<?php

declare(strict_types=1);

namespace Tests\Feature\ServiceListings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\CommunityManager;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Modules\ServiceListing\app\Models\ServiceListing;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * SL-2 Service Listings WRITE API: create/update/delete, ownership security,
 * IDOR, mass-assignment protection, status protection, soft delete.
 *
 * LOCAL ONLY / NOT staged.
 */
class ServiceListingWriteApiTest extends TestCase
{
    use RefreshDatabase;

    private Community $communityA;
    private Community $communityB;
    private Resident $ownerResident;
    private User $ownerUser;
    private User $secondUser; // active resident of A, NOT an owner
    private User $managerUser;
    private User $providerUser;
    private User $superAdmin;
    private User $outsider; // active resident of B
    private ServiceListing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->communityA = Community::create([
            'name' => 'Community A', 'city' => 'C', 'address' => 'A',
            'total_units' => 10, 'is_active' => true,
        ]);
        $this->communityB = Community::create([
            'name' => 'Community B', 'city' => 'C2', 'address' => 'A2',
            'total_units' => 5, 'is_active' => true,
        ]);

        $unitA = Unit::create([
            'community_id' => $this->communityA->id, 'unit_number' => 'U1',
            'building_name' => 'B1', 'rooms' => 2, 'unit_type' => 'apartment', 'is_active' => true,
        ]);
        $unitB = Unit::create([
            'community_id' => $this->communityB->id, 'unit_number' => 'U2',
            'building_name' => 'B2', 'rooms' => 2, 'unit_type' => 'apartment', 'is_active' => true,
        ]);

        $this->ownerUser = User::factory()->resident()->create(['is_active' => true]);
        $this->ownerResident = Resident::create([
            'user_id' => $this->ownerUser->id, 'unit_id' => $unitA->id,
            'community_id' => $this->communityA->id, 'residence_type' => 'owner',
            'status' => 'active', 'current_marker' => true,
        ]);

        $this->secondUser = User::factory()->resident()->create(['is_active' => true]);
        Resident::create([
            'user_id' => $this->secondUser->id, 'unit_id' => $unitA->id,
            'community_id' => $this->communityA->id, 'residence_type' => 'tenant',
            'status' => 'active', 'current_marker' => false,
        ]);

        $this->managerUser = User::factory()->manager()->create(['is_active' => true]);
        CommunityManager::create(['community_id' => $this->communityA->id, 'manager_id' => $this->managerUser->id]);

        $this->providerUser = User::factory()->provider()->create(['is_active' => true]);
        $this->superAdmin = User::factory()->superAdmin()->create(['is_active' => true]);

        $this->outsider = User::factory()->resident()->create(['is_active' => true]);
        Resident::create([
            'user_id' => $this->outsider->id, 'unit_id' => $unitB->id,
            'community_id' => $this->communityB->id, 'residence_type' => 'tenant',
            'status' => 'active', 'current_marker' => true,
        ]);

        $this->listing = ServiceListing::factory()->create([
            'community_id' => $this->communityA->id,
            'resident_id' => $this->ownerResident->id,
        ]);
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if ($this->app && $this->app->bound('auth')) {
            $this->app['auth']->forgetGuards();
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /** @return array<string, string> */
    private function token(User $user): array
    {
        return [
            'Authorization' => 'Bearer ' . $user->createToken('sl2-test')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    /** @return array<string, mixed> */
    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Test Listing',
            'description' => 'A description for the listing.',
            'type' => 'sale',
            'price' => '99.99',
            'expires_at' => now()->addDays(7)->toIso8601String(),
        ], $overrides);
    }

    private function indexUri(Community $community): string
    {
        return "/api/v1/communities/{$community->id}/service-listings";
    }

    private function showUri(Community $community, ServiceListing|int $listing): string
    {
        $id = $listing instanceof ServiceListing ? $listing->id : $listing;

        return "/api/v1/communities/{$community->id}/service-listings/{$id}";
    }

    // ════════════ CREATE ════════════

    public function test_anonymous_create_is_unauthenticated(): void
    {
        $this->postJson($this->indexUri($this->communityA), $this->storePayload())->assertStatus(401);
    }

