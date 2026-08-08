<?php

declare(strict_types=1);

namespace Tests\Feature\Poll;

use ReflectionClass;
use Tests\TestCase;
use Modules\Poll\app\Http\Controllers\V1\PollController;

/**
 * Ensure the Poll controller class and its primary methods exist.
 */
class PollControllerExistenceTest extends TestCase
{
    public function test_poll_controller_and_methods_exist(): void
    {
        $this->assertTrue(class_exists(PollController::class), 'PollController class must exist.');

        $rc = new ReflectionClass(PollController::class);

        foreach (['index', 'store', 'show'] as $method) {
            $this->assertTrue($rc->hasMethod($method), "PollController must implement {$method}()");
        }
    }
}
