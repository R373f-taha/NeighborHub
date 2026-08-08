<?php

declare(strict_types=1);

namespace Tests\Feature\ServiceListings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\CommunityManager;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Modules\ServiceListing\app\Exceptions\InvalidServiceListingStatusTransitionException;
use Modules\ServiceListing\app\Models\ServiceListing;
use Modules\ServiceListing\app\Services\ServiceListingService;
use Monolog\Handler\NullHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PDO;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * SL-3 Service Listing Status Transitions & Moderation.
 *
 * LOCAL ONLY / NOT staged.
 */
class ServiceListingStatusApiTest extends TestCase
{
    use RefreshDatabase;

    private Community $communityA;
    private Community $communityB;
    private Resident $ownerResident;
    private User $ownerUser;
    private User $secondUser;
    private User $managerUser;
    private User $providerUser;
    private User $superAdmin;
    private User $outsider;
    private ServiceListingService $service;

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
        Unit::create([
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

        $unitB = Unit::where('community_id', $this->communityB->id)->first();
        $this->outsider = User::factory()->resident()->create(['is_active' => true]);
        Resident::create([
            'user_id' => $this->outsider->id, 'unit_id' => $unitB->id,
            'community_id' => $this->communityB->id, 'residence_type' => 'tenant',
            'status' => 'active', 'current_marker' => true,
        ]);

        $this->service = app(ServiceListingService::class);
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
            'Authorization' => 'Bearer ' . $user->createToken('sl3-test')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    private function listingAt(string $status, ?User $owner = null): ServiceListing
    {
        return ServiceListing::factory()->create([
            'community_id' => $this->communityA->id,
            'resident_id' => $owner === null ? $this->ownerResident->id : Resident::where('user_id', $owner->id)->where('community_id', $this->communityA->id)->value('id'),
            'status' => $status,
            'closed_at' => $status === 'closed' ? now() : null,
        ]);
    }

    private function statusUri(Community $community, ServiceListing|int $listing): string
    {
        $id = $listing instanceof ServiceListing ? $listing->id : $listing;

        return "/api/v1/communities/{$community->id}/service-listings/{$id}/status";
    }

    // ════════════ AUTH ════════════

    public function test_anonymous_status_update_is_unauthenticated(): void
    {
        $this->patchJson($this->statusUri($this->communityA, $this->listingAt('active')), ['status' => 'reserved'])
            ->assertStatus(401);
    }

    public function test_inactive_user_is_rejected_by_middleware(): void
    {
        // is_active is intentionally not mass-assignable; update it directly.
        DB::table('users')->where('id', $this->ownerUser->id)->update(['is_active' => false]);

        $this->patchJson($this->statusUri($this->communityA, $this->listingAt('active')), ['status' => 'reserved'], $this->token($this->ownerUser))
            ->assertStatus(403);
    }

    // ════════════ OWNER TRANSITIONS ════════════

    public function test_owner_active_to_reserved(): void
    {
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'reserved'], $this->token($this->ownerUser))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Service listing status updated successfully.')
            ->assertJsonPath('data.status', 'reserved')
            ->assertJsonPath('data.closed_at', null);
    }

