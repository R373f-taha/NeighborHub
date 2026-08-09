<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;

/**
 * Real, end-to-end coverage of the Community API CRUD + membership flows.
 *
 * These tests exercise the actual middleware authorization, Spatie permission
 * gating, server-authoritative fields, validation and community isolation,
 * complementing the existing Mockery-based controller tests.
 */
class CommunityApiIntegrationTest extends CommunityIntegrationTestCase
{
    // -----------------------------------------------------------------------
    // Store (Super Admin only)
    // -----------------------------------------------------------------------

    public function test_store_authorization_and_creation(): void
    {
        $payload = ['name' => 'New Place', 'city' => 'Zarqa', 'address' => '1 St'];

        $this->postJson('/api/v1/communities', $payload)->assertUnauthorized();
        $this->postJson('/api/v1/communities', $payload, $this->token($this->managerA))->assertForbidden();
        $this->postJson('/api/v1/communities', $payload, $this->token($this->residentUserA))->assertForbidden();

        $response = $this->postJson('/api/v1/communities', $payload, $this->token($this->superAdmin))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'New Place');

        $created = Community::where('name', 'New Place')->first();
        $this->assertNotNull($created);
        // is_active is server-controlled (defaults true); not in the request rules.
        $this->assertTrue($created->is_active);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->postJson('/api/v1/communities', [], $this->token($this->superAdmin))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'city', 'address']);
    }

    public function test_store_can_attach_managers_server_authoritatively(): void
    {
        $this->postJson('/api/v1/communities', [
            'name' => 'Managed', 'city' => 'C', 'address' => 'A',
            'manager_ids' => [$this->managerA->id],
        ], $this->token($this->superAdmin))->assertStatus(201);

        $created = Community::where('name', 'Managed')->first();
        $this->assertTrue($created->managers()->where('manager_id', $this->managerA->id)->exists());
    }

    // -----------------------------------------------------------------------
    // Show (Manager of the community)
    // -----------------------------------------------------------------------

    public function test_show_authorization_and_retrieval(): void
    {
        $this->getJson("/api/v1/communities/{$this->communityA->id}")->assertUnauthorized();
        $this->getJson("/api/v1/communities/{$this->communityA->id}", $this->token($this->residentUserA))->assertForbidden();
        // Manager of a different community cannot view this one.
        $this->getJson("/api/v1/communities/{$this->communityA->id}", $this->token($this->managerB))->assertForbidden();

        $this->getJson("/api/v1/communities/{$this->communityA->id}", $this->token($this->managerA))
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Community A');
    }

    public function test_show_missing_community_is_404(): void
    {
        $missing = Community::max('id') + 1;

        $this->getJson("/api/v1/communities/{$missing}", $this->token($this->managerA))->assertNotFound();
    }

    // -----------------------------------------------------------------------
    // Update (Manager of the community / Super Admin)
    // -----------------------------------------------------------------------

    public function test_update_allows_community_manager_and_persists(): void
    {
        $this->putJson("/api/v1/communities/{$this->communityA->id}", [
            'name' => 'Renamed A',
        ], $this->token($this->managerA))
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Renamed A');

        $this->assertSame('Renamed A', $this->communityA->fresh()->name);
    }

    public function test_update_rejects_manager_of_other_community(): void
    {
        $this->putJson("/api/v1/communities/{$this->communityA->id}", [
            'name' => 'Hijacked',
        ], $this->token($this->managerB))->assertForbidden();

        $this->assertSame('Community A', $this->communityA->fresh()->name);
    }

    // -----------------------------------------------------------------------
    // Join (Resident)
    // -----------------------------------------------------------------------

    public function test_join_creates_pending_resident_for_resident_role(): void
    {
        $this->postJson("/api/v1/communities/{$this->communityA->id}/join", [
            'unit_id' => $this->unitA->id, 'residence_type' => 'owner',
        ], $this->token($this->residentUserA))
            ->assertStatus(202);

        $resident = Resident::where('user_id', $this->residentUserA->id)->first();
        $this->assertNotNull($resident);
        $this->assertSame('pending', $resident->status);
        $this->assertSame($this->communityA->id, $resident->community_id);
    }

    public function test_join_rejects_unit_from_another_community(): void
    {
        $otherUnit = \Modules\Community\app\Models\Unit::where('community_id', $this->communityB->id)->first();

        $this->postJson("/api/v1/communities/{$this->communityA->id}/join", [
            'unit_id' => $otherUnit->id, 'residence_type' => 'owner',
        ], $this->token($this->residentUserA))
            ->assertStatus(422);
    }

    public function test_join_requires_resident_role(): void
    {
        $this->postJson("/api/v1/communities/{$this->communityA->id}/join", [
            'unit_id' => $this->unitA->id, 'residence_type' => 'owner',
        ], $this->token($this->managerA))->assertForbidden();
    }

    // -----------------------------------------------------------------------
    // Approve (Manager)
    // -----------------------------------------------------------------------

    public function test_manager_can_approve_pending_resident(): void
    {
        $pending = Resident::create([
            'user_id' => $this->residentUserA->id, 'community_id' => $this->communityA->id,
            'unit_id' => $this->unitA->id, 'residence_type' => 'owner',
            'status' => 'pending', 'current_marker' => false,
        ]);

        $this->postJson("/api/v1/communities/{$this->communityA->id}/residents/{$pending->id}/approve", [], $this->token($this->managerA))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $this->assertSame('active', $pending->fresh()->status);
    }

    public function test_approve_rejects_resident_of_other_community(): void
    {
        $otherResident = Resident::create([
            'user_id' => $this->residentUserA->id, 'community_id' => $this->communityB->id,
            'unit_id' => \Modules\Community\app\Models\Unit::where('community_id', $this->communityB->id)->first()->id,
            'residence_type' => 'owner', 'status' => 'pending', 'current_marker' => false,
        ]);

        // Manager A tries to approve a resident of community B through community A's route.
        $this->postJson("/api/v1/communities/{$this->communityA->id}/residents/{$otherResident->id}/approve", [], $this->token($this->managerA))
            ->assertStatus(422);
    }

    public function test_manager_can_reject_pending_resident(): void
    {
        $pending = $this->makePendingResident();

        $this->postJson("/api/v1/communities/{$this->communityA->id}/residents/{$pending->id}/reject", [], $this->token($this->managerA))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertSame('rejected', $pending->fresh()->status);
    }

    public function test_manager_can_suspend_active_resident(): void
    {
        $active = Resident::create([
            'user_id' => $this->residentUserA->id, 'community_id' => $this->communityA->id,
            'unit_id' => $this->unitA->id, 'residence_type' => 'owner',
            'status' => 'active', 'current_marker' => true,
        ]);

        $this->postJson("/api/v1/communities/{$this->communityA->id}/residents/{$active->id}/suspend", [], $this->token($this->managerA))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'suspended');

        $this->assertSame('suspended', $active->fresh()->status);
    }

    // -----------------------------------------------------------------------
    // Destroy (Super Admin only)
    // -----------------------------------------------------------------------

    public function test_destroy_requires_super_admin(): void
    {
        $this->deleteJson("/api/v1/communities/{$this->communityA->id}", [], $this->token($this->managerA))
            ->assertForbidden();
        $this->assertDatabaseHas('communities', ['id' => $this->communityA->id]);

        $this->deleteJson("/api/v1/communities/{$this->communityA->id}", [], $this->token($this->superAdmin))
            ->assertStatus(200);

        $this->assertDatabaseMissing('communities', ['id' => $this->communityA->id]);
    }

    // -----------------------------------------------------------------------
    // Stats & Residents list (Manager)
    // -----------------------------------------------------------------------

    public function test_stats_requires_manager_or_super_admin(): void
    {
        $this->getJson("/api/v1/communities/{$this->communityA->id}/stats")->assertUnauthorized();
        $this->getJson("/api/v1/communities/{$this->communityA->id}/stats", $this->token($this->residentUserA))->assertForbidden();

        $this->getJson("/api/v1/communities/{$this->communityA->id}/stats", $this->token($this->managerA))
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_residents_list_requires_manager_of_community(): void
    {
        $this->getJson("/api/v1/communities/{$this->communityA->id}/residents", $this->token($this->residentUserA))->assertForbidden();
        // Manager of another community is rejected by the manager middleware.
        $this->getJson("/api/v1/communities/{$this->communityA->id}/residents", $this->token($this->managerB))->assertForbidden();

        $this->getJson("/api/v1/communities/{$this->communityA->id}/residents", $this->token($this->managerA))
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    private function makePendingResident(): Resident
    {
        return Resident::create([
            'user_id' => $this->residentUserA->id, 'community_id' => $this->communityA->id,
            'unit_id' => $this->unitA->id, 'residence_type' => 'owner',
            'status' => 'pending', 'current_marker' => false,
        ]);
    }

    // NOTE: GET /api/v1/residents/me is gated by can:view_my_residency, a
    // permission that RolePermissionSeeder never creates or grants, so every
    // resident receives 403. This is reported as a production defect in the
    // route matrix rather than asserted here (no intended contract to green-test).
}
