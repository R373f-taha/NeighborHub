<?php

declare(strict_types=1);

namespace Tests\Feature\Issues;

use Illuminate\Support\Carbon;
use Modules\Issue\app\Models\Issue;

/**
 * Issue READ API: routing, authorization, pagination, deterministic ordering,
 * category listing, and resource data safety.
 */
class IssueReadApiTest extends IssueTestCase
{
    // ── Routing / Auth ──

    public function test_anonymous_index_is_unauthenticated(): void
    {
        $this->getJson($this->indexUri($this->communityA))->assertStatus(401);
    }

    public function test_anonymous_show_is_unauthenticated(): void
    {
        $this->getJson($this->showUri($this->communityA, $this->issueA))->assertStatus(401);
    }

    public function test_anonymous_categories_is_unauthenticated(): void
    {
        $this->getJson('/v1/issue-categories')->assertStatus(401);
    }

    // ── Access ──

    public function test_resident_can_index_own_community_issues(): void
    {
        $this->getJson($this->indexUri($this->communityA), $this->token($this->residentUserA))
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_resident_can_show_issue_in_own_community(): void
    {
        $this->getJson($this->showUri($this->communityA, $this->issueA), $this->token($this->residentUserA))
            ->assertStatus(200)
            ->assertJsonPath('data.id', $this->issueA->id);
    }

    public function test_manager_can_read_issues(): void
    {
        $this->getJson($this->indexUri($this->communityA), $this->token($this->managerA))->assertStatus(200);
        $this->getJson($this->showUri($this->communityA, $this->issueA), $this->token($this->managerA))->assertStatus(200);
    }

    public function test_super_admin_can_read_issues(): void
    {
        $this->getJson($this->indexUri($this->communityA), $this->token($this->superAdmin))->assertStatus(200);
        $this->getJson($this->showUri($this->communityA, $this->issueA), $this->token($this->superAdmin))->assertStatus(200);
    }

    // ── Community scoping ──

    public function test_index_returns_only_requested_community_issues(): void
    {
        $response = $this->getJson($this->indexUri($this->communityA), $this->token($this->residentUserA))
            ->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertContains($this->issueA->id, $ids);
        $this->assertNotContains($this->issueB->id, $ids);
    }

    public function test_show_rejects_issue_from_other_community(): void
    {
        // Exposes a production defect: `show` resolves the issue by global id
        // (Issue::findOrFail) and never verifies the issue belongs to the
        // community in the URL, so a resident of community A can read an issue
        // that belongs to community B through community A's route.
        // Expected: 404 (the issue does not belong to this community).
        // Actual: 200 with community B's issue data.
        $response = $this->getJson($this->showUri($this->communityA, $this->issueB), $this->token($this->residentUserA));

        $this->assertSame(404, $response->status(), 'PRODUCTION DEFECT: cross-community issue is returned instead of 404.');
    }

    public function test_missing_issue_is_404(): void
    {
        $this->getJson($this->showUri($this->communityA, 999999), $this->token($this->residentUserA))
            ->assertStatus(404);
    }

    public function test_soft_deleted_issue_is_404(): void
    {
        $this->issueA->delete();

        $this->getJson($this->showUri($this->communityA, $this->issueA), $this->token($this->residentUserA))
            ->assertStatus(404);
    }

    // ── Pagination / Ordering ──

    public function test_index_paginates(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->createIssue();
        }

        $response = $this->getJson($this->indexUri($this->communityA), $this->token($this->residentUserA))
            ->assertStatus(200);

        $this->assertCount(15, $response->json('data'));
        $this->assertSame(15, $response->json('meta.per_page'));
        $this->assertSame(21, $response->json('meta.total'));
    }

    public function test_index_is_ordered_newest_first(): void
    {
        $older = $this->createIssue(['created_at' => Carbon::now()->subDays(3)]);
        $newer = $this->createIssue(['created_at' => Carbon::now()->subDay()]);

        $ids = collect(
            $this->getJson($this->indexUri($this->communityA), $this->token($this->residentUserA))->assertStatus(200)->json('data')
        )->pluck('id')->values()->all();

        $this->assertTrue(array_search($newer->id, $ids, true) < array_search($older->id, $ids, true), 'newer issue should come first');
    }

    private function createIssue(array $overrides = []): Issue
    {
        $createdAt = array_key_exists('created_at', $overrides) ? $overrides['created_at'] : null;
        unset($overrides['created_at']);

        $issue = Issue::create(array_merge([
            'community_id' => $this->communityA->id,
            'category_id' => $this->category->id,
            'title' => 'Issue',
            'description' => 'd',
            'location' => 'l',
            'priority' => 'low',
            'status' => 'open',
            'reported_by' => $this->residentUserA->id,
        ], $overrides));

        // created_at is not mass-assignable on the Issue model, so set it
        // explicitly to allow deterministic ordering assertions.
        if ($createdAt !== null) {
            $issue->created_at = $createdAt;
            $issue->save();
        }

        return $issue->fresh();
    }

    // ── Categories ──

    public function test_categories_list_only_active(): void
    {
        $response = $this->getJson('/v1/issue-categories', $this->token($this->residentUserA))
            ->assertStatus(200)
            ->assertJsonStructure(['data']);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Plumbing', $names);
        $this->assertNotContains('Deprecated', $names);
    }

    // ── Data contract / safety ──

    public function test_show_returns_expected_resource_fields(): void
    {
        $data = $this->getJson($this->showUri($this->communityA, $this->issueA), $this->token($this->residentUserA))
            ->assertStatus(200)
            ->json('data');

        $this->assertSame($this->issueA->id, $data['id']);
        $this->assertSame('Leak A', $data['title']);
        $this->assertSame('high', $data['priority']);
        $this->assertSame('open', $data['status']);
        $this->assertSame($this->category->id, $data['category']['id']);
        $this->assertSame($this->residentUserA->id, $data['reported_by']['id']);
        $this->assertSame($this->communityA->id, $data['community']['id']);
        $this->assertNull($data['assigned_to']);
    }

    public function test_show_does_not_leak_sensitive_fields(): void
    {
        $json = $this->getJson($this->showUri($this->communityA, $this->issueA), $this->token($this->residentUserA))
            ->assertStatus(200)
            ->getContent();

        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString('email', $json);
        $this->assertStringNotContainsString('remember_token', $json);
        $this->assertStringNotContainsString('"reported_by":' . $this->residentUserA->id, $json);
    }
}
