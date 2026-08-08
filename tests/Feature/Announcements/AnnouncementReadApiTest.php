<?php

declare(strict_types=1);

namespace Tests\Feature\Announcements;

use Illuminate\Support\Carbon;
use Modules\Community\app\Models\Announcement;

/**
 * Announcement READ API: routing, authorization, community isolation,
 * pagination, deterministic ordering, and resource data safety.
 */
class AnnouncementReadApiTest extends AnnouncementTestCase
{
    // ── Routing / Auth ──

    public function test_anonymous_index_is_unauthenticated(): void
    {
        $this->getJson($this->indexUri($this->communityA))->assertStatus(401);
    }

    public function test_anonymous_show_is_unauthenticated(): void
    {
        $this->getJson($this->showUri($this->communityA, $this->announcementA))->assertStatus(401);
    }

    // ── Access ──

    public function test_active_resident_of_same_community_can_index(): void
    {
        $this->getJson($this->indexUri($this->communityA), $this->token($this->residentUserA))
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_active_resident_of_same_community_can_show(): void
    {
        $this->getJson($this->showUri($this->communityA, $this->announcementA), $this->token($this->residentUserA))
            ->assertStatus(200)
            ->assertJsonPath('data.id', $this->announcementA->id);
    }

    public function test_manager_of_same_community_can_read(): void
    {
        $this->getJson($this->indexUri($this->communityA), $this->token($this->managerA))->assertStatus(200);
        $this->getJson($this->showUri($this->communityA, $this->announcementA), $this->token($this->managerA))->assertStatus(200);
    }

    public function test_super_admin_can_read(): void
    {
        $this->getJson($this->indexUri($this->communityA), $this->token($this->superAdmin))->assertStatus(200);
        $this->getJson($this->showUri($this->communityA, $this->announcementA), $this->token($this->superAdmin))->assertStatus(200);
    }

    public function test_outsider_resident_cannot_show_other_community_announcement(): void
    {
        $this->getJson($this->showUri($this->communityA, $this->announcementA), $this->token($this->residentUserB))
            ->assertStatus(403);
    }

    public function test_outsider_resident_index_currently_allows_listing_other_community(): void
    {
        // Community-membership authorization is enforced on `show` but not on
        // `index`. Documented production gap: an active resident of community B
        // receives community A's announcement list (data is scoped to the URL
        // community, but access is not gated to membership).
        $this->getJson($this->indexUri($this->communityA), $this->token($this->residentUserB))
            ->assertStatus(200);
    }

    // ── Community isolation / IDOR ──

    public function test_show_rejects_announcement_from_other_community(): void
    {
        $this->getJson($this->showUri($this->communityA, $this->announcementB), $this->token($this->residentUserA))
            ->assertStatus(404);
    }

    public function test_missing_announcement_is_404(): void
    {
        $this->getJson($this->showUri($this->communityA, 999999), $this->token($this->residentUserA))
            ->assertStatus(404);
    }

    public function test_soft_deleted_announcement_is_404(): void
    {
        $this->announcementA->delete();

        $this->getJson($this->showUri($this->communityA, $this->announcementA), $this->token($this->residentUserA))
            ->assertStatus(404);
    }

    public function test_index_returns_only_requested_community_announcements(): void
    {
        $response = $this->getJson($this->indexUri($this->communityA), $this->token($this->residentUserA))
            ->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertContains($this->announcementA->id, $ids);
        $this->assertNotContains($this->announcementB->id, $ids);
    }

    // ── Pagination / Ordering ──

    public function test_index_paginates(): void
    {
        Announcement::factory()->count(20)->create([
            'community_id' => $this->communityA->id,
            'created_by_manager' => $this->managerA->id,
        ]);

        $response = $this->getJson($this->indexUri($this->communityA), $this->token($this->residentUserA))
            ->assertStatus(200);

        // The controller paginates with a fixed page size of 15 and does not
        // honor a client-supplied per_page.
        $this->assertCount(15, $response->json('data'));
        $this->assertSame(15, $response->json('meta.per_page'));
        $this->assertSame(21, $response->json('meta.total'));
        $this->assertGreaterThan(1, $response->json('meta.last_page'));
    }

    public function test_index_is_ordered_newest_first(): void
    {
        $older = Announcement::factory()->create([
            'community_id' => $this->communityA->id, 'created_by_manager' => $this->managerA->id,
            'created_at' => Carbon::now()->subDays(3),
        ]);
        $newer = Announcement::factory()->create([
            'community_id' => $this->communityA->id, 'created_by_manager' => $this->managerA->id,
            'created_at' => Carbon::now()->subDay(),
        ]);

        $ids = collect(
            $this->getJson($this->indexUri($this->communityA) . '?per_page=100', $this->token($this->residentUserA))
                ->assertStatus(200)
                ->json('data')
        )->pluck('id')->values()->all();

        $this->assertTrue(array_search($newer->id, $ids, true) < array_search($older->id, $ids, true), 'newer announcement should come first');
    }

    // ── Data contract / safety ──

    public function test_show_returns_expected_resource_fields(): void
    {
        $data = $this->getJson($this->showUri($this->communityA, $this->announcementA), $this->token($this->residentUserA))
            ->assertStatus(200)
            ->json('data');

        $this->assertSame($this->announcementA->id, $data['id']);
        $this->assertSame('Announcement A', $data['title']);
        $this->assertSame('normal', $data['priority']);
        $this->assertSame($this->managerA->id, $data['creator']['id']);
        $this->assertSame($this->managerA->name, $data['creator']['name']);
    }

    public function test_show_does_not_leak_sensitive_fields(): void
    {
        $json = $this->getJson($this->showUri($this->communityA, $this->announcementA), $this->token($this->residentUserA))
            ->assertStatus(200)
            ->getContent();

        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString('email', $json);
        $this->assertStringNotContainsString('remember_token', $json);
        $this->assertStringNotContainsString('created_by_manager', $json);
    }
}
