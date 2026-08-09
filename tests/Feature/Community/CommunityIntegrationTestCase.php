<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\CommunityManager;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Tests\AuthApiTestCase;

/**
 * Base fixture for real (database-backed) Community API tests.
 *
 * The Community API gates every mutation with a role middleware alias plus a
 * Spatie `can:<permission>` check, so the api-guard permission set must exist.
 * It is published once per database lifecycle by
 * Tests\Support\RbacProvisioner (outside the per-test transaction) so the
 * contract is available regardless of which suite runs first, with no per-test
 * permission-sync contention and no mutable demo-user leakage.
 */
abstract class CommunityIntegrationTestCase extends AuthApiTestCase
{
    use RefreshDatabase;

    protected Community $communityA;
    protected Community $communityB;
    protected User $managerA;
    protected User $managerB;
    protected User $superAdmin;
    protected User $residentUserA;
    protected Resident $residentA;
    protected Unit $unitA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->communityA = Community::create([
            'name' => 'Community A', 'city' => 'Amman', 'address' => 'A St',
            'total_units' => 10, 'is_active' => true,
        ]);
        $this->communityB = Community::create([
            'name' => 'Community B', 'city' => 'Irbid', 'address' => 'B St',
            'total_units' => 5, 'is_active' => true,
        ]);

        $this->unitA = Unit::create([
            'community_id' => $this->communityA->id, 'unit_number' => 'U1',
            'building_name' => 'B1', 'rooms' => 2, 'unit_type' => 'apartment', 'is_active' => true,
        ]);
        Unit::create([
            'community_id' => $this->communityB->id, 'unit_number' => 'U2',
            'building_name' => 'B2', 'rooms' => 2, 'unit_type' => 'apartment', 'is_active' => true,
        ]);

        $this->managerA = User::factory()->manager()->create(['is_active' => true]);
        CommunityManager::create(['community_id' => $this->communityA->id, 'manager_id' => $this->managerA->id]);

        $this->managerB = User::factory()->manager()->create(['is_active' => true]);
        CommunityManager::create(['community_id' => $this->communityB->id, 'manager_id' => $this->managerB->id]);

        $this->superAdmin = User::factory()->superAdmin()->create(['is_active' => true]);

        $this->residentUserA = User::factory()->resident()->create(['is_active' => true]);
    }

    /** @return array<string, string> */
    protected function token(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.$user->createToken('community-test')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }
}
