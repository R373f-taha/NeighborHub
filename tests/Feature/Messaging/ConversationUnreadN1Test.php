<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Unit;
use Tests\TestCase;

/**
 * MSG-5 N+1 regression: the conversation list must derive unread_count for
 * ALL conversations with a SINGLE set-based query, never one COUNT per
 * conversation. Proven via query logging on real representative data.
 *
 * LOCAL ONLY / NOT staged.
 */
class ConversationUnreadN1Test extends TestCase
{
    use RefreshDatabase;
    use ConversationTestHelpers;

    /**
     * The number of queries touching the `messages` table during a list
     * request must NOT grow with the number of conversations — i.e. the
     * set-based unread query is executed exactly once regardless of N.
     *
     * @dataProvider conversationCounts
     */
    public function test_unread_calculation_does_not_grow_with_conversation_count(int $n): void
    {
        [$community, $userA, $userB] = $this->seedParticipant($n);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson($this->indexUri($community), $this->token($userA))->assertStatus(200);

        $messagesQueries = collect(DB::getQueryLog())
            ->filter(fn (array $q): bool => str_contains($q['query'], '`messages`'))
            ->count();

        DB::disableQueryLog();

        // Exactly one set-based unread query against `messages`, independent of N.
        $this->assertSame(
            1,
            $messagesQueries,
            "List with {$n} conversations must use a single set-based unread query (no N+1).",
        );
    }

    /** @return array<string, array{int}> */
    public static function conversationCounts(): array
    {
        return [
            '3 conversations' => [3],
            '8 conversations' => [8],
        ];
    }

    /** @return array{0: Community, 1: User, 2: User} */
    private function seedParticipant(int $n): array
    {
        $community = $this->makeCommunity('N1');
        $unit = $this->makeUnit($community, 'U1');

        $userA = User::factory()->resident()->create(['is_active' => true]);
        $this->makeResident($community, $unit, $userA);
        $userB = User::factory()->resident()->create(['is_active' => true]);
        $this->makeResident($community, $unit, $userB, 'active', 'tenant');

        for ($i = 0; $i < $n; $i++) {
            $conversation = $this->makeConversation($community);
            $this->makeParticipant($conversation, $userA);
            $this->makeParticipant($conversation, $userB);
            // A couple of incoming (B) messages per conversation.
            $this->makeMessage($conversation, $community, $userB);
            $this->makeMessage($conversation, $community, $userB);
        }

        return [$community, $userA, $userB];
    }
}
