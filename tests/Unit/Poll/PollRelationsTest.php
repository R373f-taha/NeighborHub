<?php

declare(strict_types=1);

namespace Tests\Unit\Poll;

use Modules\Poll\app\Models\Poll;
use Modules\Poll\app\Models\PollOption;
use Modules\Poll\app\Models\PollVote;
use Modules\Community\app\Models\Community;
use Modules\Auth\app\Models\User;
use Tests\TestCase;

/**
 * Verifies Poll relationship targets without touching the DB.
 */
class PollRelationsTest extends TestCase
{
    public function test_poll_relations_target_expected_models(): void
    {
        $poll = new Poll();

        $this->assertSame(Community::class, $poll->community()->getRelated()::class);
        $this->assertSame(User::class, $poll->creator()->getRelated()::class);
        $this->assertSame(User::class, $poll->closer()->getRelated()::class);
        $this->assertSame(PollOption::class, $poll->options()->getRelated()::class);
        $this->assertSame(PollVote::class, $poll->votes()->getRelated()::class);
    }
}
