<?php

declare(strict_types=1);

namespace Tests\Feature\ServiceListings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\CommunityManager;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Modules\ServiceListing\app\Models\ServiceListing;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * SL-1 Service Listings READ API: routing, authorization, IDOR, data
 * contract, pagination and deterministic ordering.
 *
 * LOCAL ONLY / NOT staged.
 */
class ServiceListingReadApiTest extends TestCase
{
    use RefreshDatabase;

    private Community $communityA;
    private Community $communityB;
    private Resident $residentA;
    private User $residentUserA;
    private User $managerUserA;
    private User $managerUserB;
    private User $superAdmin;
    private User $outsider;
    private ServiceListing $listingA;

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

        $this->residentUserA = User::factory()->resident()->create(['is_active' => true]);
        $this->residentA = Resident::create([
            'user_id' => $this->residentUserA->id, 'unit_id' => $unitA->id,
            'community_id' => $this->communityA->id, 'residence_type' => 'owner',
            'status' => 'active', 'current_marker' => true,
        ]);

        $this->managerUserA = User::factory()->manager()->create(['is_active' => true]);
        CommunityManager::create(['community_id' => $this->communityA->id, 'manager_id' => $this->managerUserA->id]);

        $this->managerUserB = User::factory()->manager()->create(['is_active' => true]);
        CommunityManager::create(['community_id' => $this->communityB->id, 'manager_id' => $this->managerUserB->id]);

        $this->superAdmin = User::factory()->superAdmin()->create(['is_active' => true]);

        // Outsider: active resident of B, no access to A.
        $this->outsider = User::factory()->resident()->create(['is_active' => true]);
        Resident::create([
            'user_id' => $this->outsider->id, 'unit_id' => $unitB->id,
            'community_id' => $this->communityB->id, 'residence_type' => 'tenant',
            'status' => 'active', 'current_marker' => true,
        ]);

