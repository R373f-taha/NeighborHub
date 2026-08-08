<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Auth\app\Enums\UserRole;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Models\ConversationParticipant;
use Modules\Messaging\app\Models\Message;
use Tests\TestCase;

/**
 * Core Mark Read API + Derived Unread State: participant-private read-state
 * mutation, same-conversation + joined_at target enforcement, monotonic
 * cursor, corrupt-cursor fail-safe, derived unread_count, GET side-effect
 * freedom, and read-access (non-residency) lifecycle semantics.
 *
 * Unread count is verified through the LIST response (and the Mark Read
 * response), since there is no standalone show/unread endpoint.
 *
 * LOCAL ONLY / NOT staged.
 */
class MarkReadApiTest extends TestCase
{
    use RefreshDatabase;
    use ConversationTestHelpers;

    private Community $communityA;
    private Community $communityB;
    private User $userA;
    private User $userB;
    private Unit $unitA;
    private Conversation $conversation;
    private Message $m1;
    private Message $m2;
    private Message $m3;

    protected function setUp(): void
    {
        parent::setUp();

        $base = Carbon::create(2026, 1, 1, 12, 0, 0);

        $this->communityA = $this->makeCommunity('A');
        $this->communityB = $this->makeCommunity('B');
        $this->unitA = $this->makeUnit($this->communityA, 'A1');

        $this->userA = User::factory()->resident()->create(['is_active' => true]);
        $this->makeResident($this->communityA, $this->unitA, $this->userA);

        $this->userB = User::factory()->resident()->create(['is_active' => true]);
        $this->makeResident($this->communityA, $this->unitA, $this->userB, 'active', 'tenant');

        $this->conversation = $this->makeConversation($this->communityA, $base);
        $this->makeParticipant($this->conversation, $this->userA, $base);
        $this->makeParticipant($this->conversation, $this->userB, $base);

        // Incoming messages (from B) visible to A (>= joined_at).
        $this->m1 = $this->makeMessage($this->conversation, $this->communityA, $this->userB, $base->copy()->addSeconds(60));
        $this->m2 = $this->makeMessage($this->conversation, $this->communityA, $this->userB, $base->copy()->addSeconds(120));
        $this->m3 = $this->makeMessage($this->conversation, $this->communityA, $this->userB, $base->copy()->addSeconds(180));
    }

    private function markReadAs(User $user, Community $community, Conversation $conversation, int $messageId): \Illuminate\Testing\TestResponse
    {
        return $this->patchJson($this->readUri($community, $conversation), ['message_id' => $messageId], $this->token($user));
    }

    private function actorParticipant(): ConversationParticipant
    {
        return ConversationParticipant::query()
            ->where('conversation_id', $this->conversation->id)
            ->where('user_id', $this->userA->id)
            ->first();
    }

    /** The conversation's entry on the LIST page (carries derived unread_count). */
    private function listEntry(Community $community, Conversation $conversation): ?array
    {
        $resp = $this->getJson($this->indexUri($community), $this->token($this->userA))->assertStatus(200);

        return collect($resp->json('data'))->firstWhere('id', $conversation->id);
    }

    private function unreadFromList(Community $community, Conversation $conversation): int
    {
        $entry = $this->listEntry($community, $conversation);

        return $entry !== null ? (int) $entry['unread_count'] : -1;
    }

    // ── Routing / Auth ──

