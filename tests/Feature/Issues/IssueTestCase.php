<?php

declare(strict_types=1);

namespace Tests\Feature\Issues;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\CommunityManager;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Models\IssueCategory;
use Tests\TestCase;

abstract class IssueTestCase extends TestCase
{
    use RefreshDatabase;

    protected Community $communityA;
    protected Community $communityB;
    protected User $residentUserA;
    protected User $residentUserB;
    protected User $managerA;
    protected User $managerB;
    protected User $provider;
    protected User $superAdmin;
    protected IssueCategory $category;
    protected IssueCategory $inactiveCategory;
    protected Issue $issueA;
    protected Issue $issueB;

    protected function setUp(): void
    {
        parent::setUp();

        // The official role/permission contract is provisioned once per
        // database lifecycle by Tests\Support\RbacProvisioner (outside the
        // per-test transaction), so it is already available here. Re-seeding it
        // per test inside the RefreshDatabase transaction was the source of the
        // MySQL deadlock (syncPermissions exclusive lock + permission-cache
        // LOCK IN SHARE MODE reload in the same transaction) that cascaded into
        // hundreds of "Unknown database" failures on contended machines.

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

        $this->managerB = User::factory()->manager()->create(['is_active' => true]);
        CommunityManager::create(['community_id' => $this->communityB->id, 'manager_id' => $this->managerB->id]);

        $this->provider = User::factory()->provider()->create(['is_active' => true]);
        $this->superAdmin = User::factory()->superAdmin()->create(['is_active' => true]);

        $this->category = IssueCategory::create(['name' => 'Plumbing', 'is_active' => true]);
        $this->inactiveCategory = IssueCategory::create(['name' => 'Deprecated', 'is_active' => false]);

$this->issueA = Issue::create(['community_id' => $this->communityA->id,'category_id' => $this->category->id,'title' => 'Leak A','description' => 'desc','location' => 'loc','priority' => 'high','status' => 'open','reported_by' => $this->residentUserA->id,]);
  $this->issueB = Issue::create(['community_id' => $this->communityB->id, 'category_id' => $this->category->id, 'title' => 'Leak B', 'description' => 'desc', 'location' => 'loc', 'priority' => 'low', 'status' => 'open', 'reported_by' => $this->residentUserB->id]);
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
            'Authorization' => 'Bearer ' . $user->createToken('issue-test')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    protected function indexUri(Community $community): string
    {
        return "/v1/communities/{$community->id}/issues";
    }

    protected function showUri(Community $community, Issue|int $issue): string
    {
        $id = $issue instanceof Issue ? $issue->id : $issue;

        return "/v1/communities/{$community->id}/issues/{$id}";
    }

    protected function assignUri(Community $community, Issue|int $issue): string
    {
        $id = $issue instanceof Issue ? $issue->id : $issue;

        return "/v1/communities/{$community->id}/issues/{$id}/assign";
    }

    protected function statusUri(Community $community, Issue|int $issue): string
    {
        $id = $issue instanceof Issue ? $issue->id : $issue;

        return "/v1/communities/{$community->id}/issues/{$id}/status";
    }

    protected function updatesUri(Community $community, Issue|int $issue): string
    {
        $id = $issue instanceof Issue ? $issue->id : $issue;

        return "/v1/communities/{$community->id}/issues/{$id}/updates";
    }
}