        $this->listingA = ServiceListing::factory()->create([
            'community_id' => $this->communityA->id,
            'resident_id' => $this->residentA->id,
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
            'Authorization' => 'Bearer ' . $user->createToken('sl-test')->plainTextToken,
            'Accept' => 'application/json',
        ];
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

    // ── Routing / Auth ──

    public function test_anonymous_index_is_unauthenticated(): void
    {
        $this->getJson($this->indexUri($this->communityA))->assertStatus(401);
    }

    public function test_anonymous_show_is_unauthenticated(): void
    {
        $this->getJson($this->showUri($this->communityA, $this->listingA))->assertStatus(401);
    }

    // ── Access ──

    public function test_active_resident_of_same_community_can_index(): void
    {
        $this->getJson($this->indexUri($this->communityA), $this->token($this->residentUserA))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Service listings retrieved successfully.')
            ->assertJsonStructure(['message', 'data', 'links', 'meta']);
    }

    public function test_active_resident_of_same_community_can_show(): void
    {
        $this->getJson($this->showUri($this->communityA, $this->listingA), $this->token($this->residentUserA))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Service listing retrieved successfully.')
            ->assertJsonPath('data.id', $this->listingA->id);
    }

    public function test_outsider_cannot_index(): void
    {
        $this->getJson($this->indexUri($this->communityA), $this->token($this->outsider))
            ->assertStatus(403);
    }

    public function test_outsider_cannot_show(): void
    {
        $this->getJson($this->showUri($this->communityA, $this->listingA), $this->token($this->outsider))
            ->assertStatus(403);
    }

    public function test_manager_of_same_community_can_read(): void
    {
        $this->getJson($this->indexUri($this->communityA), $this->token($this->managerUserA))->assertStatus(200);
        $this->getJson($this->showUri($this->communityA, $this->listingA), $this->token($this->managerUserA))->assertStatus(200);
    }

    public function test_manager_of_another_community_cannot_access_this_one(): void
    {
        $this->getJson($this->indexUri($this->communityA), $this->token($this->managerUserB))
            ->assertStatus(403);
        $this->getJson($this->showUri($this->communityA, $this->listingA), $this->token($this->managerUserB))
            ->assertStatus(403);
    }

    public function test_super_admin_can_read(): void
    {
        $this->getJson($this->indexUri($this->communityA), $this->token($this->superAdmin))->assertStatus(200);
        $this->getJson($this->showUri($this->communityA, $this->listingA), $this->token($this->superAdmin))->assertStatus(200);
    }

    // ── IDOR ──

    public function test_foreign_community_listing_under_wrong_community_is_404(): void
    {
        // Listing belongs to B; requested through community A's route.
        $foreign = ServiceListing::factory()->create([
            'community_id' => $this->communityB->id,
            'resident_id' => Resident::where('community_id', $this->communityB->id)->value('id'),
        ]);

        // A user WITH access to A still must NOT see B's listing via A.
        $this->getJson($this->showUri($this->communityA, $foreign), $this->token($this->residentUserA))
            ->assertStatus(404);
    }

    public function test_missing_listing_is_404(): void
    {
        $this->getJson($this->showUri($this->communityA, 999999), $this->token($this->residentUserA))
            ->assertStatus(404);
    }

    public function test_soft_deleted_listing_is_404(): void
    {
        $this->listingA->delete(); // soft delete

        $this->getJson($this->showUri($this->communityA, $this->listingA), $this->token($this->residentUserA))
            ->assertStatus(404);
    }

    // ── Data contract ──

    public function test_index_contains_only_requested_community_listings(): void
    {
        ServiceListing::factory()->create([
            'community_id' => $this->communityB->id,
            'resident_id' => Resident::where('community_id', $this->communityB->id)->value('id'),
        ]);

        $response = $this->getJson($this->indexUri($this->communityA), $this->token($this->residentUserA))
            ->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertContains($this->listingA->id, $ids);
        $this->assertTrue($ids->every(fn ($id) => ServiceListing::withTrashed()->find($id)?->community_id === $this->communityA->id));
    }

    public function test_index_paginates(): void
    {
        ServiceListing::factory()->count(20)->create([
            'community_id' => $this->communityA->id,
            'resident_id' => $this->residentA->id,
        ]);

        $response = $this->getJson($this->indexUri($this->communityA) . '?per_page=5', $this->token($this->residentUserA))
            ->assertStatus(200);

        $this->assertCount(5, $response->json('data'));
        $this->assertSame(5, $response->json('meta.per_page'));
        $this->assertGreaterThan(1, $response->json('meta.last_page'));
        $this->assertSame(21, $response->json('meta.total')); // 20 + setUp listing
    }

    public function test_per_page_is_clamped_to_maximum(): void
    {
        ServiceListing::factory()->count(3)->create([
            'community_id' => $this->communityA->id,
            'resident_id' => $this->residentA->id,
        ]);

        // Requesting an absurd page size must not exceed the cap.
        $this->getJson($this->indexUri($this->communityA) . '?per_page=99999', $this->token($this->residentUserA))
            ->assertStatus(200)
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_index_is_ordered_by_created_at_desc_then_id_desc(): void
    {
        $oldest = ServiceListing::factory()->create([
            'community_id' => $this->communityA->id, 'resident_id' => $this->residentA->id,
            'created_at' => Carbon::now()->subDays(3),
        ]);
        $newest = ServiceListing::factory()->create([
            'community_id' => $this->communityA->id, 'resident_id' => $this->residentA->id,
            'created_at' => Carbon::now()->subDay(),
        ]);

        // Same created_at -> id DESC tiebreaker.
        $same = Carbon::now()->subDays(2);
        $lower = ServiceListing::factory()->create([
            'community_id' => $this->communityA->id, 'resident_id' => $this->residentA->id,
            'created_at' => $same,
        ]);
        $higher = ServiceListing::factory()->create([
            'community_id' => $this->communityA->id, 'resident_id' => $this->residentA->id,
            'created_at' => $same,
        ]);

        $ids = collect(
            $this->getJson($this->indexUri($this->communityA) . '?per_page=100', $this->token($this->residentUserA))
                ->assertStatus(200)
                ->json('data')
        )->pluck('id')->values()->all();

        $posNewest = array_search($newest->id, $ids, true);
        $posHigher = array_search($higher->id, $ids, true);
        $posLower = array_search($lower->id, $ids, true);
        $posOldest = array_search($oldest->id, $ids, true);

        // newest(created_at) < higher(same ts, bigger id) < lower(same ts, smaller id) < oldest
        $this->assertTrue($posNewest < $posHigher, 'newest created_at should come first');
        $this->assertTrue($posHigher < $posLower, 'id DESC tiebreaker within same created_at');
        $this->assertTrue($posLower < $posOldest, 'oldest created_at should come last');
    }

    public function test_show_returns_expected_resource_fields(): void
    {
        $response = $this->getJson($this->showUri($this->communityA, $this->listingA), $this->token($this->residentUserA))
            ->assertStatus(200);

        $data = $response->json('data');

        $this->assertSame($this->listingA->id, $data['id']);
        $this->assertSame($this->communityA->id, $data['community_id']);
        $this->assertSame($this->listingA->title, $data['title']);
        $this->assertSame($this->listingA->type, $data['type']);
        $this->assertSame($this->listingA->status, $data['status']);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('price', $data);
        $this->assertArrayHasKey('expires_at', $data);
        $this->assertArrayHasKey('closed_at', $data);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);

        // Author: minimal representation only.
        $this->assertSame($this->residentA->id, $data['author']['id']);
        $this->assertSame($this->residentUserA->name, $data['author']['name']);
    }

    public function test_show_does_not_leak_sensitive_fields(): void
    {
        $json = $this->getJson($this->showUri($this->communityA, $this->listingA), $this->token($this->residentUserA))
            ->assertStatus(200)
            ->getContent();

        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString('email', $json);
        $this->assertStringNotContainsString('phone', $json);
        $this->assertStringNotContainsString('resident_id', $json);
        $this->assertStringNotContainsString('remember_token', $json);
    }
}