    public function test_active_resident_can_create(): void
    {
        $this->postJson($this->indexUri($this->communityA), $this->storePayload(), $this->token($this->ownerUser))
            ->assertStatus(201)
            ->assertJsonPath('message', 'Service listing created successfully.')
            ->assertJsonPath('data.community_id', $this->communityA->id)
            ->assertJsonPath('data.author.id', $this->ownerResident->id);
    }

    public function test_outsider_cannot_create(): void
    {
        $this->postJson($this->indexUri($this->communityA), $this->storePayload(), $this->token($this->outsider))
            ->assertStatus(403);
    }

    public function test_provider_without_active_residency_cannot_create(): void
    {
        $this->postJson($this->indexUri($this->communityA), $this->storePayload(), $this->token($this->providerUser))
            ->assertStatus(403);
    }

    public function test_manager_without_active_residency_cannot_create(): void
    {
        $this->postJson($this->indexUri($this->communityA), $this->storePayload(), $this->token($this->managerUser))
            ->assertStatus(403);
    }

    public function test_super_admin_without_active_residency_cannot_create(): void
    {
        $this->postJson($this->indexUri($this->communityA), $this->storePayload(), $this->token($this->superAdmin))
            ->assertStatus(403);
    }

    public function test_suspended_resident_cannot_create(): void
    {
        $this->ownerResident->update(['status' => 'suspended']);

        $this->postJson($this->indexUri($this->communityA), $this->storePayload(), $this->token($this->ownerUser))
            ->assertStatus(403);
    }

    public function test_create_assigns_server_authoritative_values(): void
    {
        $response = $this->postJson($this->indexUri($this->communityA), $this->storePayload(), $this->token($this->ownerUser))
            ->assertStatus(201);

        $id = $response->json('data.id');
        $row = ServiceListing::withTrashed()->find($id);

        $this->assertSame($this->communityA->id, (int) $row->community_id, 'community_id from route');
        $this->assertSame($this->ownerResident->id, (int) $row->resident_id, 'resident_id from active resident');
        $this->assertSame('active', $row->status, 'status forced active');
        $this->assertNull($row->closed_at, 'closed_at null');
    }

