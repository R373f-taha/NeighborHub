<?php

namespace Modules\Reports\app\Services;

use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Models\IssueCategory;
use Modules\Issue\app\Models\IssueStatusLog;
use Modules\Post\app\Models\Post;
use Modules\Interaction\app\Models\Comment;
use Modules\Interaction\app\Models\Reaction;
use Modules\Poll\app\Models\PollVote;
use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Models\Resident;
use Modules\ServiceListing\app\Models\ServiceListing;

class ReportService
{
    /**
     * Issues Summary
     *
     * GET /communities/{community}/reports/issues-summary
     */
    public function issuesSummary($communityId): array
    {
        $issues = Issue::where('community_id', $communityId);

        $total = (clone $issues)->count();

        $statusCounts = (clone $issues)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $averageResolutionMinutes = $this->calculateAverageResolutionTime(
            $communityId
        );

        //Distribution by category

        $byCategory = IssueCategory::withCount([
            'issues' => function ($query) use ($communityId) {
                $query->where('community_id', $communityId);
            }
        ])
            ->get()
            ->map(function ($category) {
                return [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'count' => (int) $category->issues_count,
                ];
            })
            ->values()
            ->toArray();

        // Distribution by priority

        $byPriority = (clone $issues)
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->map(fn ($count) => (int) $count)
            ->toArray();

        return [
            'total' => $total,

            'open' => (int) ($statusCounts['open'] ?? 0),
            'assigned' => (int) ($statusCounts['assigned'] ?? 0),
            'in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
            'resolved' => (int) ($statusCounts['resolved'] ?? 0),
            'closed' => (int) ($statusCounts['closed'] ?? 0),

            'by_category' => $byCategory,

            'by_priority' => $byPriority,

            'average_resolution_time_minutes' => $averageResolutionMinutes,
        ];
    }

    //Engagement Report


    public function engagement($communityId): array
    {
        // Posts by category

        $postsByCategory = Post::where('community_id', $communityId)
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->map(fn ($count) => (int) $count)
            ->toArray();


        $posts = Post::where('community_id', $communityId)
            ->count();

        /**
         * Comments on posts, issues and announcements.
         *
         * Because comments are polymorphic, nested replies
         * are also included in the total.
         */
        $comments = Comment::whereHasMorph(
            'commentable',
            [
                Post::class,
                Issue::class,
                Announcement::class,
            ],
            function ($query) use ($communityId) {
                $query->where('community_id', $communityId);
            }
        )->count();


        $reactions = Reaction::whereHasMorph(
            'reactionable',
            [
                Post::class,
                Issue::class,
                Announcement::class,
            ],
            function ($query) use ($communityId) {
                $query->where('community_id', $communityId);
            }
        )->count();


        $pollVotes = PollVote::whereHas(
            'poll',
            function ($query) use ($communityId) {
                $query->where('community_id', $communityId);
            }
        )->count();

        /*
         * Active residents
         */
        $activeResidents = Resident::where('community_id', $communityId)
            ->count();

        /**
         * Unique residents who voted.
         *
         * We count distinct voter_id instead of total votes
         * because one resident may vote more than once in
         * different polls.
         */
        $uniqueVoters = PollVote::whereHas(
            'poll',
            function ($query) use ($communityId) {
                $query->where('community_id', $communityId);
            }
        )
            ->distinct('voter_id')
            ->count('voter_id');

        $pollParticipationRate = $activeResidents > 0
            ? round(($uniqueVoters / $activeResidents) * 100, 2)
            : 0;

        return [
            'posts' => $posts,

            'posts_by_category' => $postsByCategory,

            'comments' => $comments,

            'reactions' => $reactions,

            'poll_votes' => $pollVotes,

            'poll_participation' => [
                'active_residents' => $activeResidents,
                'unique_voters' => $uniqueVoters,
                'percentage' => $pollParticipationRate,
            ],
        ];
    }

