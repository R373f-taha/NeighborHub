<?php

declare(strict_types=1);

namespace Tests\Unit\Poll;

use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Poll\app\Enums\PollStatus;
use Modules\Poll\app\Models\Poll;
use Tests\TestCase;

/**
 * Unit tests for Poll model business logic and relationship definitions.
 */
class PollModelTest extends TestCase
{
    public function test_poll_relationships_are_defined_correctly(): void
    {
        $poll = new Poll();

        $this->assertSame(
            Community::class,
            $poll->community()->getRelated()::class
        );

        $this->assertSame(
            User::class,
            $poll->creator()->getRelated()::class
        );
    }

    public function test_poll_status_helpers_reflect_active_state(): void
    {
        $poll = new Poll([
            'status' => PollStatus::Active,
            'ends_at' => now()->addDays(5),
        ]);

        $this->assertTrue($poll->isActive());
        $this->assertFalse($poll->isClosed());
        $this->assertFalse($poll->isDraft());
        $this->assertFalse($poll->isExpired());
    }

    public function test_poll_status_helpers_reflect_draft_state(): void
    {
        $poll = new Poll([
            'status' => PollStatus::Draft,
            'ends_at' => now()->addDays(5),
        ]);

        $this->assertFalse($poll->isActive());
        $this->assertFalse($poll->isClosed());
        $this->assertTrue($poll->isDraft());
    }

    public function test_poll_expired_helper_returns_true_when_ends_at_is_past(): void
    {
        $poll = new Poll([
            'status' => PollStatus::Active,
            'ends_at' => now()->subDay(),
        ]);

        $this->assertTrue($poll->isExpired());
    }
}
