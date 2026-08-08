<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\CommunityManager;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Modules\Post\app\Models\Post;
use Modules\ServiceListing\app\Models\ServiceListing;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class MediaTestCase extends TestCase
{
    use RefreshDatabase;

    protected Community $communityA;
    protected Community $communityB;
    protected Resident $ownerResident;
    protected User $ownerUser;
    protected User $secondUser;
    protected User $managerUser;
    protected User $providerUser;
    protected User $superAdmin;
    protected User $outsider;
    protected Post $post;
    protected ServiceListing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        // Official roles are seeded by Tests\TestCase::setUp(). The project's
        // RolePermissionSeeder cannot be used: it throws PermissionDoesNotExist
        // because it syncs web-guard permissions onto an api-guard role.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Storage::fake('public');

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

        $this->post = Post::create([
            'community_id' => $this->communityA->id,
            'resident_id' => $this->ownerResident->id,
            'category' => 'general',
            'content' => 'A post.',
        ]);

        $this->listing = ServiceListing::factory()->create([
            'community_id' => $this->communityA->id,
            'resident_id' => $this->ownerResident->id,
            'status' => 'active',
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
    protected function token(User $user): array
    {
        return [
            'Authorization' => 'Bearer ' . $user->createToken('media-test')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    protected function validImage(string $name = 'photo.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 400, 300);
    }

    protected function postMediaUri(Community $community, Post|int $post): string
    {
        $id = $post instanceof Post ? $post->id : $post;

        return "/api/v1/communities/{$community->id}/posts/{$id}/media";
    }

    protected function listingMediaUri(Community $community, ServiceListing|int $listing): string
    {
        $id = $listing instanceof ServiceListing ? $listing->id : $listing;

        return "/api/v1/communities/{$community->id}/service-listings/{$id}/media";
    }

    protected function postReorderUri(Community $community, Post $post): string
    {
        return "/api/v1/communities/{$community->id}/posts/{$post->id}/media/reorder";
    }

    protected function listingReorderUri(Community $community, ServiceListing $listing): string
    {
        return "/api/v1/communities/{$community->id}/service-listings/{$listing->id}/media/reorder";
    }

    protected function deleteMediaUri(Community $community, int $mediaId): string
    {
        return "/api/v1/communities/{$community->id}/media/{$mediaId}";
    }
}
