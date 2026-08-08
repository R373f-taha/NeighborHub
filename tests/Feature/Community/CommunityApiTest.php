<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use Illuminate\Http\Request as HttpRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Http\Controllers\V1\CommunityController;
use Modules\Community\app\Http\Requests\StoreCommunityRequest;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Services\V1\CommunityService;
use Mockery;
use Tests\TestCase;

class CommunityApiTest extends TestCase
{
    private const COMMUNITY_NAME = 'NeighborHub Community';
    private const COMMUNITY_CITY = 'Amman';

    public function test_guest_can_list_active_communities(): void
    {
        $community = new Community([
            'id' => 1,
            'name' => self::COMMUNITY_NAME,
            'city' => self::COMMUNITY_CITY,
            'is_active' => true,
        ]);

        $items = collect([$community]);
        $paginator = new LengthAwarePaginator($items->all(), $items->count(), 20, 1, [
            'path' => '/api/v1/communities',
        ]);

        $mock = Mockery::mock(CommunityService::class);
        $mock->shouldReceive('getCommunities')->once()->andReturn($paginator);

        $this->app->instance(CommunityService::class, $mock);

        $response = $this->getJson('/api/v1/communities');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.0.name', self::COMMUNITY_NAME)
            ->assertJsonPath('data.0.city', self::COMMUNITY_CITY);
    }

    public function test_guest_receives_404_when_no_communities_exist(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 20, 1, ['path' => '/api/v1/communities']);

        $mock = Mockery::mock(CommunityService::class);
        $mock->shouldReceive('getCommunities')->once()->andReturn($paginator);

        $this->app->instance(CommunityService::class, $mock);

        $response = $this->getJson('/api/v1/communities');

        $response->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'No communities found.']);
    }

    public function test_manager_can_create_community(): void
    {
        $created = new Community([
            'id' => 123,
            'name' => self::COMMUNITY_NAME,
            'city' => self::COMMUNITY_CITY,
            'is_active' => true,
        ]);

        $mock = Mockery::mock(CommunityService::class);
        $mock->shouldReceive('create')->once()->andReturn($created);

        $controller = new CommunityController($mock);

        $storeReq = Mockery::mock(StoreCommunityRequest::class);
        $storeReq->shouldReceive('validated')->andReturn([
            'name' => self::COMMUNITY_NAME,
            'city' => self::COMMUNITY_CITY,
            'address' => '123 NeighborHub Ave',
            'is_active' => true,
        ]);

        $response = $controller->store($storeReq);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(self::COMMUNITY_NAME, $response->getData(true)['data']['name']);
    }

    public function test_normal_user_cannot_manage_communities(): void
    {
        $user = new User(['id' => 2, 'role' => 'resident']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/communities', [
                'name' => 'Unauthorized Community',
                'city' => 'Amman',
            ]);

        $response->assertStatus(403);
    }

    public function test_community_list_supports_city_filter_and_pagination(): void
    {
        $c1 = new Community(['id' => 1, 'name' => self::COMMUNITY_NAME, 'city' => 'Amman', 'is_active' => true]);
        $c2 = new Community(['id' => 2, 'name' => 'Irbid Community', 'city' => 'Irbid', 'is_active' => true]);

        $items = collect([$c1, $c2]);
        $paginator = new LengthAwarePaginator($items->all(), 2, 10, 1, ['path' => '/api/v1/communities']);

        $mock = Mockery::mock(CommunityService::class);
        $mock->shouldReceive('getCommunities')->once()->with(['city' => 'Amman'], 10)->andReturn($paginator);

        $controller = new CommunityController($mock);

        $request = HttpRequest::create('/api/v1/communities', 'GET', ['city' => 'Amman', 'per_page' => 10]);

        $response = $controller->index($request);

        $this->assertSame(200, $response->getStatusCode());
        $json = $response->getData(true);
        $this->assertSame(10, $json['meta']['per_page']);
        $this->assertSame('Amman', $json['data'][0]['city']);
    }
}

