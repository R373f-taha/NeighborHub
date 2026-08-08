<?php

declare(strict_types=1);

namespace Tests\Unit\Poll;

use Modules\Poll\app\Models\Poll;
use Tests\TestCase;

/**
 * Ensures Poll scope methods and helper methods exist and are callable.
 */
class PollScopesAndHelpersTest extends TestCase
{
    public function test_poll_scope_methods_exist(): void
    {
        $poll = new Poll();

        foreach (['scopeDraft', 'scopeActive', 'scopeClosed', 'scopeExpired'] as $method) {
            $this->assertTrue(
                method_exists($poll, $method),
                "Poll must implement {$method}()"
            );
        }
    }

    public function test_poll_helper_methods_exist_and_callable(): void
    {
        $poll = new Poll();

        foreach (['isActive', 'isClosed', 'isDraft', 'isExpired', 'getTotalVotesCount'] as $method) {
            $this->assertTrue(
                method_exists($poll, $method),
                "Poll must implement {$method}()"
            );
        }
    }
}