    public function test_mark_read_route_is_registered(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::getRoutes()->hasNamedRoute('api.v1.communities.conversations.read.update'));
    }

    public function test_anonymous_mark_read_is_unauthenticated(): void
    {
        $this->patchJson($this->readUri($this->communityA, $this->conversation), ['message_id' => $this->m1->id])
            ->assertStatus(401);
    }

    // ── Privacy ──

    public function test_active_participant_can_mark_read(): void
    {
        $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m2->id)
            ->assertStatus(200)
            ->assertJsonPath('message', 'Conversation read state updated successfully.')
            ->assertJsonPath('data.conversation_id', $this->conversation->id)
            ->assertJsonPath('data.last_read_message_id', $this->m2->id);
    }

    public function test_non_participant_mark_read_is_privacy_safe_404(): void
    {
        $super = User::factory()->superAdmin()->create(['is_active' => true]);
        $manager = User::factory()->manager()->create(['is_active' => true]);

        $this->markReadAs($super, $this->communityA, $this->conversation, (int) $this->m1->id)->assertStatus(404);
        $this->markReadAs($manager, $this->communityA, $this->conversation, (int) $this->m1->id)->assertStatus(404);
    }

    public function test_left_participant_mark_read_is_404(): void
    {
        ConversationParticipant::query()
            ->where('conversation_id', $this->conversation->id)
            ->where('user_id', $this->userA->id)
            ->update(['left_at' => now()]);

        $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m1->id)->assertStatus(404);
    }

    public function test_wrong_community_mark_read_is_404(): void
    {
        $this->markReadAs($this->userA, $this->communityB, $this->conversation, (int) $this->m1->id)->assertStatus(404);
    }

    public function test_missing_conversation_mark_read_is_404(): void
    {
        $this->patchJson($this->readUri($this->communityA, 999999), ['message_id' => $this->m1->id], $this->token($this->userA))
            ->assertStatus(404);
    }

    public function test_users_role_drift_gives_no_access(): void
    {
        $drift = User::create([
            'name' => 'Drift', 'email' => 'drift.read@example.test', 'password' => 'password',
            'role' => UserRole::SuperAdmin, 'phone' => '123', 'avatar' => 'x',
            'is_active' => true, 'email_verified_at' => now(),
        ]);

        $this->markReadAs($drift, $this->communityA, $this->conversation, (int) $this->m1->id)->assertStatus(404);
    }

    // ── No residency coupling / read-access lifecycle ──

    public function test_actor_resident_suspended_still_allows_mark_read(): void
    {
        Resident::query()->where('user_id', $this->userA->id)->update(['status' => 'suspended']);

        $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m2->id)->assertStatus(200);
    }

    public function test_recipient_resident_suspended_still_allows_mark_read(): void
    {
        Resident::query()->where('user_id', $this->userB->id)->update(['status' => 'suspended']);

        $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m2->id)->assertStatus(200);
    }

    public function test_archived_conversation_mark_read_allowed(): void
    {
        $this->conversation->status = 'archived';
        $this->conversation->save();
        $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m2->id)->assertStatus(200);
    }

    public function test_closed_conversation_mark_read_allowed(): void
    {
        $this->conversation->status = 'closed';
        $this->conversation->save();
        $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m2->id)->assertStatus(200);
    }

    public function test_group_conversation_mark_read_allowed(): void
    {
        $this->conversation->type = 'group';
        $this->conversation->save();
        $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m2->id)->assertStatus(200);
    }

    public function test_appeal_conversation_mark_read_allowed(): void
    {
        $this->conversation->type = 'appeal';
        $this->conversation->save();
        $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m2->id)->assertStatus(200);
    }

    // ── Target resolution ──

    public function test_missing_message_mark_read_is_404(): void
    {
        $this->markReadAs($this->userA, $this->communityA, $this->conversation, 999999)->assertStatus(404);
    }

    public function test_message_from_another_conversation_is_404(): void
    {
        $other = $this->makeConversation($this->communityA);
        $this->makeParticipant($other, $this->userA);
        $otherMsg = $this->makeMessage($other, $this->communityA, $this->userB);

        $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $otherMsg->id)->assertStatus(404);
        $this->assertNull($this->actorParticipant()->last_read_message_id);
    }

    public function test_pre_join_message_is_404(): void
    {
        $base = Carbon::create(2026, 2, 2, 9, 0, 0);
        $conv = $this->makeConversation($this->communityA, $base);
        // Actor joins AFTER an existing message.
        $this->makeParticipant($conv, $this->userA, $base->copy()->addSeconds(120));
        $this->makeParticipant($conv, $this->userB, $base);
        $preJoin = $this->makeMessage($conv, $this->communityA, $this->userB, $base);

        $this->markReadAs($this->userA, $this->communityA, $conv, (int) $preJoin->id)->assertStatus(404);
    }

    public function test_visible_message_at_joined_at_boundary_is_allowed(): void
    {
        $base = Carbon::create(2026, 2, 3, 9, 0, 0);
        $conv = $this->makeConversation($this->communityA, $base);
        $boundary = $base->copy()->addSeconds(120);
        $this->makeParticipant($conv, $this->userA, $boundary);
        $this->makeParticipant($conv, $this->userB, $base);
        $atBoundary = $this->makeMessage($conv, $this->communityA, $this->userB, $boundary);

        $this->markReadAs($this->userA, $this->communityA, $conv, (int) $atBoundary->id)->assertStatus(200);
    }

    // ── Monotonicity ──

    public function test_cursor_advances_from_null(): void
    {
        $this->assertNull($this->actorParticipant()->last_read_message_id);

        $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m1->id)
            ->assertJsonPath('data.last_read_message_id', $this->m1->id);

        $this->assertSame((int) $this->m1->id, (int) $this->actorParticipant()->last_read_message_id);
    }

    public function test_cursor_advances_forward(): void
    {
        $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m1->id);
        $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m3->id)
            ->assertJsonPath('data.last_read_message_id', $this->m3->id);

        $this->assertSame((int) $this->m3->id, (int) $this->actorParticipant()->last_read_message_id);
    }

    public function test_same_target_is_idempotent(): void
    {
        $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m2->id);
        $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m2->id)
            ->assertStatus(200)
            ->assertJsonPath('data.last_read_message_id', $this->m2->id);

        $this->assertSame((int) $this->m2->id, (int) $this->actorParticipant()->last_read_message_id);
    }

    public function test_older_target_does_not_regress_cursor(): void
    {
        $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m3->id);
        $resp = $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m1->id)
            ->assertStatus(200);

        // Returns the actual authoritative (unchanged) cursor.
        $this->assertSame((int) $this->m3->id, (int) $resp->json('data.last_read_message_id'));
        $this->assertSame((int) $this->m3->id, (int) $this->actorParticipant()->last_read_message_id);
    }

    // ── Existing cursor corruption ──

    public function test_corrupt_cross_conversation_cursor_is_detected(): void
    {
        $other = $this->makeConversation($this->communityA);
        $this->makeParticipant($other, $this->userB);
        $foreignMessage = $this->makeMessage($other, $this->communityA, $this->userB);

        $actorP = $this->actorParticipant();
        $actorP->last_read_message_id = $foreignMessage->id; // corrupt
        $actorP->save();

        $resp = $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m2->id);
        $this->assertSame(500, $resp->status()); // fail-safe; never silently repaired

        // Cursor unchanged; no repair.
        $this->assertSame((int) $foreignMessage->id, (int) $this->actorParticipant()->fresh()->last_read_message_id);
    }

    // ── Read-state mutation scope ──

    public function test_only_last_read_message_id_changes_legacy_and_conversation_untouched(): void
    {
        $this->m1->is_read = false;
        $this->m1->read_at = null;
        $this->m1->save();
        $conversationUpdatedAt = $this->conversation->updated_at;

        $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m2->id)->assertStatus(200);

        $this->assertSame((int) $this->m2->id, (int) $this->actorParticipant()->last_read_message_id);

        $fresh = Message::find($this->m1->id);
        $this->assertFalse((bool) $fresh->is_read);
        $this->assertNull($fresh->read_at);

        // Conversation row not mutated.
        $this->assertEquals($conversationUpdatedAt, $this->conversation->fresh()->updated_at);
    }

    // ── Unread definition ──

    public function test_null_cursor_counts_all_visible_incoming(): void
    {
        $this->assertSame(3, $this->unreadFromList($this->communityA, $this->conversation));
    }

    public function test_own_messages_do_not_count_unread(): void
    {
        $this->makeMessage($this->conversation, $this->communityA, $this->userA); // own message by A

        $this->assertSame(3, $this->unreadFromList($this->communityA, $this->conversation)); // still only B's 3
    }

    public function test_cursor_excludes_read_incoming_and_counts_remaining(): void
    {
        $resp = $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m1->id);
        $this->assertSame(2, $resp->json('data.unread_count')); // m2, m3 remain
    }

    public function test_no_messages_yields_zero_unread(): void
    {
        $empty = $this->makeConversation($this->communityA);
        $this->makeParticipant($empty, $this->userA);

        $this->assertSame(0, $this->unreadFromList($this->communityA, $empty));
    }

    public function test_pre_join_incoming_messages_excluded_from_unread(): void
    {
        $base = Carbon::create(2026, 3, 3, 9, 0, 0);
        $conv = $this->makeConversation($this->communityA, $base);
        $this->makeParticipant($conv, $this->userA, $base->copy()->addSeconds(120));
        $this->makeParticipant($conv, $this->userB, $base);
        $this->makeMessage($conv, $this->communityA, $this->userB, $base); // pre-join
        $this->makeMessage($conv, $this->communityA, $this->userB, $base->copy()->addSeconds(180)); // post-join

        $this->assertSame(1, $this->unreadFromList($this->communityA, $conv));
    }

    public function test_list_and_markread_share_one_unread_definition(): void
    {
        $listCount = $this->unreadFromList($this->communityA, $this->conversation);

        $mark = $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m1->id)->assertStatus(200);
        $markCount = $mark->json('data.unread_count');

        $this->assertSame(3, $listCount);
        $this->assertSame($listCount - 1, $markCount); // marking m1 read reduces by one
    }

    // ── GET side-effect freedom ──

    public function test_get_list_and_messages_do_not_mutate_read_state(): void
    {
        $this->getJson($this->indexUri($this->communityA), $this->token($this->userA))->assertStatus(200);
        $this->getJson($this->messagesUri($this->communityA, $this->conversation), $this->token($this->userA))->assertStatus(200);

        $this->assertNull($this->actorParticipant()->fresh()->last_read_message_id);
    }

    public function test_get_messages_does_not_advance_cursor(): void
    {
        $this->getJson($this->messagesUri($this->communityA, $this->conversation), $this->token($this->userA))->assertStatus(200);
        $this->assertNull($this->actorParticipant()->fresh()->last_read_message_id);
    }

    // ── Privacy output ──

    public function test_mark_read_response_exposes_only_actor_read_state_fields(): void
    {
        $resp = $this->markReadAs($this->userA, $this->communityA, $this->conversation, (int) $this->m2->id)->assertStatus(200);

        $data = $resp->json('data');
        $this->assertSame(['conversation_id', 'last_read_message_id', 'unread_count'], array_keys($data));
    }

    public function test_conversation_participants_still_do_not_expose_read_cursor(): void
    {
        $entry = $this->listEntry($this->communityA, $this->conversation);

        foreach ($entry['participants'] as $p) {
            $this->assertSame(['id', 'name'], array_keys($p));
        }
        $this->assertArrayNotHasKey('last_read_message_id', $entry);
    }
}
