<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Illuminate\Support\Facades\DB;
use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Models\IssueStatusLog;
use Modules\Interaction\app\Models\Reaction;
use Modules\Post\app\Models\Post;
use Modules\ServiceListing\app\Models\ServiceListing;

/**
 * Data-correctness, community isolation and privacy for the Reports API.
 *
 * Each test seeds a known dataset across two communities and asserts the
 * aggregation matches exactly, that the other community's data never leaks in,
 * and that private user attributes are not serialized.
 */
class ReportsDataTest extends ReportsTestCase
{
    public function test_issues_summary_counts_match_known_dataset(): void
    {
        $this->seedIssuesForSummary();

        $response = $this->getJson($this->uri('issues-summary', $this->communityA), $this->token($this->managerA))
            ->assertStatus(200);

        $data = $response->json();

        // Core counts (community B's issue is excluded).
        $this->assertSame(3, $data['total']);
        $this->assertSame(1, $data['open']);
        $this->assertSame(0, $data['assigned']);
        $this->assertSame(1, $data['in_progress']);
        $this->assertSame(1, $data['resolved']);
        $this->assertSame(0, $data['closed']);

        // Distribution by priority.
        $this->assertSame(1, $data['by_priority']['high']);
        $this->assertSame(1, $data['by_priority']['low']);
        $this->assertSame(1, $data['by_priority']['medium']);

        // Distribution by category only includes this community's issues.
        $category = collect($data['by_category'])->firstWhere('category_name', 'Plumbing');
        $this->assertNotNull($category);
        $this->assertSame(3, $category['count']);

        // Average resolution derived from the single resolved issue (30 minutes).
        $this->assertSame(30.0, (float) $data['average_resolution_time_minutes']);
    }

    public function test_providers_report_aggregates_and_is_safe(): void
    {
        $this->seedIssuesForProviders();

        $response = $this->getJson($this->uri('providers', $this->communityA), $this->token($this->managerA))
            ->assertStatus(200);

        $providers = $response->json();

        $this->assertCount(1, $providers);
        $entry = $providers[0];

        $this->assertSame($this->provider->id, $entry['provider_id']);
        $this->assertSame($this->provider->name, $entry['provider_name']);
        $this->assertSame(3, $entry['total']);
        $this->assertSame(1, $entry['assigned']);
        $this->assertSame(1, $entry['resolved']);
        $this->assertSame(1, $entry['closed']);
        $this->assertSame(66.67, (float) $entry['completion_rate']);

        // Privacy: a provider analytics row must never leak account credentials.
        $this->assertArrayNotHasKey('email', $entry);
        $this->assertArrayNotHasKey('password', $entry);
        $this->assertArrayNotHasKey('phone', $entry);
    }

    public function test_services_activity_report_counts(): void
    {
        $this->seedServiceListings();

        $response = $this->getJson($this->uri('services-activity', $this->communityA), $this->token($this->managerA))
            ->assertStatus(200);

        $data = $response->json();

        $this->assertSame(2, $data['by_type']['sale']);
        $this->assertSame(2, $data['by_type']['rent']);
        $this->assertSame(3, $data['by_status']['active']);
        $this->assertSame(1, $data['by_status']['closed']);
        $this->assertSame(1, $data['closure']['automatic_expired']);
        $this->assertSame(1, $data['closure']['manually_closed']);
    }

    public function test_engagement_report_aggregates_activity(): void
    {
        $this->seedEngagementData();

        $response = $this->getJson($this->uri('engagement', $this->communityA), $this->token($this->managerA))
            ->assertStatus(200);

        $data = $response->json();

        $this->assertSame(3, $data['posts']);
        $this->assertSame(2, $data['posts_by_category']['general']);
        $this->assertSame(1, $data['posts_by_category']['event']);
        $this->assertSame(2, $data['comments']);
        $this->assertSame(1, $data['reactions']);
        $this->assertSame(1, $data['poll_votes']);
        $this->assertSame(1, $data['poll_participation']['active_residents']);
        $this->assertSame(1, $data['poll_participation']['unique_voters']);
        $this->assertSame(100.0, (float) $data['poll_participation']['percentage']);
    }

