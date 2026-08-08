<?php

declare(strict_types=1);

namespace Tests\Unit\Poll;

use ReflectionClass;
use Modules\Poll\app\Services\V1\PollService;
use Tests\TestCase;

/**
 * Confirms PollService exposes the expected public methods (non-DB).
 */
class PollServiceExistenceTest extends TestCase
{
    public function test_poll_service_methods_exist(): void
    {
        $this->assertTrue(class_exists(PollService::class), 'PollService must exist');

        $rc = new ReflectionClass(PollService::class);

        foreach (['getCommunityPolls', 'createPoll'] as $method) {
            $this->assertTrue($rc->hasMethod($method), "PollService must implement {$method}()");
        }
    }
}
