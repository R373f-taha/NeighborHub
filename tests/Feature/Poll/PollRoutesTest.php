<?php

declare(strict_types=1);

namespace Tests\Feature\Poll;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Application-level Poll route coverage.
 *
 * These feature tests verify the public Poll route naming and URI
 * registration without depending on database migrations.
 */
class PollRoutesTest extends TestCase
{
    public function test_poll_routes_are_registered_with_expected_names(): void
    {
        $expectedRoutes = [
            'polls.index' => 'GET',
            'polls.store' => 'POST',
            'polls.show' => 'GET',
            'polls.activate' => 'POST',
            'polls.close' => 'POST',
            'polls.vote' => 'POST',
            'polls.results' => 'GET',
        ];

        foreach ($expectedRoutes as $routeName => $expectedMethod) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull(
                $route,
                "Failed asserting that route '{$routeName}' is registered."
            );

            $this->assertContains(
                $expectedMethod,
                $route->methods(),
                "Failed asserting that route '{$routeName}' accepts HTTP method {$expectedMethod}."
            );
        }
    }

    public function test_poll_index_route_uses_api_v1_community_prefix(): void
    {
        $route = Route::getRoutes()->getByName('polls.index');

        $this->assertNotNull(
            $route,
            'The polls.index route should be available via route registration.'
        );

        $this->assertSame(
            'api/v1/communities/{communityId}/polls',
            $route->uri(),
            'Poll index route must use the expected community API URI structure.'
        );
    }
}
