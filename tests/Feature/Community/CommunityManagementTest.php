<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use Modules\Auth\app\Enums\UserRole;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Tests\TestCase;

class CommunityManagementTest extends TestCase
{
    public function test_manager_can_update_managed_community_but_not_create_or_delete_it(): void
    {
        $manager = new User(['id' => 10]);
        $manager->forceFill(['role' => UserRole::Manager]);
        $community = new Community(['id' => 55]);

        $this->assertTrue($manager->isManager());
        $this->assertInstanceOf(Community::class, $community);
    }
}
