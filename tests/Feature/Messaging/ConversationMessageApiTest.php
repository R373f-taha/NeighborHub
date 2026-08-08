<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Models\ConversationParticipant;
use Modules\Messaging\app\Models\Message;
use Tests\TestCase;

/**
 * MSG-2 Conversation MESSAGE history API: participant-scoped history,
 * joined_at visibility baseline, deterministic ordering, sender exposure,
 * and read-state side-effect freedom (GET never mutates read state).
 *
 * LOCAL ONLY / NOT staged.
 */
class ConversationMessageApiTest extends TestCase
{
    use RefreshDatabase;
    use ConversationTestHelpers;

    private Community $community;
    private User $participantX;
    private User $participantLate;
    private User $nonParticipant;
    private User $superAdmin;
    private Conversation $conversation;
    private User $sender;

    /** @var array<string,Message> */
    private array $messages = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->community = $this->makeCommunity('A');
        $base = Carbon::create(2026, 1, 1, 12, 0, 0);

        $this->conversation = $this->makeConversation($this->community, $base);
        $this->sender = User::factory()->resident()->create(['is_active' => true]);

        // userX present from creation (joined at base).
        $this->participantX = User::factory()->resident()->create(['is_active' => true]);
        $this->makeParticipant($this->conversation, $this->participantX, $base);

        // userLate joins at base+60s (later).
        $this->participantLate = User::factory()->resident()->create(['is_active' => true]);
        $this->makeParticipant($this->conversation, $this->participantLate, $base->copy()->addSeconds(60));

        // Messages at distinct whole-second timestamps.
        $this->messages['m1'] = $this->makeMessage($this->conversation, $this->community, $this->sender, $base);
        $this->messages['m2'] = $this->makeMessage($this->conversation, $this->community, $this->sender, $base->copy()->addSeconds(60));
        $this->messages['m3'] = $this->makeMessage($this->conversation, $this->community, $this->sender, $base->copy()->addSeconds(120));

        // A message in an unrelated conversation that must never appear here.
        $other = $this->makeConversation($this->community, $base);
        $this->makeMessage($other, $this->community, $this->sender, $base);

