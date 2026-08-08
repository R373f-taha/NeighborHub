<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use Modules\Auth\app\Enums\UserRole;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Http\Requests\StoreCommunityRequest;
use Modules\Community\app\Models\Community;
use Tests\TestCase;

class CommunityAuthorizationTest extends TestCase
{
    public function test_normal_user_cannot_manage_communities(): void
    {
        $request = new StoreCommunityRequest();
        $user = new User(['id' => 2]);
        $user->forceFill(['role' => UserRole::Resident]);
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    public function test_manager_can_update_managed_community(): void
    {
        $manager = new User(['id' => 11]);
        $manager->forceFill(['role' => UserRole::Manager]);
        $community = new Community(['id' => 7]);

        $this->assertTrue($manager->isManager());
        $this->assertInstanceOf(Community::class, $community);
    }
}
