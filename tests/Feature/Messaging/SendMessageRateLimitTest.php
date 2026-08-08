<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Unit;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Models\Message;
use Tests\TestCase;

/**
 * MSG-4 messaging-send rate limiter: it is registered, applied to the send
 * route only, keyed by authenticated user, and a 429 creates no Message.
 *
 * LOCAL ONLY / NOT staged.
 */
class SendMessageRateLimitTest extends TestCase
{
    use RefreshDatabase;
    use ConversationTestHelpers;

    private Community $community;
    private User $userA;
    private User $userB;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        // The array cache must be clean so each test's limiter window starts fresh.
        Cache::flush();

        $this->community = $this->makeCommunity('RL');
        $unit = $this->makeUnit($this->community, 'R1');

        $this->userA = User::factory()->resident()->create(['is_active' => true]);
        $this->makeResident($this->community, $unit, $this->userA);

        $this->userB = User::factory()->resident()->create(['is_active' => true]);
        $this->makeResident($this->community, $unit, $this->userB, 'active', 'tenant');

        $this->conversation = $this->makeConversation($this->community);
        $this->makeParticipant($this->conversation, $this->userA);
        $this->makeParticipant($this->conversation, $this->userB);

        // Rebind to a tiny limit so 429 is reachable in a fast test, while
        // keeping the same per-user keying contract.
        RateLimiter::for('messaging-send', fn (Request $r): Limit => Limit::perMinute(2)->by($r->user()?->id ?: $r->ip()));
    }

    public function test_limiter_is_registered_and_send_route_uses_it(): void
    {
        $this->assertNotNull(RateLimiter::limiter('messaging-send'));

        $store = Route::getRoutes()->getByName('api.v1.communities.conversations.messages.store');
        $this->assertNotNull($store, 'Send route registered.');
        $this->assertContains('throttle:messaging-send', $store->gatherMiddleware());

        // Read endpoints must NOT carry this limiter.
        $index = Route::getRoutes()->getByName('api.v1.communities.conversations.index');
        $this->assertNotNull($index);
        $this->assertNotContains('throttle:messaging-send', $index->gatherMiddleware());
    }

    public function test_exceeding_limit_returns_429_and_creates_no_message(): void
    {
        $uri = $this->messagesUri($this->community, $this->conversation);

        $this->postJson($uri, $this->payload(), $this->token($this->userA))->assertStatus(201);
        $this->postJson($uri, $this->payload(), $this->token($this->userA))->assertStatus(201);

        // Third send in the same user window -> throttled.
        $over = $this->postJson($uri, $this->payload(), $this->token($this->userA));
        $this->assertSame(429, $over->status());

        // Exactly the two successful sends; no partial/throttled row.
        $this->assertSame(2, Message::count());
    }

    public function test_limiter_is_keyed_per_authenticated_user(): void
    {
        $uri = $this->messagesUri($this->community, $this->conversation);

        // userA exhausts their bucket.
        $this->postJson($uri, $this->payload(), $this->token($this->userA))->assertStatus(201);
        $this->postJson($uri, $this->payload(), $this->token($this->userA))->assertStatus(201);
        $this->postJson($uri, $this->payload(), $this->token($this->userA))->assertStatus(429);

        // userB has an independent bucket and can still send.
        $this->postJson($uri, $this->payload(), $this->token($this->userB))->assertStatus(201);
    }

    /** @return array<string, string> */
    private function payload(): array
    {
        return ['content' => 'Hi'];
    }
}
