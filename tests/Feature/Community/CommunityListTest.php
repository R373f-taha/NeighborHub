<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Community\app\Http\Controllers\V1\CommunityController;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Services\V1\CommunityService;
use Mockery;
use Tests\TestCase;

class CommunityListTest extends TestCase
{
    public function test_guest_can_list_active_communities(): void
    {
        $community = new Community(['id' => 1, 'name' => 'NeighborHub Community', 'city' => 'Amman', 'is_active' => true]);
        $paginator = new LengthAwarePaginator([$community], 1, 20, 1, ['path' => '/api/v1/communities']);

        $mock = Mockery::mock(CommunityService::class);
        $mock->shouldReceive('getCommunities')->once()->andReturn($paginator);

        $this->app->instance(CommunityService::class, $mock);

        $controller = new CommunityController($mock);
        $response = $controller->index(new Request());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('NeighborHub Community', $response->getData(true)['data'][0]['name']);
    }

    public function test_guest_receives_404_when_no_communities_exist(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 20, 1, ['path' => '/api/v1/communities']);

        $mock = Mockery::mock(CommunityService::class);
        $mock->shouldReceive('getCommunities')->once()->andReturn($paginator);

        $this->app->instance(CommunityService::class, $mock);

        $controller = new CommunityController($mock);
        $response = $controller->index(new Request());

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('No communities found.', $response->getData(true)['message']);
    }

    public function test_community_list_supports_city_filter_and_pagination(): void
    {
        $c1 = new Community(['id' => 1, 'name' => 'NeighborHub Community', 'city' => 'Amman', 'is_active' => true]);
        $c2 = new Community(['id' => 2, 'name' => 'Irbid Community', 'city' => 'Irbid', 'is_active' => true]);
        $paginator = new LengthAwarePaginator([$c1, $c2], 2, 10, 1, ['path' => '/api/v1/communities']);

        $mock = Mockery::mock(CommunityService::class);
        $mock->shouldReceive('getCommunities')->once()->with(['city' => 'Amman'], 10)->andReturn($paginator);

        $this->app->instance(CommunityService::class, $mock);

        $controller = new CommunityController($mock);
        $request = new Request(['city' => 'Amman', 'per_page' => 10]);
        $response = $controller->index($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(10, $response->getData(true)['meta']['per_page']);
        $this->assertSame('Amman', $response->getData(true)['data'][0]['city']);
    }
}
