<?php

declare(strict_types=1);

namespace Tests\Feature\Poll;

use Tests\TestCase;
use Modules\Poll\App\Transformers\PollResource;
use Modules\Poll\app\Models\Poll;

/**
 * Lightweight tests for PollResource transformer availability.
 */
class PollTransformersTest extends TestCase
{
    public function test_poll_resource_class_exists_and_has_to_array(): void
    {
        $this->assertTrue(class_exists(PollResource::class), 'PollResource must exist');

        $poll = new Poll([
            'title' => 'Sample',
            'status' => 'draft',
        ]);

        $resource = new PollResource($poll);

        $this->assertTrue(method_exists($resource, 'toArray'), 'PollResource must implement toArray()');
    }
}