    public function test_create_rejects_invalid_type(): void
    {
        $this->postJson($this->indexUri($this->communityA), $this->storePayload(['type' => 'auction']), $this->token($this->ownerUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_requires_fields(): void
    {
        $this->postJson($this->indexUri($this->communityA), [], $this->token($this->ownerUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'description', 'type', 'expires_at']);
    }

    public function test_create_rejects_past_expires_at(): void
    {
        $this->postJson($this->indexUri($this->communityA), $this->storePayload(['expires_at' => now()->subDay()->toIso8601String()]), $this->token($this->ownerUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['expires_at']);
    }

    public function test_create_rejects_invalid_price(): void
    {
        $this->postJson($this->indexUri($this->communityA), $this->storePayload(['price' => '-5']), $this->token($this->ownerUser))
            ->assertStatus(422)->assertJsonValidationErrors(['price']);

        $this->postJson($this->indexUri($this->communityA), $this->storePayload(['price' => '99999999999999']), $this->token($this->ownerUser))
            ->assertStatus(422)->assertJsonValidationErrors(['price']);
    }

    public function test_create_ignores_malicious_resident_id(): void
    {
        // Client attempts to assign a foreign resident_id; server must
        // ignore it and assign the authenticated user's active resident.
        $response = $this->postJson($this->indexUri($this->communityA), $this->storePayload(['resident_id' => 999999]), $this->token($this->ownerUser))
            ->assertStatus(201);

        $this->assertSame($this->ownerResident->id, (int) ServiceListing::withTrashed()->find($response->json('data.id'))->resident_id);
    }

    public function test_create_ignores_malicious_community_id(): void
    {
        $response = $this->postJson($this->indexUri($this->communityA), $this->storePayload(['community_id' => $this->communityB->id]), $this->token($this->ownerUser))
            ->assertStatus(201);

        $this->assertSame($this->communityA->id, (int) ServiceListing::withTrashed()->find($response->json('data.id'))->community_id);
    }

    public function test_create_ignores_malicious_status(): void
    {
        foreach (['reserved', 'closed'] as $bad) {
            $response = $this->postJson($this->indexUri($this->communityA), $this->storePayload(['status' => $bad]), $this->token($this->ownerUser))
                ->assertStatus(201);

            $this->assertSame('active', ServiceListing::withTrashed()->find($response->json('data.id'))->status, "status must stay active despite $bad");
        }
    }

    public function test_create_does_not_leak_sensitive_fields(): void
    {
        $json = $this->postJson($this->indexUri($this->communityA), $this->storePayload(), $this->token($this->ownerUser))
            ->assertStatus(201)->getContent();

        foreach (['password', 'email', 'phone', 'resident_id', 'remember_token'] as $secret) {
            $this->assertStringNotContainsString($secret, $json, "response must not leak $secret");
        }
    }

    // ════════════ UPDATE ════════════

    public function test_owner_can_update(): void
    {
        $this->patchJson($this->showUri($this->communityA, $this->listing), ['title' => 'Updated Title'], $this->token($this->ownerUser))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Service listing updated successfully.')
            ->assertJsonPath('data.title', 'Updated Title');
    }

    public function test_other_active_resident_same_community_cannot_update(): void
    {
        $this->patchJson($this->showUri($this->communityA, $this->listing), ['title' => 'Hijack'], $this->token($this->secondUser))
            ->assertStatus(403);
    }

    public function test_suspended_owner_cannot_update(): void
    {
        $this->ownerResident->update(['status' => 'suspended']);

        $this->patchJson($this->showUri($this->communityA, $this->listing), ['title' => 'X'], $this->token($this->ownerUser))
            ->assertStatus(403);
    }

    public function test_manager_non_owner_cannot_update(): void
    {
        $this->patchJson($this->showUri($this->communityA, $this->listing), ['title' => 'X'], $this->token($this->managerUser))
            ->assertStatus(403);
    }

    public function test_provider_non_owner_cannot_update(): void
    {
        $this->patchJson($this->showUri($this->communityA, $this->listing), ['title' => 'X'], $this->token($this->providerUser))
            ->assertStatus(403);
    }

    public function test_super_admin_non_owner_cannot_update(): void
    {
        $this->patchJson($this->showUri($this->communityA, $this->listing), ['title' => 'X'], $this->token($this->superAdmin))
            ->assertStatus(403);
    }

    public function test_update_foreign_community_path_is_404(): void
    {
        // Listing belongs to A; attempt update through B's route. Scoped
        // binding fails before the policy is consulted -> 404.
        $this->patchJson($this->showUri($this->communityB, $this->listing), ['title' => 'X'], $this->token($this->ownerUser))
            ->assertStatus(404);
    }

    public function test_update_missing_listing_is_404(): void
    {
        $this->patchJson($this->showUri($this->communityA, 999999), ['title' => 'X'], $this->token($this->ownerUser))
            ->assertStatus(404);
    }

    public function test_update_soft_deleted_listing_is_404(): void
    {
        $this->listing->delete();

        $this->patchJson($this->showUri($this->communityA, $this->listing), ['title' => 'X'], $this->token($this->ownerUser))
            ->assertStatus(404);
    }

    public function test_update_changes_content_fields(): void
    {
        $this->patchJson($this->showUri($this->communityA, $this->listing), [
            'title' => 'New Title',
            'description' => 'New desc',
            'type' => 'rent',
            'price' => '12.50',
        ], $this->token($this->ownerUser))
            ->assertStatus(200);

        $row = $this->listing->fresh();
        $this->assertSame('New Title', $row->title);
        $this->assertSame('New desc', $row->description);
        $this->assertSame('rent', $row->type);
        $this->assertSame('12.50', (string) $row->price);
    }

    public function test_update_cannot_change_authority_fields(): void
    {
        $originalCommunity = (int) $this->listing->community_id;
        $originalResident = (int) $this->listing->resident_id;
        $originalStatus = $this->listing->status;
        $originalClosedAt = $this->listing->closed_at;

        $this->patchJson($this->showUri($this->communityA, $this->listing), [
            'community_id' => $this->communityB->id,
            'resident_id' => 999999,
            'status' => 'closed',
            'closed_at' => now()->toIso8601String(),
            'deleted_at' => now()->toIso8601String(),
            'title' => 'Valid change',
        ], $this->token($this->ownerUser))
            ->assertStatus(200);

        $row = $this->listing->fresh();
        $this->assertSame($originalCommunity, (int) $row->community_id, 'community_id immutable');
        $this->assertSame($originalResident, (int) $row->resident_id, 'resident_id immutable');
        $this->assertSame($originalStatus, $row->status, 'status immutable');
        $this->assertSame($originalClosedAt, $row->closed_at ? $row->closed_at->toJson() : null, 'closed_at immutable');
        $this->assertSame('Valid change', $row->title, 'content still updated');
    }

    public function test_update_rejects_invalid_data(): void
    {
        $this->patchJson($this->showUri($this->communityA, $this->listing), ['type' => 'auction'], $this->token($this->ownerUser))
            ->assertStatus(422)->assertJsonValidationErrors(['type']);

        $this->patchJson($this->showUri($this->communityA, $this->listing), ['expires_at' => now()->subDay()->toIso8601String()], $this->token($this->ownerUser))
            ->assertStatus(422)->assertJsonValidationErrors(['expires_at']);
    }

    public function test_update_response_does_not_leak_sensitive_fields(): void
    {
        $json = $this->patchJson($this->showUri($this->communityA, $this->listing), ['title' => 'X'], $this->token($this->ownerUser))
            ->assertStatus(200)->getContent();

        foreach (['password', 'email', 'phone', 'resident_id', 'remember_token'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
    }

    // ════════════ DELETE ════════════

    public function test_owner_can_delete(): void
    {
        $this->deleteJson($this->showUri($this->communityA, $this->listing), [], $this->token($this->ownerUser))
            ->assertStatus(200)
            ->assertJson(['message' => 'Service listing deleted successfully.', 'data' => null]);
    }

    public function test_delete_soft_deletes_row(): void
    {
        $this->deleteJson($this->showUri($this->communityA, $this->listing), [], $this->token($this->ownerUser))
            ->assertStatus(200);

        $this->assertNull(ServiceListing::find($this->listing->id), 'excluded from default scope');
        $this->assertNotNull(ServiceListing::withTrashed()->find($this->listing->id), 'row retained (soft)');
        $this->assertNotNull(ServiceListing::withTrashed()->find($this->listing->id)->deleted_at);
    }

    public function test_deleted_listing_absent_from_index_and_show(): void
    {
        $this->deleteJson($this->showUri($this->communityA, $this->listing), [], $this->token($this->ownerUser))
            ->assertStatus(200);

        $ids = collect($this->getJson($this->indexUri($this->communityA), $this->token($this->ownerUser))->assertStatus(200)->json('data'))
            ->pluck('id');

        $this->assertNotContains($this->listing->id, $ids);
        $this->getJson($this->showUri($this->communityA, $this->listing), $this->token($this->ownerUser))->assertStatus(404);
    }

    public function test_second_delete_is_404(): void
    {
        $this->deleteJson($this->showUri($this->communityA, $this->listing), [], $this->token($this->ownerUser))->assertStatus(200);

        $this->deleteJson($this->showUri($this->communityA, $this->listing), [], $this->token($this->ownerUser))->assertStatus(404);
    }

    public function test_other_resident_cannot_delete(): void
    {
        $this->deleteJson($this->showUri($this->communityA, $this->listing), [], $this->token($this->secondUser))->assertStatus(403);
    }

    public function test_suspended_owner_cannot_delete(): void
    {
        $this->ownerResident->update(['status' => 'suspended']);

        $this->deleteJson($this->showUri($this->communityA, $this->listing), [], $this->token($this->ownerUser))->assertStatus(403);
    }

    public function test_manager_non_owner_cannot_delete(): void
    {
        $this->deleteJson($this->showUri($this->communityA, $this->listing), [], $this->token($this->managerUser))->assertStatus(403);
    }

    public function test_super_admin_non_owner_cannot_delete(): void
    {
        $this->deleteJson($this->showUri($this->communityA, $this->listing), [], $this->token($this->superAdmin))->assertStatus(403);
    }

    public function test_delete_foreign_community_path_is_404(): void
    {
        // Listing belongs to A; attempt delete through B's route.
        $this->deleteJson($this->showUri($this->communityB, $this->listing), [], $this->token($this->ownerUser))->assertStatus(404);
    }

    public function test_anonymous_delete_is_unauthenticated(): void
    {
        $this->deleteJson($this->showUri($this->communityA, $this->listing))->assertStatus(401);
    }

    public function test_anonymous_update_is_unauthenticated(): void
    {
        $this->patchJson($this->showUri($this->communityA, $this->listing), ['title' => 'X'])->assertStatus(401);
    }
}
