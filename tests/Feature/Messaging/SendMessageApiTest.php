<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Modules\Auth\app\Enums\UserRole;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Models\ConversationParticipant;
use Modules\Messaging\app\Models\Message;
use Tests\TestCase;

/**
 * Core Send Message API: privacy, participant-domain authorization, active
 * Conversation requirement, validation, server-authoritative fields, legacy
 * read-state non-authoritativeness, zero cursor side effects, history.
 *
 * The minimal contract is type-agnostic: any ACTIVE participant of an ACTIVE
 * Conversation may send. There is no Residency coupling, no recipient
 * eligibility scan, and no idempotency key.
 *
 * LOCAL ONLY / NOT staged.
 */
class SendMessageApiTest extends TestCase
{
    use RefreshDatabase;
    use ConversationTestHelpers;

    private Community $communityA;
    private Community $communityB;
    private User $userA;
    private User $userB;
    private User $superAdmin;
    private User $manager;
    private Conversation $directConversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->communityA = $this->makeCommunity('A');
        $this->communityB = $this->makeCommunity('B');
        $unitA = $this->makeUnit($this->communityA, 'A1');

        $this->userA = User::factory()->resident()->create(['is_active' => true]);
        $this->makeResident($this->communityA, $unitA, $this->userA);

        $this->userB = User::factory()->resident()->create(['is_active' => true]);
        $this->makeResident($this->communityA, $unitA, $this->userB, 'active', 'tenant');

        $this->directConversation = $this->makeConversation($this->communityA);
        $this->makeParticipant($this->directConversation, $this->userA);
        $this->makeParticipant($this->directConversation, $this->userB);