    // ---------------------------------------------------------------------
    // Dataset seeders
    // ---------------------------------------------------------------------

    private function seedIssuesForSummary(): void
    {
        Issue::create([
            'community_id' => $this->communityA->id, 'category_id' => $this->categoryA->id,
            'title' => 'Open leak', 'description' => 'd', 'location' => 'l',
            'priority' => 'high', 'status' => 'open', 'reported_by' => $this->residentUserA->id,
        ]);

        $resolved = Issue::create([
            'community_id' => $this->communityA->id, 'category_id' => $this->categoryA->id,
            'title' => 'Fixed tap', 'description' => 'd', 'location' => 'l',
            'priority' => 'low', 'status' => 'resolved', 'reported_by' => $this->residentUserA->id,
        ]);
        IssueStatusLog::create([
            'issue_id' => $resolved->id, 'old_status' => 'in_progress', 'new_status' => 'resolved',
            'changed_by' => $this->provider->id, 'note' => 'done',
        ]);
        // Issue created 60 minutes ago, resolved 30 minutes ago -> 30 minute resolution.
        Issue::whereKey($resolved->id)->update(['created_at' => now()->subHour(), 'updated_at' => now()->subHour()]);
        IssueStatusLog::where('issue_id', $resolved->id)->where('new_status', 'resolved')
            ->update(['created_at' => now()->subMinutes(30), 'updated_at' => now()->subMinutes(30)]);

        Issue::create([
            'community_id' => $this->communityA->id, 'category_id' => $this->categoryA->id,
            'title' => 'WIP pipe', 'description' => 'd', 'location' => 'l',
            'priority' => 'medium', 'status' => 'in_progress', 'reported_by' => $this->residentUserA->id,
        ]);

        // Isolation: an issue in community B must never be counted for A.
        Issue::create([
            'community_id' => $this->communityB->id, 'category_id' => $this->categoryA->id,
            'title' => 'Other community', 'description' => 'd', 'location' => 'l',
            'priority' => 'urgent', 'status' => 'open', 'reported_by' => $this->residentUserA->id,
        ]);
    }

    private function seedIssuesForProviders(): void
    {
        $assigned = Issue::create([
            'community_id' => $this->communityA->id, 'category_id' => $this->categoryA->id,
            'title' => 'Assigned', 'description' => 'd', 'location' => 'l',
            'priority' => 'high', 'status' => 'assigned', 'reported_by' => $this->residentUserA->id,
            'assigned_to' => $this->provider->id,
        ]);

        $resolved = Issue::create([
            'community_id' => $this->communityA->id, 'category_id' => $this->categoryA->id,
            'title' => 'Resolved', 'description' => 'd', 'location' => 'l',
            'priority' => 'medium', 'status' => 'resolved', 'reported_by' => $this->residentUserA->id,
            'assigned_to' => $this->provider->id,
        ]);
        IssueStatusLog::create(['issue_id' => $resolved->id, 'old_status' => 'open', 'new_status' => 'assigned', 'changed_by' => $this->managerA->id, 'note' => null, 'created_at' => now()->subHour(), 'updated_at' => now()->subHour()]);
        IssueStatusLog::create(['issue_id' => $resolved->id, 'old_status' => 'assigned', 'new_status' => 'resolved', 'changed_by' => $this->provider->id, 'note' => null, 'created_at' => now()->subMinutes(30), 'updated_at' => now()->subMinutes(30)]);

        Issue::create([
            'community_id' => $this->communityA->id, 'category_id' => $this->categoryA->id,
            'title' => 'Closed', 'description' => 'd', 'location' => 'l',
            'priority' => 'low', 'status' => 'closed', 'reported_by' => $this->residentUserA->id,
            'assigned_to' => $this->provider->id,
        ]);

        // Isolation: an assigned issue in community B must not appear for A.
        Issue::create([
            'community_id' => $this->communityB->id, 'category_id' => $this->categoryA->id,
            'title' => 'Other', 'description' => 'd', 'location' => 'l',
            'priority' => 'high', 'status' => 'assigned', 'reported_by' => $this->residentUserA->id,
            'assigned_to' => $this->provider->id,
        ]);
    }