        $this->nonParticipant = User::factory()->resident()->create(['is_active' => true]);
        $this->superAdmin = User::factory()->superAdmin()->create(['is_active' => true]);
    }

    // ── Access ──

    public function test_active_participant_can_list_messages(): void
    {
        $this->getJson($this->messagesUri($this->community, $this->conversation), $this->token($this->participantX))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Messages retrieved successfully.')
            ->assertJsonStructure(['message', 'data', 'links', 'meta']);
    }

    public function test_non_participant_messages_is_privacy_safe_404(): void
    {
        $this->getJson($this->messagesUri($this->community, $this->conversation), $this->token($this->nonParticipant))
            ->assertStatus(404);
    }

    public function test_left_participant_messages_is_404(): void
    {
        $leftUser = User::factory()->resident()->create(['is_active' => true]);
        $this->makeParticipant($this->conversation, $leftUser, Carbon::create(2026, 1, 1, 12, 0, 0), Carbon::create(2026, 1, 1, 12, 0, 5));

        $this->getJson($this->messagesUri($this->community, $this->conversation), $this->token($leftUser))
            ->assertStatus(404);
    }

    public function test_super_admin_non_participant_messages_is_404(): void
    {
        $this->getJson($this->messagesUri($this->community, $this->conversation), $this->token($this->superAdmin))
            ->assertStatus(404);
    }

    // ── joined_at visibility baseline ──

    public function test_participant_present_from_creation_sees_all_messages(): void
    {
        $response = $this->getJson($this->messagesUri($this->community, $this->conversation), $this->token($this->participantX))
            ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($this->messages['m1']->id, $ids);
        $this->assertContains($this->messages['m2']->id, $ids);
        $this->assertContains($this->messages['m3']->id, $ids);
    }

    public function test_late_participant_does_not_see_pre_join_messages(): void
    {
        $response = $this->getJson($this->messagesUri($this->community, $this->conversation), $this->token($this->participantLate))
            ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');

        // joined at base+60: m1 (base) is pre-join -> hidden.
        $this->assertNotContains($this->messages['m1']->id, $ids);
        // m2 (base+60) is at the boundary -> visible (>=).
        $this->assertContains($this->messages['m2']->id, $ids);
        // m3 (base+120) -> visible.
        $this->assertContains($this->messages['m3']->id, $ids);
    }

    public function test_boundary_message_at_exact_joined_at_is_visible(): void
    {
        // m2 was created at base+60, exactly when participantLate joined.
        // Asserted again here explicitly to lock the inclusive boundary.
        $response = $this->getJson($this->messagesUri($this->community, $this->conversation), $this->token($this->participantLate))
            ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($this->messages['m2']->id, $ids);
    }

    // ── Ordering & scoping ──

    public function test_messages_ordered_descending_by_created_at_then_id(): void
    {
        $response = $this->getJson($this->messagesUri($this->community, $this->conversation), $this->token($this->participantX))
            ->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');

        $this->assertSame([
            $this->messages['m3']->id,
            $this->messages['m2']->id,
            $this->messages['m1']->id,
        ], $ids);
    }

    public function test_only_messages_from_the_resolved_conversation_are_returned(): void
    {
        $response = $this->getJson($this->messagesUri($this->community, $this->conversation), $this->token($this->participantX))
            ->assertStatus(200);

        foreach ($response->json('data') as $message) {
            $this->assertSame($this->conversation->id, $message['conversation_id']);
        }
    }

    public function test_messages_pagination_works(): void
    {
        // Add many messages to exercise pagination.
        $base = Carbon::create(2026, 1, 1, 12, 0, 0)->addSeconds(200);
        for ($i = 0; $i < 40; $i++) {
            $this->makeMessage($this->conversation, $this->community, $this->sender, $base->copy()->addSeconds($i));
        }

        $this->getJson($this->messagesUri($this->community, $this->conversation).'?per_page=15', $this->token($this->participantX))
            ->assertStatus(200)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.last_page', 3);
    }

    // ── Sender exposure ──

    public function test_message_sender_exposes_only_id_and_name(): void
    {
        $response = $this->getJson($this->messagesUri($this->community, $this->conversation), $this->token($this->participantX))
            ->assertStatus(200);

        foreach ($response->json('data') as $message) {
            $this->assertSame(['id', 'name'], array_keys($message['sender']));
            $this->assertSame($this->sender->id, $message['sender']['id']);

            // No private user fields and no authoritative read-state fields.
            foreach (['email', 'phone', 'password', 'remember_token', 'role'] as $field) {
                $this->assertArrayNotHasKey($field, $message['sender']);
            }
            foreach (['is_read', 'read_at', 'community_id'] as $field) {
                $this->assertArrayNotHasKey($field, $message);
            }
        }
    }

    // ── Read-state side-effect freedom ──

    public function test_get_messages_does_not_mutate_last_read_message_id(): void
    {
        $participant = ConversationParticipant::query()
            ->where('conversation_id', $this->conversation->id)
            ->where('user_id', $this->participantX->id)
            ->first();

        $participant->last_read_message_id = $this->messages['m1']->id;
        $participant->save();

        $this->getJson($this->messagesUri($this->community, $this->conversation), $this->token($this->participantX))
            ->assertStatus(200);

        $this->assertSame(
            $this->messages['m1']->id,
            $participant->fresh()->last_read_message_id,
            'GET must not advance the read cursor.',
        );
    }

    public function test_get_messages_does_not_mutate_legacy_read_fields(): void
    {
        $this->messages['m2']->is_read = false;
        $this->messages['m2']->read_at = null;
        $this->messages['m2']->save();

        $this->getJson($this->messagesUri($this->community, $this->conversation), $this->token($this->participantX))
            ->assertStatus(200);

        $fresh = Message::query()->whereKey($this->messages['m2']->id)->first();

        $this->assertFalse((bool) $fresh->is_read);
        $this->assertNull($fresh->read_at);
    }

    public function test_no_message_count_or_total_queries_leak_read_state_rows(): void
    {
        // Sanity: message history does not write any rows to read-state tables.
        $beforeParticipants = DB::table('conversation_participants')->count();

        $this->getJson($this->messagesUri($this->community, $this->conversation), $this->token($this->participantX))
            ->assertStatus(200);

        $this->assertSame($beforeParticipants, DB::table('conversation_participants')->count());
    }
}