        $this->superAdmin = User::factory()->superAdmin()->create(['is_active' => true]);
        $this->manager = User::factory()->manager()->create(['is_active' => true]);
    }

    private function send(int $byUserOffset, array $overrides = []): TestResponse
    {
        $users = ['a' => $this->userA, 'b' => $this->userB];
        $user = array_values($users)[$byUserOffset] ?? $this->userA;

        $payload = array_merge(['content' => 'Hello'], $overrides);

        return $this->postJson($this->messagesUri($this->communityA, $this->directConversation), $payload, $this->token($user));
    }

    // ── Auth / Privacy ──

    public function test_anonymous_send_is_unauthenticated(): void
    {
        $this->postJson($this->messagesUri($this->communityA, $this->directConversation), ['content' => 'Hi'])
            ->assertStatus(401);
    }

    public function test_active_direct_participant_can_send(): void
    {
        $this->send(0, ['content' => 'Hello there'])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Message sent successfully.');
    }

    public function test_non_participant_send_is_privacy_safe_404(): void
    {
        $this->postJson($this->messagesUri($this->communityA, $this->directConversation), ['content' => 'Hi'], $this->token($this->superAdmin))
            ->assertStatus(404);

        $this->postJson($this->messagesUri($this->communityA, $this->directConversation), ['content' => 'Hi'], $this->token($this->manager))
            ->assertStatus(404);
    }

    public function test_left_actor_send_is_404(): void
    {
        ConversationParticipant::query()
            ->where('conversation_id', $this->directConversation->id)
            ->where('user_id', $this->userA->id)
            ->update(['left_at' => now()]);

        $this->send(0)->assertStatus(404);
        $this->assertSame(0, Message::count());
    }

    public function test_wrong_community_send_is_404(): void
    {
        $this->postJson($this->messagesUri($this->communityB, $this->directConversation), ['content' => 'Hi'], $this->token($this->userA))
            ->assertStatus(404);
    }

    public function test_missing_conversation_send_is_404(): void
    {
        $this->postJson($this->messagesUri($this->communityA, 999999), ['content' => 'Hi'], $this->token($this->userA))
            ->assertStatus(404);
    }

    public function test_users_role_drift_gives_no_bypass(): void
    {
        $drift = User::create([
            'name' => 'Drift', 'email' => 'drift.msg@example.test', 'password' => 'password',
            'role' => UserRole::SuperAdmin, 'phone' => '123', 'avatar' => 'x',
            'is_active' => true, 'email_verified_at' => now(),
        ]);

        $this->postJson($this->messagesUri($this->communityA, $this->directConversation), ['content' => 'Hi'], $this->token($drift))
            ->assertStatus(404);
    }

    // ── Conversation status ──

    public function test_archived_conversation_send_is_422(): void
    {
        $this->directConversation->status = 'archived';
        $this->directConversation->save();
        $this->send(0)->assertStatus(422);
    }

    public function test_closed_conversation_send_is_422(): void
    {
        $this->directConversation->status = 'closed';
        $this->directConversation->save();
        $this->send(0)->assertStatus(422);
    }

    public function test_group_conversation_send_is_supported_201(): void
    {
        // Minimal type-agnostic contract: an active participant of an active
        // group Conversation may send (no Group product workflow required).
        $group = $this->makeConversation($this->communityA);
        $group->type = 'group';
        $group->save();
        $this->makeParticipant($group, $this->userA);
        $this->makeParticipant($group, $this->userB);

        $this->postJson($this->messagesUri($this->communityA, $group), ['content' => 'Hi'], $this->token($this->userA))
            ->assertStatus(201);
    }

    public function test_appeal_conversation_send_is_supported_201(): void
    {
        // No original requirement restricts appeal sends; the core contract is
        // type-agnostic. An active participant of an active appeal may send.
        $appeal = $this->makeConversation($this->communityA);
        $appeal->type = 'appeal';
        $appeal->save();
        $this->makeParticipant($appeal, $this->userA);
        $this->makeParticipant($appeal, $this->userB);

        $this->postJson($this->messagesUri($this->communityA, $appeal), ['content' => 'Hi'], $this->token($this->userA))
            ->assertStatus(201);
    }

    public function test_payload_type_status_are_ignored(): void
    {
        $this->send(0, ['type' => 'group', 'status' => 'closed'])->assertStatus(201);
        $this->assertSame('direct', $this->directConversation->fresh()->type);
        $this->assertSame('active', $this->directConversation->fresh()->status);
    }

    // ── Participant-domain authorization (no Residency coupling) ──

    public function test_actor_suspended_residency_does_not_block_send(): void
    {
        // Messaging authority is participant-domain based; Resident status is
        // not consulted by the core send contract.
        Resident::query()->where('user_id', $this->userA->id)->update(['status' => 'suspended']);
        $this->send(0)->assertStatus(201);
    }

    public function test_actor_inactive_user_is_forbidden(): void
    {
        // EnsureUserIsActive middleware guards the route for inactive users.
        $this->userA->is_active = false;
        $this->userA->save();
        $this->send(0)->assertStatus(403);
        $this->assertSame(0, Message::count());
    }

    public function test_recipient_suspended_residency_does_not_block_send(): void
    {
        Resident::query()->where('user_id', $this->userB->id)->update(['status' => 'suspended']);
        $this->send(0)->assertStatus(201);
    }

    public function test_recipient_inactive_user_does_not_block_send(): void
    {
        $this->userB->is_active = false;
        $this->userB->save();
        $this->send(0)->assertStatus(201);
    }

    // ── Validation ──

    public function test_content_is_required(): void
    {
        $this->send(0, ['content' => null])->assertStatus(422)->assertJsonValidationErrors('content');
    }

    public function test_normal_bounded_text_succeeds(): void
    {
        $this->send(0, ['content' => str_repeat('a', 1000)])->assertStatus(201);
    }

    public function test_16000_four_byte_chars_succeeds(): void
    {
        // U+1F600 is a 4-byte UTF-8 character. 16000 of them = 64000 bytes < 65535.
        $this->send(0, ['content' => str_repeat("\u{1F600}", 16000)])->assertStatus(201);
    }

    public function test_oversized_multibyte_content_is_422_not_mysql_error(): void
    {
        // 16001 four-byte chars exceeds the byte budget and must fail validation (422),
        // never reach MySQL error 1406.
        $this->send(0, ['content' => str_repeat("\u{1F600}", 16001)])
            ->assertStatus(422)->assertJsonValidationErrors('content');
        $this->assertSame(0, Message::count());
    }

    // ── Server authority ──

    public function test_message_fields_are_server_authoritative(): void
    {
        $resp = $this->send(0, [
            'sender_id' => $this->userB->id,            // ignored
            'conversation_id' => 999999,                // ignored
            'community_id' => $this->communityB->id,    // ignored
            'is_read' => true,                           // ignored
            'read_at' => now()->toIso8601String(),      // ignored
        ])->assertStatus(201);

        $msg = Message::first();
        $this->assertSame($this->userA->id, $msg->sender_id);
        $this->assertSame($this->directConversation->id, $msg->conversation_id);
        $this->assertSame($this->communityA->id, $msg->community_id);
        $this->assertSame($resp->json('data.id'), $msg->id);
    }

    // ── Legacy read state ──

    public function test_new_message_legacy_read_fields_remain_defaults_and_unexposed(): void
    {
        $resp = $this->send(0)->assertStatus(201);
        $msg = Message::first();

        $this->assertFalse((bool) $msg->is_read);
        $this->assertNull($msg->read_at);
        $this->assertArrayNotHasKey('is_read', $resp->json('data'));
        $this->assertArrayNotHasKey('read_at', $resp->json('data'));
    }

    public function test_send_does_not_advance_last_read_message_id(): void
    {
        $participant = ConversationParticipant::query()
            ->where('conversation_id', $this->directConversation->id)
            ->where('user_id', $this->userA->id)
            ->first();
        $before = $participant->last_read_message_id;

        $this->send(0)->assertStatus(201);

        $this->assertSame($before, $participant->fresh()->last_read_message_id);
    }

    // ── History integration ──

    public function test_sent_message_appears_in_history_with_deterministic_order(): void
    {
        $this->send(0, ['content' => 'first'])->assertStatus(201);
        $this->send(1, ['content' => 'second'])->assertStatus(201);

        $resp = $this->getJson($this->messagesUri($this->communityA, $this->directConversation), $this->token($this->userA))
            ->assertStatus(200);

        $contents = array_column($resp->json('data'), 'content');
        $this->assertSame(['second', 'first'], $contents); // created_at DESC, id DESC
    }

    public function test_history_get_after_send_has_zero_read_state_side_effects(): void
    {
        $this->send(0, ['content' => 'one'])->assertStatus(201);
        $participant = ConversationParticipant::query()
            ->where('conversation_id', $this->directConversation->id)
            ->where('user_id', $this->userA->id)
            ->first();
        $before = $participant->last_read_message_id;

        $this->getJson($this->messagesUri($this->communityA, $this->directConversation), $this->token($this->userA))->assertStatus(200);

        $this->assertSame($before, $participant->fresh()->last_read_message_id);
    }

    // ── Privacy of response ──

    public function test_response_exposes_only_safe_sender_fields(): void
    {
        $resp = $this->send(0)->assertStatus(201);

        $data = $resp->json('data');
        $this->assertSame(['id', 'name'], array_keys($data['sender']));
        foreach (['is_read', 'read_at', 'community_id', 'client_message_id'] as $field) {
            $this->assertArrayNotHasKey($field, $data);
        }
        foreach (['email', 'phone', 'role'] as $field) {
            $this->assertArrayNotHasKey($field, $data['sender']);
        }
    }
}
