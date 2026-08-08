<?php

declare(strict_types=1);

namespace Tests\Feature\Announcements;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Models\User;
use Modules\Auth\Database\Seeders\RolePermissionSeeder;
use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\CommunityManager;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class AnnouncementTestCase extends TestCase
{
    use RefreshDatabase;

    protected Community $communityA;
    protected Community $communityB;
    protected User $residentUserA;
    protected User $residentUserB;
    protected User $managerA;
    protected User $managerANonCreator;
    protected User $managerB;
    protected User $superAdmin;
    protected Announcement $announcementA;
    protected Announcement $announcementB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->communityA = Community::create(['name' => 'Community A', 'city' => 'C', 'address' => 'A', 'total_units' => 10, 'is_active' => true]);
        $this->communityB = Community::create(['name' => 'Community B', 'city' => 'C2', 'address' => 'A2', 'total_units' => 5, 'is_active' => true]);

        $unitA = Unit::create(['community_id' => $this->communityA->id, 'unit_number' => 'U1', 'building_name' => 'B1', 'rooms' => 2, 'unit_type' => 'apartment', 'is_active' => true]);
        $unitB = Unit::create(['community_id' => $this->communityB->id, 'unit_number' => 'U2', 'building_name' => 'B2', 'rooms' => 2, 'unit_type' => 'apartment', 'is_active' => true]);

        $this->residentUserA = User::factory()->resident()->create(['is_active' => true]);
        Resident::create(['user_id' => $this->residentUserA->id, 'unit_id' => $unitA->id, 'community_id' => $this->communityA->id, 'residence_type' => 'owner', 'status' => 'active', 'current_marker' => true]);

        $this->residentUserB = User::factory()->resident()->create(['is_active' => true]);
        Resident::create(['user_id' => $this->residentUserB->id, 'unit_id' => $unitB->id, 'community_id' => $this->communityB->id, 'residence_type' => 'tenant', 'status' => 'active', 'current_marker' => true]);

        $this->managerA = User::factory()->manager()->create(['is_active' => true]);
        CommunityManager::create(['community_id' => $this->communityA->id, 'manager_id' => $this->managerA->id]);

        $this->managerANonCreator = User::factory()->manager()->create(['is_active' => true]);
        CommunityManager::create(['community_id' => $this->communityA->id, 'manager_id' => $this->managerANonCreator->id]);

        $this->managerB = User::factory()->manager()->create(['is_active' => true]);
        CommunityManager::create(['community_id' => $this->communityB->id, 'manager_id' => $this->managerB->id]);

        $this->superAdmin = User::factory()->superAdmin()->create(['is_active' => true]);

        $this->announcementA = Announcement::create(['community_id' => $this->communityA->id, 'created_by_manager' => $this->managerA->id, 'title' => 'Announcement A', 'content' => 'Content A', 'priority' => 'normal']);
        $this->announcementB = Announcement::create(['community_id' => $this->communityB->id, 'created_by_manager' => $this->managerB->id, 'title' => 'Announcement B', 'content' => 'Content B', 'priority' => 'urgent']);
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
            'Authorization' => 'Bearer ' . $user->createToken('announcement-test')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    protected function indexUri(Community $community): string
    {
        return "/v1/communities/{$community->id}/announcements";
    }

    protected function showUri(Community $community, Announcement|int $announcement): string
    {
        $id = $announcement instanceof Announcement ? $announcement->id : $announcement;

        return "/v1/communities/{$community->id}/announcements/{$id}";
    }

    protected function reactUri(Community $community, Announcement|int $announcement): string
    {
        $id = $announcement instanceof Announcement ? $announcement->id : $announcement;

        return "/v1/communities/{$community->id}/announcements/{$id}/react";
    }
}
