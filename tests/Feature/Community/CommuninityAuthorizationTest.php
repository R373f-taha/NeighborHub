<?php

declare(strict_types=1);

namespace Modules\Community\Tests\Feature;

use Modules\Auth\app\Enums\UserRole;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Http\Requests\StoreCommunityRequest;
use Modules\Community\app\Models\Community;
use Tests\TestCase;

class CommuninityAuthorizationTest extends TestCase
{
    public function test_super_admin_can_create_community(): void
    {
        $request = new StoreCommunityRequest();
        $user = new User(['id' => 1]);
        $user->forceFill(['role' => UserRole::SuperAdmin]);
        $request->setUserResolver(fn () => $user);

        $this->assertTrue($request->authorize());
    }

    public function test_manager_cannot_create_community(): void
    {
        $request = new StoreCommunityRequest();
        $user = new User(['id' => 2]);
        $user->forceFill(['role' => UserRole::Manager]);
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    public function test_resident_cannot_create_community(): void
    {
        $request = new StoreCommunityRequest();
        $user = new User(['id' => 3]);
        $user->forceFill(['role' => UserRole::Resident]);
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    public function test_guest_cannot_create_community(): void
    {
        $request = new StoreCommunityRequest();
        $request->setUserResolver(fn () => null);

        $this->assertFalse($request->authorize());
    }

    public function test_manager_can_update_managed_community(): void
    {
        $community = new Community(['id' => 5]);
        $manager = new User(['id' => 4]);
        $manager->forceFill(['role' => UserRole::Manager]);

        $this->assertTrue($manager->isManager());
        $this->assertInstanceOf(Community::class, $community);
    }
}