    private function seedServiceListings(): void
    {
        $make = function (array $a) {
            return ServiceListing::create(array_merge([
                'community_id' => $this->communityA->id, 'resident_id' => $this->residentA->id,
                'title' => 'svc', 'description' => 'd', 'price' => 10,
            ], $a));
        };

        $make(['type' => 'sale', 'status' => 'active', 'expires_at' => now()->addDays(5), 'closed_at' => null]);
        $make(['type' => 'sale', 'status' => 'active', 'expires_at' => now()->addDays(5), 'closed_at' => null]);
        $make(['type' => 'rent', 'status' => 'active', 'expires_at' => now()->subDay(), 'closed_at' => null]); // auto-expired
        $make(['type' => 'rent', 'status' => 'closed', 'expires_at' => now()->addDays(5), 'closed_at' => now()]); // manually closed

        // Isolation: a listing in community B must not be counted for A.
        ServiceListing::create([
            'community_id' => $this->communityB->id, 'resident_id' => $this->residentA->id,
            'title' => 'other', 'description' => 'd', 'type' => 'sale', 'price' => 5,
            'status' => 'active', 'expires_at' => now()->addDay(), 'closed_at' => null,
        ]);
    }

    private function seedEngagementData(): void
    {
        $postNews1 = Post::create(['community_id' => $this->communityA->id, 'resident_id' => $this->residentA->id, 'category' => 'general', 'content' => 'n1']);
        Post::create(['community_id' => $this->communityA->id, 'resident_id' => $this->residentA->id, 'category' => 'general', 'content' => 'n2']);
        Post::create(['community_id' => $this->communityA->id, 'resident_id' => $this->residentA->id, 'category' => 'event', 'content' => 'e1']);

        // Use the post's morph relations so the commentable/reactionable type is
        // stored through the global morph map alias ("post"), matching the report.
        $postNews1->comments()->create(['author_id' => $this->managerA->id, 'content' => 'c1']);
        $postNews1->comments()->create(['author_id' => $this->provider->id, 'content' => 'c2']);

        $reaction = (new Reaction())->forceFill(['type' => 'like', 'user_id' => $this->managerA->id]);
        $postNews1->reactions()->save($reaction);

        // Poll + option + single vote (by residentA) in community A.
        $pollId = DB::table('polls')->insertGetId([
            'community_id' => $this->communityA->id, 'created_by' => $this->managerA->id,
            'title' => 'Poll A', 'description' => 'd', 'type' => 'single_choice',
            'status' => 'active', 'ends_at' => now()->addDays(3), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $optionId = DB::table('poll_options')->insertGetId([
            'poll_id' => $pollId, 'text' => 'Yes', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('poll_votes')->insert([
            'poll_id' => $pollId, 'option_id' => $optionId, 'submitted_at' => now(),
            'voter_id' => $this->residentA->id, 'voted_at' => now()->toDateString(),
        ]);

        // Isolation: a post + poll in community B must not be counted for A.
        $bPost = Post::create(['community_id' => $this->communityB->id, 'resident_id' => $this->residentA->id, 'category' => 'general', 'content' => 'b']);
        $bPost->comments()->create(['author_id' => $this->managerB->id, 'content' => 'bc']);
        $bPoll = DB::table('polls')->insertGetId([
            'community_id' => $this->communityB->id, 'created_by' => $this->managerB->id,
            'title' => 'Poll B', 'description' => 'd', 'type' => 'single_choice',
            'status' => 'active', 'ends_at' => now()->addDays(3), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $bOption = DB::table('poll_options')->insertGetId([
            'poll_id' => $bPoll, 'text' => 'No', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('poll_votes')->insert([
            'poll_id' => $bPoll, 'option_id' => $bOption, 'submitted_at' => now(),
            'voter_id' => $this->residentA->id, 'voted_at' => now()->toDateString(),
        ]);
    }
}
