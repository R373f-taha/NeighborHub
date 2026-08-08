<?php

declare(strict_types=1);

namespace Tests\Feature\ServiceListings;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Modules\ServiceListing\app\Models\ServiceListing;
use Modules\ServiceListing\app\Services\ServiceListingService;
use PDO;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * SL-2 Final Hardening: (A) utf8mb4 description byte-safety, and
 * (B) active-residency TOCTOU race-hardening of owner write mutations.
 *
 * LOCAL ONLY / NOT staged.
 */
class ServiceListingHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Community $communityA;
    private Resident $ownerResident;
    private User $ownerUser;
    private ServiceListing $listing;
    private ServiceListingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->communityA = Community::create([
            'name' => 'Community A', 'city' => 'C', 'address' => 'A',
            'total_units' => 10, 'is_active' => true,
        ]);
        $unitA = Unit::create([
            'community_id' => $this->communityA->id, 'unit_number' => 'U1',
            'building_name' => 'B1', 'rooms' => 2, 'unit_type' => 'apartment', 'is_active' => true,
        ]);

        $this->ownerUser = User::factory()->resident()->create(['is_active' => true]);
        $this->ownerResident = Resident::create([
            'user_id' => $this->ownerUser->id, 'unit_id' => $unitA->id,
            'community_id' => $this->communityA->id, 'residence_type' => 'owner',
            'status' => 'active', 'current_marker' => true,
        ]);

        $this->listing = ServiceListing::factory()->create([
            'community_id' => $this->communityA->id,
            'resident_id' => $this->ownerResident->id,
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
            'Authorization' => 'Bearer ' . $user->createToken('hardening')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    /** @return array<string, mixed> */
    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Hardening Listing',
            'description' => 'A normal description.',
            'type' => 'sale',
            'price' => '10.00',
            'expires_at' => now()->addDays(7)->toIso8601String(),
        ], $overrides);
    }

    // ════════════ A. utf8mb4 DESCRIPTION BYTE-SAFETY ════════════

    public function test_bounded_multibyte_description_succeeds(): void
    {
        // 16000 x 4-byte emoji = 64000 bytes, the largest safe utf8mb4
        // payload that cannot overflow MySQL TEXT (65535 bytes).
        $desc = str_repeat("\u{1F600}", 16000);

        $response = $this->postJson(
            "/api/v1/communities/{$this->communityA->id}/service-listings",
            $this->storePayload(['description' => $desc]),
            $this->token($this->ownerUser)
        )->assertStatus(201);

        $row = ServiceListing::withTrashed()->find($response->json('data.id'));
        $this->assertSame(16000, mb_strlen($row->description), 'full multibyte description persisted');
        $this->assertSame(64000, strlen($row->description), 'byte count within TEXT limit');
    }

    public function test_oversized_multibyte_description_returns_422_and_never_reaches_mysql(): void
    {
        // 20000 x 4-byte emoji = 80000 bytes > 65535; with the old
        // character-based max:65000 this would pass validation and fail at
        // MySQL. max:16000 must reject it as 422 before persistence.
        $desc = str_repeat("\u{1F600}", 20000);

        $before = ServiceListing::count();

        $this->postJson(
            "/api/v1/communities/{$this->communityA->id}/service-listings",
            $this->storePayload(['description' => $desc]),
            $this->token($this->ownerUser)
        )->assertStatus(422)->assertJsonValidationErrors(['description']);

        $this->assertSame($before, ServiceListing::count(), 'no row inserted');
    }

    // ════════════ B. ACTIVE-RESIDENCY TOCTOU HARDENING ════════════

    public function test_active_owner_can_still_create(): void
    {
        $listing = $this->service->store($this->ownerUser, $this->communityA, $this->storePayload());

        $this->assertSame($this->communityA->id, (int) $listing->community_id);
        $this->assertSame($this->ownerResident->id, (int) $listing->resident_id);
        $this->assertSame('active', $listing->status);
    }

    public function test_store_rechecks_active_residency_at_mutation_boundary(): void
    {
        // Membership suspended (committed) before the authoritative
        // mutation boundary -> the service must reject under its lock and
        // insert nothing.
        $this->ownerResident->update(['status' => 'suspended']);

        $before = ServiceListing::count();

        try {
            $this->service->store($this->ownerUser, $this->communityA, $this->storePayload());
            $this->fail('Expected AuthorizationException for suspended residency.');
        } catch (AuthorizationException $e) {
            // expected 403-domain denial
        }

        $this->assertSame($before, ServiceListing::count(), 'no listing created under stale residency');
    }

    public function test_update_rechecks_active_residency_at_mutation_boundary(): void
    {
        $this->ownerResident->update(['status' => 'suspended']);
        $originalTitle = $this->listing->title;

        try {
            $this->service->update($this->ownerUser, $this->communityA, $this->listing, ['title' => 'Hijacked']);
            $this->fail('Expected AuthorizationException for suspended residency.');
        } catch (AuthorizationException $e) {
            // expected
        }

        $this->assertSame($originalTitle, $this->listing->fresh()->title, 'content not mutated under stale residency');
    }

    public function test_delete_rechecks_active_residency_at_mutation_boundary(): void
    {
        $this->ownerResident->update(['status' => 'suspended']);

        try {
            $this->service->delete($this->ownerUser, $this->communityA, $this->listing);
            $this->fail('Expected AuthorizationException for suspended residency.');
        } catch (AuthorizationException $e) {
            // expected
        }

        $this->assertNotNull(ServiceListing::find($this->listing->id), 'listing not soft-deleted under stale residency');
    }

    public function test_service_does_not_trust_a_stale_resident_model(): void
    {
        // Snapshot a resident model that is "active" in memory, then suspend
        // it through the database. The service re-resolves from the User
        // inside the transaction and must NOT trust the stale snapshot.
        $stale = Resident::find($this->ownerResident->id);
        $this->assertSame('active', $stale->status, 'snapshot is active');

        Resident::query()->where('id', $this->ownerResident->id)->update(['status' => 'suspended']);

        $this->assertSame('active', $stale->status, 'stale snapshot unchanged after DB suspension');

        $before = ServiceListing::count();
        try {
            $this->service->store($this->ownerUser, $this->communityA, $this->storePayload());
            $this->fail('Expected AuthorizationException despite stale active model.');
        } catch (AuthorizationException $e) {
            // expected
        }
        $this->assertSame($before, ServiceListing::count(), 'stale model did not authorize a create');
    }

    // ── FOR UPDATE locking probe (two raw independent MySQL connections) ──

    public function test_row_for_update_lock_serializes_a_concurrent_connection(): void
    {
        // Lock primitive proof under real MySQL, on a committed row visible
        // to both independent connections. The `migrations` table is
        // provisioned outside the per-test transaction, so its rows are
        // committed and visible to fresh connections.
        $cfg = config('database.connections.mysql');
        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset=utf8mb4";

        $lockRowId = DB::connection()->getPdo()
            ->query('SELECT id FROM migrations LIMIT 1')
            ->fetchColumn();

        if (! $lockRowId) {
            $this->markTestSkipped('No committed row available for FOR UPDATE probe.');
        }

        $holder = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $contender = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $holder->exec('SET SESSION innodb_lock_wait_timeout = 50');
        $contender->exec('SET SESSION innodb_lock_wait_timeout = 1');

        // Holder acquires the row lock.
        $holder->beginTransaction();
        $holder->query("SELECT id FROM migrations WHERE id = {$lockRowId} FOR UPDATE");

        // Contender must be serialized behind the holder's lock.
        $contended = false;
        try {
            $contender->beginTransaction();
            $contender->query("SELECT id FROM migrations WHERE id = {$lockRowId} FOR UPDATE");
        } catch (\Throwable $e) {
            $contended = str_contains($e->getMessage(), 'Lock wait timeout')
                || str_contains($e->getMessage(), '1205');
        }

        $holder->rollBack(); // release the lock

        try {
            $contender->rollBack();
        } catch (\Throwable $e) {
            // best effort cleanup
        }

        $this->assertTrue($contended, 'SELECT ... FOR UPDATE must serialize a concurrent connection');
    }
}
