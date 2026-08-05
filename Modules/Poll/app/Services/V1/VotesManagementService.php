<?php

namespace Modules\Poll\App\Services\V1;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Poll\app\Models\Poll;
use Modules\Poll\app\Models\PollOption;
use Modules\Poll\app\Models\PollVote;
use Modules\Poll\app\Enums\PollStatus;
use Modules\Poll\app\Events\PollClosed;

use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Auth\app\Models\User;
use Modules\Poll\app\Events\PollCreated;
use Modules\Poll\app\Traits\CacheableTrait;


class VotesManagementService{

use CacheableTrait;

   private const LIST_TTL = 600; // 10 minutes

   /**
     * Vote on a poll.
     *
     * @return array ['success' => bool, 'message' => string, 'data' => mixed]
     */
    public function vote(Poll $poll, Resident $voter, int $optionId): array
    {
        if ($poll->status !== 'active') {
            return [
                'success' => false,
                'message' => 'Poll is not active.',
                'data' => null,
            ];
        }

        if ($poll->ends_at <= now()) {
            return [
                'success' => false,
                'message' => 'Poll has expired.',
                'data' => null,
            ];
        }

        $existingVote = PollVote::where('poll_id', $poll->id)
            ->where('voter_id', $voter->id)
            ->exists();

        if ($existingVote) {
            return [
                'success' => false,
                'message' => 'You have already voted.',
                'data' => null,
            ];
        }

        $option = PollOption::where('id', $optionId)
            ->where('poll_id', $poll->id)
            ->first();

        if (!$option) {
            return [
                'success' => false,
                'message' => 'Option does not belong to this poll.',
                'data' => null,
            ];
        }

        try {
            $pollVote = DB::transaction(function () use ($poll, $voter, $optionId) {
                $vote = PollVote::create([
                    'poll_id' => $poll->id,
                    'voter_id' => $voter->id,
                    'option_id' => $optionId,
                    'submitted_at' => now(),
                    'voted_at'=>now()
                ]);

                $this->clearCache($poll->id);

                return $vote;
            });

            Log::info('🗳️ Vote submitted', [
                'poll_id' => $poll->id,
                'voter_id' => $voter->id,
                'option_id' => $optionId,
            ]);

            return [
                'success' => true,
                'message' => 'Vote submitted successfully.',
                'data' => $pollVote,
            ];

        } catch (\Exception $e) {var_dump($e);
            Log::error('Vote failed', [
                'poll_id' => $poll->id,
                'voter_id' => $voter->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to submit vote. Please try again.',
                'data' => null,
            ];
        }
    }



    public function getResults(Poll $poll): array
    {

        $cacheKey = $this->cacheKey('results', $poll->id);

        return Cache::rememberForever($cacheKey, function () use($poll){

            $totalVotes = PollVote::where('poll_id', $poll->id)->count();
            $options = PollOption::where('poll_id', $poll->id)
                ->withCount(['votes'])
                ->get();

            $totalResidents = Resident::where('community_id', $poll->community_id)
                ->where('status', 'active')
                ->where('current_marker', true)
                ->count();

            $turnout = $totalResidents > 0
                ? round(($totalVotes / $totalResidents) * 100, 2)
                : 0;

            return [
                'poll_id' => $poll->id,
                'title' => $poll->title,
                'description' => $poll->description,
                'total_votes' => $totalVotes,
                'turnout' => $turnout,
                'status' => 'closed',
                'closed_at' => $poll->closed_at?->toISOString(),
                'options' => $options->map(function ($option) use ($totalVotes) {
                    return [
                        'id' => $option->id,
                        'text' => $option->text,
                        'votes' => $option->votes_count,
                        'percentage' => $totalVotes > 0
                            ? round(($option->votes_count / $totalVotes) * 100, 2)
                            : 0,
                    ];
                })->toArray(),
            ];
        });
    }

    public function getTotalVotes(int $pollId): int
    {
        $cacheKey = $this->cacheKey('votes', $pollId);

        return $this->rememberWithLock($cacheKey, self::LIST_TTL, function () use ($pollId) {
            return PollVote::where('poll_id', $pollId)->count();
        });
    }


    public function hasUserVoted(int $pollId, int $residentId): bool
    {
        return PollVote::where('poll_id', $pollId)
            ->where('voter_id', $residentId)
            ->exists();
    }




}
