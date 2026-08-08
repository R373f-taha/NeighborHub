<?php

declare(strict_types=1);

namespace Tests\Feature\Announcements;

use Illuminate\Support\Carbon;
use Modules\Community\app\Models\Announcement;
use Modules\Interaction\app\Models\Reaction;

/**
 * Announcement WRITE API: store/update/delete authorization, validation,
 * mass-assignment protection, server-controlled fields, and reactions.
 */
class AnnouncementWriteApiTest extends AnnouncementTestCase
{
    private array $validPayload;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validPayload = ['title' => 'Town hall meeting', 'content' => 'Join us Friday.', 'priority' => 'important'];
    }

    // ── Store ──

    public function test_anonymous_store_is_unauthenticated(): void
    {
        $this->postJson($this->indexUri($this->communityA), $this->validPayload)->assertStatus(401);
    }

    public function test_resident_cannot_create_announcement(): void
    {
        $this->postJson($this->indexUri($this->communityA), $this->validPayload, $this->token($this->residentUserA))
            ->assertStatus(403);
    }

    public function test_manager_of_another_community_cannot_create(): void
    {
        $this->postJson($this->indexUri($this->communityA), $this->validPayload, $this->token($this->managerB))
            ->assertStatus(403);
    }

    public function test_manager_of_community_can_create(): void
    {
        $response = $this->postJson($this->indexUri($this->communityA), $this->validPayload, $this->token($this->managerA))
            ->assertStatus(201);

        $created = Announcement::latest('id')->first();
        $this->assertNotNull($created);
        $this->assertSame($response->json('data.id'), $created->id);
        $this->assertSame($this->communityA->id, $created->community_id, 'community scoped to route');
        $this->assertSame($this->managerA->id, $created->created_by_manager, 'creator is the authenticated manager');
        $this->assertSame('important', $created->priority);
    }

    public function test_super_admin_can_create(): void
    {
        $this->postJson($this->indexUri($this->communityA), $this->validPayload, $this->token($this->superAdmin))
            ->assertStatus(201);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->postJson($this->indexUri($this->communityA), [], $this->token($this->managerA))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'content', 'priority']);
    }

    public function test_store_validates_priority_enum(): void
    {
        $this->postJson($this->indexUri($this->communityA), array_replace($this->validPayload, ['priority' => 'critical']), $this->token($this->managerA))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);
    }

    public function test_store_validates_title_length(): void
    {
        $this->postJson($this->indexUri($this->communityA), array_replace($this->validPayload, ['title' => str_repeat('x', 256)]), $this->token($this->managerA))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_store_ignores_spoofed_server_controlled_fields(): void
    {
        $spoof = array_replace($this->validPayload, [
            'community_id' => $this->communityB->id,
            'created_by_manager' => $this->managerB->id,
        ]);

        $this->postJson($this->indexUri($this->communityA), $spoof, $this->token($this->managerA))
            ->assertStatus(201);

        $created = Announcement::latest('id')->first();
        $this->assertSame($this->communityA->id, $created->community_id, 'community_id is server-controlled from the route');
        $this->assertSame($this->managerA->id, $created->created_by_manager, 'creator is server-controlled from the authenticated user');
    }

    public function test_store_accepts_optional_pinned_until(): void
    {
        $payload = array_replace($this->validPayload, ['pinned_until' => Carbon::now()->addDays(3)->toDateTimeString()]);

        $this->postJson($this->indexUri($this->communityA), $payload, $this->token($this->managerA))
            ->assertStatus(201);

        $created = Announcement::latest('id')->first();
        $this->assertNotNull($created->pinned_until);
    }

    // ── Update ──

    public function test_anonymous_update_is_unauthenticated(): void
    {
        $this->putJson($this->showUri($this->communityA, $this->announcementA), ['title' => 'Changed'])->assertStatus(401);
    }

    public function test_resident_cannot_update(): void
    {
        $this->putJson($this->showUri($this->communityA, $this->announcementA), ['title' => 'Changed'], $this->token($this->residentUserA))
            ->assertStatus(403);
    }

    public function test_manager_who_is_not_the_creator_cannot_update(): void
    {
        $this->putJson($this->showUri($this->communityA, $this->announcementA), ['title' => 'Changed'], $this->token($this->managerANonCreator))
            ->assertStatus(403);
    }

    public function test_creator_manager_can_update(): void
    {
        $this->putJson($this->showUri($this->communityA, $this->announcementA), ['title' => 'Updated title'], $this->token($this->managerA))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Announcement updated successfully');

        $this->assertSame('Updated title', $this->announcementA->fresh()->title);
    }

    public function test_super_admin_can_update_any_announcement(): void
    {
        $this->putJson($this->showUri($this->communityA, $this->announcementA), ['title' => 'Admin edit'], $this->token($this->superAdmin))
            ->assertStatus(200);

        $this->assertSame('Admin edit', $this->announcementA->fresh()->title);
    }

    public function test_update_validates_priority_enum(): void
    {
        $this->putJson($this->showUri($this->communityA, $this->announcementA), ['priority' => 'critical'], $this->token($this->managerA))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);
    }

    // ── Delete ──

    public function test_anonymous_delete_is_unauthenticated(): void
    {
        $this->deleteJson($this->showUri($this->communityA, $this->announcementA))->assertStatus(401);
    }

    public function test_resident_cannot_delete(): void
    {
        $this->deleteJson($this->showUri($this->communityA, $this->announcementA), [], $this->token($this->residentUserA))
            ->assertStatus(403);
    }

    public function test_creator_manager_can_delete(): void
    {
        $this->deleteJson($this->showUri($this->communityA, $this->announcementA), [], $this->token($this->managerA))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Announcement deleted successfully');

        $this->assertSoftDeleted('announcements', ['id' => $this->announcementA->id]);
    }

    public function test_super_admin_can_delete_any_announcement(): void
    {
        $this->deleteJson($this->showUri($this->communityA, $this->announcementA), [], $this->token($this->superAdmin))
            ->assertStatus(200);
    }

    // ── Reactions ──

    public function test_anonymous_react_is_unauthenticated(): void
    {
        $this->postJson($this->reactUri($this->communityA, $this->announcementA), ['type' => 'like'])->assertStatus(401);
    }

    public function test_manager_cannot_react_because_reaction_is_resident_only(): void
    {
        $this->postJson($this->reactUri($this->communityA, $this->announcementA), ['type' => 'like'], $this->token($this->managerA))
            ->assertStatus(403);
    }

    public function test_react_validates_type_enum(): void
    {
        $this->postJson($this->reactUri($this->communityA, $this->announcementA), ['type' => 'invalid'], $this->token($this->residentUserA))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_outsider_resident_cannot_react_to_other_community_announcement(): void
    {
        $this->postJson($this->reactUri($this->communityA, $this->announcementA), ['type' => 'like'], $this->token($this->residentUserB))
            ->assertStatus(403);
    }

    public function test_resident_can_react_to_announcement_in_own_community(): void
    {
        // Exposes a production defect: the react route's implicit {announcement}
        // binding resolves to an empty model, so the controller authorization
        // (which checks community membership) always fails for residents.
        // Expected: 201/200 success and a stored reaction.
        // Actual: 403 "This action is unauthorized." for a valid same-community resident.
        $response = $this->postJson($this->reactUri($this->communityA, $this->announcementA), ['type' => 'like'], $this->token($this->residentUserA));

        $this->assertSame(200, $response->status(), 'PRODUCTION DEFECT: resident reaction currently rejected (broken route binding).');
        $this->assertSame(1, Reaction::where('reactionable_type', Announcement::class)->where('reactionable_id', $this->announcementA->id)->count());
    }

    public function test_react_returns_404_for_unknown_announcement(): void
    {
        // Exposes a production defect: the react route's {announcement} binding
        // resolves to an empty model regardless of the id, so a non-existent
        // announcement is reported as unauthorized rather than not found.
        $response = $this->postJson($this->reactUri($this->communityA, 999999), ['type' => 'like'], $this->token($this->residentUserA));

        $this->assertSame(404, $response->status(), 'PRODUCTION DEFECT: unknown announcement returns authorization failure instead of 404.');
    }
}