    public function test_owner_active_to_closed(): void
    {
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'closed'], $this->token($this->ownerUser))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_owner_reserved_to_active(): void
    {
        $l = $this->listingAt('reserved');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'active'], $this->token($this->ownerUser))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_owner_reserved_to_closed(): void
    {
        $l = $this->listingAt('reserved');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'closed'], $this->token($this->ownerUser))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_owner_closed_to_active_is_422(): void
    {
        $l = $this->listingAt('closed');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'active'], $this->token($this->ownerUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_owner_closed_to_reserved_is_422(): void
    {
        $l = $this->listingAt('closed');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'reserved'], $this->token($this->ownerUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_suspended_owner_is_forbidden(): void
    {
        $this->ownerResident->update(['status' => 'suspended']);
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'reserved'], $this->token($this->ownerUser))
            ->assertStatus(403);
    }

    public function test_non_owner_resident_is_forbidden(): void
    {
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'reserved'], $this->token($this->secondUser))
            ->assertStatus(403);
    }

    // ════════════ STATUS DATA / INVARIANTS ════════════

    public function test_close_sets_closed_at(): void
    {
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'closed'], $this->token($this->ownerUser))
            ->assertStatus(200);

        $row = $l->fresh();
        $this->assertSame('closed', $row->status);
        $this->assertNotNull($row->closed_at, 'close sets closed_at');
    }

    public function test_reserve_keeps_closed_at_null(): void
    {
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'reserved'], $this->token($this->ownerUser))
            ->assertStatus(200);

        $this->assertNull($l->fresh()->closed_at);
    }

    public function test_reserved_to_active_keeps_closed_at_null(): void
    {
        $l = $this->listingAt('reserved');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'active'], $this->token($this->ownerUser))
            ->assertStatus(200);

        $row = $l->fresh();
        $this->assertSame('active', $row->status);
        $this->assertNull($row->closed_at);
    }

    public function test_client_cannot_supply_closed_at(): void
    {
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), [
            'status' => 'reserved',
            'closed_at' => now()->addDay()->toIso8601String(),
        ], $this->token($this->ownerUser))
            ->assertStatus(200);

        $this->assertNull($l->fresh()->closed_at, 'client-supplied closed_at must be ignored');
    }

    public function test_only_status_and_closed_at_change_through_status_endpoint(): void
    {
        $l = $this->listingAt('active');
        $originalTitle = $l->title;
        $originalDescription = $l->description;
        $originalType = $l->type;
        $originalPrice = (string) $l->price;
        $originalResident = (int) $l->resident_id;
        $originalCommunity = (int) $l->community_id;

        $this->patchJson($this->statusUri($this->communityA, $l), [
            'status' => 'reserved',
            'title' => 'Hijacked',
            'description' => 'Hijacked',
            'type' => 'rent',
            'price' => '1.00',
            'resident_id' => 999999,
            'community_id' => $this->communityB->id,
        ], $this->token($this->ownerUser))
            ->assertStatus(200);

        $row = $l->fresh();
        $this->assertSame('reserved', $row->status, 'status changed');
        $this->assertSame($originalTitle, $row->title, 'title immutable via status endpoint');
        $this->assertSame($originalDescription, $row->description, 'description immutable');
        $this->assertSame($originalType, $row->type, 'type immutable');
        $this->assertSame($originalPrice, (string) $row->price, 'price immutable');
        $this->assertSame($originalResident, (int) $row->resident_id, 'resident_id immutable');
        $this->assertSame($originalCommunity, (int) $row->community_id, 'community_id immutable');
    }

    // ════════════ MANAGER MODERATION ════════════

    public function test_manager_can_close_active(): void
    {
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'closed'], $this->token($this->managerUser))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_manager_can_close_reserved(): void
    {
        $l = $this->listingAt('reserved');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'closed'], $this->token($this->managerUser))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_manager_cannot_reserve_active(): void
    {
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'reserved'], $this->token($this->managerUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_manager_cannot_reactivate_reserved(): void
    {
        $l = $this->listingAt('reserved');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'active'], $this->token($this->managerUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_manager_of_another_community_is_forbidden(): void
    {
        $otherManager = User::factory()->manager()->create(['is_active' => true]);
        CommunityManager::create(['community_id' => $this->communityB->id, 'manager_id' => $otherManager->id]);
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'closed'], $this->token($otherManager))
            ->assertStatus(403);
    }

    public function test_enum_only_manager_without_spatie_role_is_denied(): void
    {
        // users.role says manager but Spatie has no manager role -> drift must
        // not grant moderation. Pivot is present to isolate the failure to the
        // missing Spatie role.
        $enumOnly = User::factory()->manager()->create(['is_active' => true]);
        $enumOnly->removeRole('manager');
        CommunityManager::create(['community_id' => $this->communityA->id, 'manager_id' => $enumOnly->id]);
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'closed'], $this->token($enumOnly))
            ->assertStatus(403);
    }

    public function test_spatie_manager_without_community_pivot_is_denied(): void
    {
        $noPivot = User::factory()->manager()->create(['is_active' => true]);
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'closed'], $this->token($noPivot))
            ->assertStatus(403);
    }

    // ════════════ SUPER ADMIN MODERATION ════════════

    public function test_super_admin_can_close_active(): void
    {
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'closed'], $this->token($this->superAdmin))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_super_admin_can_close_reserved(): void
    {
        $l = $this->listingAt('reserved');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'closed'], $this->token($this->superAdmin))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_super_admin_cannot_reserve_active(): void
    {
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'reserved'], $this->token($this->superAdmin))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_super_admin_cannot_reactivate_reserved(): void
    {
        $l = $this->listingAt('reserved');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'active'], $this->token($this->superAdmin))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_users_role_drift_does_not_alter_super_admin_decision(): void
    {
        // Spatie still says super_admin; the legacy mirror says resident.
        DB::table('users')->where('id', $this->superAdmin->id)->update(['role' => 'resident']);
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'closed'], $this->token($this->superAdmin))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_users_role_drift_does_not_grant_manager_power(): void
    {
        // A plain resident whose users.role is faked to 'manager' must NOT gain
        // moderation; Spatie is authoritative.
        $drift = User::factory()->resident()->create(['is_active' => true]);
        DB::table('users')->where('id', $drift->id)->update(['role' => 'manager']);
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'closed'], $this->token($drift))
            ->assertStatus(403);
    }

    // ════════════ PROVIDER ════════════

    public function test_provider_non_owner_has_no_moderation_privilege(): void
    {
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'closed'], $this->token($this->providerUser))
            ->assertStatus(403);
    }

    public function test_provider_global_role_does_not_enable_reserve(): void
    {
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'reserved'], $this->token($this->providerUser))
            ->assertStatus(403);
    }

    // ════════════ IDOR ════════════

    public function test_wrong_community_path_is_404(): void
    {
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityB, $l), ['status' => 'reserved'], $this->token($this->ownerUser))
            ->assertStatus(404);
    }

    public function test_missing_listing_is_404(): void
    {
        $this->patchJson($this->statusUri($this->communityA, 999999), ['status' => 'reserved'], $this->token($this->ownerUser))
            ->assertStatus(404);
    }

    public function test_soft_deleted_listing_is_404(): void
    {
        $l = $this->listingAt('active');
        $l->delete();

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'reserved'], $this->token($this->ownerUser))
            ->assertStatus(404);
    }

    // ════════════ IDEMPOTENCY ════════════

    public function test_owner_active_to_active_is_idempotent(): void
    {
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'active'], $this->token($this->ownerUser))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_owner_reserved_to_reserved_is_idempotent(): void
    {
        $l = $this->listingAt('reserved');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'reserved'], $this->token($this->ownerUser))
            ->assertStatus(200);
    }

    public function test_owner_closed_to_closed_is_idempotent(): void
    {
        $l = $this->listingAt('closed');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'closed'], $this->token($this->ownerUser))
            ->assertStatus(200);
    }

    public function test_moderator_closed_to_closed_is_idempotent(): void
    {
        $l = $this->listingAt('closed');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'closed'], $this->token($this->managerUser))
            ->assertStatus(200);
    }

    public function test_manager_active_to_active_must_not_succeed_merely_because_unchanged(): void
    {
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'active'], $this->token($this->managerUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_manager_reserved_to_reserved_is_denied(): void
    {
        $l = $this->listingAt('reserved');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'reserved'], $this->token($this->managerUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    // ════════════ VALIDATION ════════════

    public function test_invalid_status_value_is_422(): void
    {
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'draft'], $this->token($this->ownerUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_missing_status_is_422(): void
    {
        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), [], $this->token($this->ownerUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    // ════════════ PRIVACY ════════════

    public function test_status_response_does_not_leak_sensitive_fields(): void
    {
        $l = $this->listingAt('active');

        $json = $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'reserved'], $this->token($this->ownerUser))
            ->assertStatus(200)->getContent();

        foreach (['password', 'email', 'phone', 'resident_id', 'remember_token'] as $secret) {
            $this->assertStringNotContainsString($secret, $json, "response must not leak $secret");
        }
    }

    // ════════════ SECURITY LOGGING ════════════

    public function test_status_update_emits_safe_security_log(): void
    {
        $handler = new TestHandler();
        $logger = Log::channel('security')->getLogger();
        $original = $logger instanceof Logger ? $logger->getHandlers() : [];
        if ($logger instanceof Logger) {
            $logger->setHandlers([$handler]);
        }

        $l = $this->listingAt('active');

        $this->patchJson($this->statusUri($this->communityA, $l), ['status' => 'closed'], $this->token($this->ownerUser))
            ->assertStatus(200);

        if ($logger instanceof Logger) {
            $logger->setHandlers($original ?: [new NullHandler()]);
        }

        $found = false;
        foreach ($handler->getRecords() as $record) {
            if (str_contains($record['message'] ?? '', 'service_listing.status_updated')) {
                $found = true;
                $ctx = $record['context'];
                $this->assertSame($this->ownerUser->id, $ctx['actor_user_id']);
                $this->assertSame($this->communityA->id, $ctx['community_id']);
                $this->assertSame('active', $ctx['old_status']);
                $this->assertSame('closed', $ctx['new_status']);
                $this->assertSame('success', $ctx['result']);
                $this->assertArrayNotHasKey('password', $ctx);
                $this->assertArrayNotHasKey('token', $ctx);
                $this->assertArrayNotHasKey('Authorization', $ctx);
                $this->assertArrayNotHasKey('body', $ctx);
                $this->assertArrayNotHasKey('description', $ctx);
            }
        }
        $this->assertTrue($found, 'service_listing.status_updated security log not emitted.');
    }

    // ════════════ CONCURRENCY: BUSINESS (serialized orders) ════════════

    public function test_race_reserve_then_close_serializes_to_closed(): void
    {
        $l = $this->listingAt('active');

        $this->service->updateStatus($this->ownerUser, $this->communityA, $l, 'reserved');
        $this->assertSame('reserved', $l->fresh()->status);

        $this->service->updateStatus($this->ownerUser, $this->communityA, $l->fresh(), 'closed');

        $final = $l->fresh();
        $this->assertSame('closed', $final->status, 'final legal serialized state is closed');
        $this->assertNotNull($final->closed_at);
    }

    public function test_race_close_then_reserve_refuses_terminal_state(): void
    {
        $l = $this->listingAt('active');

        $this->service->updateStatus($this->ownerUser, $this->communityA, $l, 'closed');

        try {
            $this->service->updateStatus($this->ownerUser, $this->communityA, $l->fresh(), 'reserved');
            $this->fail('Expected invalid transition: closed is terminal.');
        } catch (InvalidServiceListingStatusTransitionException $e) {
            $this->assertSame('closed', $e->from());
            $this->assertSame('reserved', $e->to());
        }

        $this->assertSame('closed', $l->fresh()->status, 'terminal state preserved');
    }

    public function test_duplicate_reserve_is_safe_and_idempotent(): void
    {
        $l = $this->listingAt('active');

        $this->service->updateStatus($this->ownerUser, $this->communityA, $l, 'reserved');
        $result = $this->service->updateStatus($this->ownerUser, $this->communityA, $l->fresh(), 'reserved');

        $final = $l->fresh();
        $this->assertSame('reserved', $result->status);
        $this->assertSame('reserved', $final->status, 'no corruption on duplicate reserve');
        $this->assertNull($final->closed_at, 'closed_at invariant holds');
        $this->assertSame(1, ServiceListing::whereKey($l->id)->count(), 'exactly one row, no duplicate writes');
    }

    public function test_moderator_close_after_owner_reserve_ends_closed(): void
    {
        $l = $this->listingAt('active');

        $this->service->updateStatus($this->ownerUser, $this->communityA, $l, 'reserved');
        $this->service->updateStatus($this->managerUser, $this->communityA, $l->fresh(), 'closed');

        $this->assertSame('closed', $l->fresh()->status);
    }

    public function test_owner_reserve_after_moderator_close_is_refused(): void
    {
        $l = $this->listingAt('active');

        $this->service->updateStatus($this->managerUser, $this->communityA, $l, 'closed');

        try {
            $this->service->updateStatus($this->ownerUser, $this->communityA, $l->fresh(), 'reserved');
            $this->fail('Expected refusal: listing already closed by moderator.');
        } catch (InvalidServiceListingStatusTransitionException $e) {
            // expected 422
        }

        $this->assertSame('closed', $l->fresh()->status);
    }

    public function test_status_update_cannot_resurrect_soft_deleted_listing(): void
    {
        $l = $this->listingAt('active');
        $l->delete();

        $threw = false;
        try {
            $this->service->updateStatus($this->ownerUser, $this->communityA, $l, 'reserved');
        } catch (HttpException $e) {
            $threw = $e->getStatusCode() === 404;
        }
        $this->assertTrue($threw, 'Soft-deleted listing must 404 and not be mutated.');

        $persisted = ServiceListing::withTrashed()->find($l->id);
        $this->assertNotNull($persisted->deleted_at, 'row remains soft-deleted');
        $this->assertSame('active', $persisted->status, 'status untouched on a soft-deleted row');
        $this->assertNull(ServiceListing::find($l->id), 'not resurrected into the default scope');
    }

    // ════════════ CONCURRENCY: TWO-CONNECTION FOR UPDATE EVIDENCE ════════════

    public function test_service_listing_row_for_update_serializes_a_concurrent_connection(): void
    {
        $cfg = config('database.connections.mysql');
        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset=utf8mb4";
        $rowId = 777777;

        $setup = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $setup->exec('SET SESSION innodb_lock_wait_timeout = 5');
        $setup->exec('SET FOREIGN_KEY_CHECKS=0');
        $setup->exec("DELETE FROM service_listings WHERE id = {$rowId}");
        $setup->exec("INSERT INTO service_listings (id, community_id, resident_id, title, description, type, price, status, expires_at, closed_at, created_at, updated_at) VALUES ({$rowId}, 1, 1, 'Lock probe', 'probe', 'sale', 1.00, 'active', '2030-01-01 00:00:00', NULL, NOW(), NOW())");
        $setup->exec('SET FOREIGN_KEY_CHECKS=1');
        $setup = null;

        $holder = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $contender = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $holder->exec('SET SESSION innodb_lock_wait_timeout = 50');
        $contender->exec('SET SESSION innodb_lock_wait_timeout = 1');

        $contended = false;
        try {
            $holder->beginTransaction();
            $holder->query("SELECT id FROM service_listings WHERE id = {$rowId} FOR UPDATE");

            $contender->beginTransaction();
            $contender->query("SELECT id FROM service_listings WHERE id = {$rowId} FOR UPDATE");
        } catch (\Throwable $e) {
            $contended = str_contains($e->getMessage(), 'Lock wait timeout')
                || str_contains($e->getMessage(), '1205');
        } finally {
            try {
                $holder->rollBack();
            } catch (\Throwable $e) {
            }
            try {
                $contender->rollBack();
            } catch (\Throwable $e) {
            }
            $cleanup = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $cleanup->exec("DELETE FROM service_listings WHERE id = {$rowId}");
        }

        $this->assertTrue($contended, 'SELECT ... FOR UPDATE on a service_listings row must serialize a concurrent connection.');
    }
}
