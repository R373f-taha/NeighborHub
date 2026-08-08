<?php

declare(strict_types=1);

namespace Tests\Unit\Poll;

use Modules\Poll\app\Models\Poll;
use Tests\TestCase;

/**
 * Unit tests for Poll vote counting and turnout percentage.
 *
 * These tests avoid database access by overriding relation methods
 * with lightweight test doubles.
 */
class PollVotesTest extends TestCase
{
    public function test_get_total_votes_count_returns_mocked_value(): void
    {
        $poll = new class extends Poll {
            public function votes()
            {
                return new class {
                    public function count(): int
                    {
                        return 42;
                    }
                };
            }
        };

        $this->assertSame(42, $poll->getTotalVotesCount());
    }

    public function test_get_turnout_percentage_computes_correctly(): void
    {
        $poll = new class extends Poll {
            public function votes()
            {
                return new class {
                    public function count(): int
                    {
                        return 20;
                    }
                };
            }
        };

        $community = new class {
            public function residents()
            {
                return new class {
                    public function where($k, $v)
                    {
                        return $this;
                    }

                    public function count(): int
                    {
                        return 100;
                    }
                };
            }
        };

        $poll->setRelation('community', $community);

        $this->assertSame(20.0, $poll->getTurnoutPercentage());
    }
}