    /**
     * Providers Performance
     *
     */
    public function providers($communityId): array
    {
        $issues = Issue::with('assignee')
            ->where('community_id', $communityId)
            ->whereNotNull('assigned_to')
            ->get();

        return $issues
            ->groupBy('assigned_to')
            ->map(function ($providerIssues, $providerId) {

                $total = $providerIssues->count();

                $assigned = $providerIssues
                    ->where('status', 'assigned')
                    ->count();

                $inProgress = $providerIssues
                    ->where('status', 'in_progress')
                    ->count();

                $resolved = $providerIssues
                    ->where('status', 'resolved')
                    ->count();

                $closed = $providerIssues
                    ->where('status', 'closed')
                    ->count();

                /**
                 * Calculate resolution time for every issue
                 * assigned to this provider.
                 */
                $resolutionTimes = [];

                foreach ($providerIssues as $issue) {

                    $assignedLog = IssueStatusLog::where('issue_id', $issue->id)
                        ->where('new_status', 'assigned')
                        ->orderBy('created_at')
                        ->first();

                    $resolvedLog = IssueStatusLog::where('issue_id', $issue->id)
                        ->whereIn('new_status', ['resolved', 'closed'])
                        ->orderBy('created_at')
                        ->first();

                    if ($assignedLog && $resolvedLog) {

                        $minutes = $assignedLog->created_at
                            ->diffInMinutes($resolvedLog->created_at);

                        $resolutionTimes[] = $minutes;
                    }
                }

                $averageResolutionTime = count($resolutionTimes) > 0
                    ? round(array_sum($resolutionTimes) / count($resolutionTimes), 2): 0;


                $completed = $resolved + $closed;

                $completionRate = $total > 0
                    ? round(($completed / $total) * 100, 2): 0;

                $provider = $providerIssues->first()->assignee;

                return [
                    'provider_id' => (int) $providerId,

                    'provider_name' => $provider?->name,

                    'total' => $total,

                    'assigned' => $assigned,

                    'in_progress' => $inProgress,

                    'resolved' => $resolved,

                    'closed' => $closed,

                    'average_resolution_time_minutes' =>
                        $averageResolutionTime,

                    'completion_rate' =>
                        $completionRate,
                ];
            })
            ->values()
            ->toArray();
    }


    public function servicesActivity($communityId): array
    {

        $byType = ServiceListing::where('community_id', $communityId)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->map(fn ($count) => (int) $count)
            ->toArray();

        $byStatus = ServiceListing::where('community_id', $communityId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->map(fn ($count) => (int) $count)
            ->toArray();


        $automaticExpired = ServiceListing::where(
                'community_id',
                $communityId
            )
            ->where('expires_at', '<=', now())
            ->where('status', 'active')
            ->whereNull('closed_at')
            ->count();

        $manuallyClosed = ServiceListing::where(
                'community_id',
                $communityId
            )
            ->where('status', 'closed')
            ->whereNotNull('closed_at')
            ->where('expires_at', '>', now())
            ->count();

        return [
            'by_type' => $byType,

            'by_status' => $byStatus,

            'closure' => [
                'automatic_expired' => $automaticExpired,
                'manually_closed' => $manuallyClosed,
            ],
        ];
    }

    /**
     * Calculate average issue resolution time.
     *
     * Start:
     * Issue created_at
     *
     * End:
     * First status log with resolved or closed.
     */
    private function calculateAverageResolutionTime($communityId): float
    {
        $issues = Issue::where('community_id', $communityId)
            ->whereIn('status', ['resolved', 'closed'])
            ->get();

        $resolutionTimes = [];

        foreach ($issues as $issue) {

            $resolvedLog = IssueStatusLog::where('issue_id', $issue->id)
                ->whereIn('new_status', ['resolved', 'closed'])
                ->orderBy('created_at')
                ->first();

            if (!$resolvedLog) {
                continue;
            }

            $minutes = $issue->created_at
                ->diffInMinutes($resolvedLog->created_at);

            $resolutionTimes[] = $minutes;
        }

        if (count($resolutionTimes) === 0) {
            return 0;
        }

        return round(
            array_sum($resolutionTimes) / count($resolutionTimes),2
        );
    }
}