<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Models\ConversationParticipant;
use Modules\Messaging\app\Models\Message;
use Modules\Messaging\Database\Factories\ConversationParticipantFactory;
use Modules\Messaging\Database\Factories\ConversationFactory;
use Modules\Messaging\Database\Factories\MessageFactory;
use Tests\TestCase;

/**
 * MSG-1 Messaging Read-State & Schema Foundation.
 *
 * Proves the database/read-state model required before any Messaging API
 * controller exists. No send/mark-read/group/attachment logic is exercised.
 *
 * LOCAL ONLY / NOT staged.
 */
class MessagingReadStateFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_read_message_id_column_exists_and_is_nullable(): void
    {
        $this->assertTrue(
            Schema::hasColumn('conversation_participants', 'last_read_message_id'),
            'conversation_participants.last_read_message_id must exist.',
        );

        // Nullable: a freshly inserted participant must default to NULL.
        $community = $this->community();
        $conversation = $this->conversation($community);
        $user = User::factory()->create();

        $participant = ConversationParticipant::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);

        $this->assertNull(
            $participant->fresh()->last_read_message_id,
            'last_read_message_id must be NULL until an authoritative cursor is recorded.',
        );
    }

    public function test_foreign_key_to_messages_uses_on_delete_set_null(): void
    {
        $fks = DB::selectOne(
            "SELECT DELETE_RULE AS rule
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_NAME = 'conversation_participants_last_read_message_id_foreign'
               AND UNIQUE_CONSTRAINT_SCHEMA = ?",
            [DB::connection()->getDatabaseName()],
        );

        $this->assertNotNull($fks, 'Expected FK conversation_participants.last_read_message_id -> messages.id.');
        $this->assertSame('SET NULL', $fks->rule, 'FK must be ON DELETE SET NULL.');
    }

    public function test_different_participants_hold_independent_read_cursors(): void
    {
        $community = $this->community();
        $conversation = $this->conversation($community);

        $ali = User::factory()->create();
        $rama = User::factory()->create();
        $rahaf = User::factory()->create();

        $m1 = $this->message($conversation, $community, $ali);
        $m2 = $this->message($conversation, $community, $rama);
        $m3 = $this->message($conversation, $community, $ali);

        $pAli = ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $ali->id]);
        $pRama = ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $rama->id]);
        $pRahaf = ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $rahaf->id]);

        // Ali read through m3, Rama through m1, Rahaf has read nothing yet.
        $this->setCursor($pAli, $m3);
        $this->setCursor($pRama, $m1);

        $this->assertSame($m3->id, $pAli->fresh()->last_read_message_id);
        $this->assertSame($m1->id, $pRama->fresh()->last_read_message_id);
        $this->assertNull($pRahaf->fresh()->last_read_message_id);

        // Derived unread counts are independent per participant.
        $this->assertSame(0, $this->unreadCount($conversation, $pAli));
        $this->assertSame(2, $this->unreadCount($conversation, $pRama));
        $this->assertSame(3, $this->unreadCount($conversation, $pRahaf));
    }

    public function test_deleting_a_message_clears_referencing_read_cursors(): void
    {
        $community = $this->community();
        $conversation = $this->conversation($community);
        $sender = User::factory()->create();

        $message = $this->message($conversation, $community, $sender);
        $user = User::factory()->create();

        $participant = ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
        $this->setCursor($participant, $message);
        $this->assertSame($message->id, $participant->fresh()->last_read_message_id);

        $message->delete();

        $this->assertNull(
            $participant->fresh()->last_read_message_id,
            'ON DELETE SET NULL must null the cursor when the referenced message is removed.',
        );
    }

    public function test_last_read_message_relation_is_type_safe_and_not_eager(): void
    {
        $community = $this->community();
        $conversation = $this->conversation($community);
        $sender = User::factory()->create();
        $message = $this->message($conversation, $community, $sender);

        $user = User::factory()->create();
        $participant = ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
        $this->setCursor($participant, $message);

        $loaded = $participant->fresh()->lastReadMessage;

        $this->assertNotNull($loaded);
        $this->assertSame($message->id, $loaded->id);
        $this->assertInstanceOf(Message::class, $loaded);

        // Default (no eager load) query must not join messages.
        $this->assertFalse(
            ConversationParticipant::first()->relationLoaded('lastReadMessage'),
            'lastReadMessage must not be eager-loaded by default.',
        );
    }

    public function test_last_read_message_id_is_not_mass_assignable(): void
    {
        $this->assertNotContains(
            'last_read_message_id',
            (new ConversationParticipant())->getFillable(),
            'last_read_message_id is a service-controlled cursor and must not be client mass-assignable.',
        );
    }

    /**
     * A plain FK to messages.id ONLY proves the referenced message exists.
     * It CANNOT enforce that the message belongs to the same conversation.
     * The Messaging service MUST validate same-conversation identity before
     * advancing the cursor. This test documents that DB-level gap.
     */
    public function test_fk_alone_cannot_enforce_same_conversation_cursor(): void
    {
        $community = $this->community();

        $c1 = $this->conversation($community);
        $c2 = $this->conversation($community);

        $sender = User::factory()->create();
        $mInC1 = $this->message($c1, $community, $sender);
        $mInC2 = $this->message($c2, $community, $sender);

        $user = User::factory()->create();
        $participant = ConversationParticipant::create(['conversation_id' => $c1->id, 'user_id' => $user->id]);

        // Point a C1 participant at a message that lives in C2. The FK
        // permits this because mInC2.id genuinely exists in messages.
        $this->setCursor($participant, $mInC2);

        $this->assertSame(
            $mInC2->id,
            $participant->fresh()->last_read_message_id,
            'FK allows a cross-conversation cursor; same-conversation identity '
            .'MUST be enforced by the service layer.',
        );
        $this->assertNotSame($participant->conversation_id, $mInC2->conversation_id);
    }

    public function test_message_ordering_contract_is_deterministic_by_created_at_then_id(): void
    {
        $community = $this->community();
        $conversation = $this->conversation($community);
        $sender = User::factory()->create();

        $sameMoment = now();

        $older = $this->message($conversation, $community, $sender, $sameMoment);
        $newer = $this->message($conversation, $community, $sender, $sameMoment->copy());

        $asc = Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->pluck('id');

        $this->assertSame([$older->id, $newer->id], $asc->all());

        $desc = Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->pluck('id');

        $this->assertSame([$newer->id, $older->id], $desc->all());
    }

    public function test_factories_still_resolve_and_create(): void
    {
        $this->assertInstanceOf(ConversationFactory::class, Conversation::factory());
        $this->assertInstanceOf(MessageFactory::class, Message::factory());
        $this->assertInstanceOf(ConversationParticipantFactory::class, ConversationParticipant::factory());

        // Conversation / participant smoke (nullable new column must not break them).
        $conversation = Conversation::factory()->create();
        $this->assertModelExists($conversation);

        $participant = ConversationParticipant::factory()->create();
        $this->assertModelExists($participant);
        $this->assertNull($participant->fresh()->last_read_message_id);
    }

    public function test_messaging_seeders_are_discoverable(): void
    {
        $classes = [
            \Modules\Messaging\Database\Seeders\ConversationSeeder::class,
            \Modules\Messaging\Database\Seeders\ConversationParticipantSeeder::class,
            \Modules\Messaging\Database\Seeders\MessageSeeder::class,
            \Modules\Messaging\Database\Seeders\DatabaseSeeder::class,
        ];

        foreach ($classes as $class) {
            $this->assertTrue(class_exists($class), "Messaging seeder {$class} must autoload.");
        }
    }

    // --- helpers -----------------------------------------------------------

    private function community(): Community
    {
        return Community::create([
            'name' => 'Msg Community',
            'city' => 'Beirut',
            'address' => 'Ring',
        ]);
    }

    private function conversation(Community $community): Conversation
    {
        return Conversation::create([
            'community_id' => $community->id,
            'type' => 'direct',
            'status' => 'active',
        ]);
    }

    private function message(
        Conversation $conversation,
        Community $community,
        User $sender,
        ?\Illuminate\Support\Carbon $at = null,
    ): Message {
        return Message::create([
            'conversation_id' => $conversation->id,
            'community_id' => $community->id,
            'sender_id' => $sender->id,
            'content' => 'hello',
            'created_at' => $at ?? now(),
            'updated_at' => $at ?? now(),
        ]);
    }

    private function setCursor(ConversationParticipant $participant, Message $message): void
    {
        // Direct assignment (not mass assignment): service-controlled field.
        $participant->last_read_message_id = $message->id;
        $participant->save();
    }

    /**
     * Derived unread count: messages newer than the cursor. This is never a
     * stored mutable counter; it is computed from authoritative state.
     */
    private function unreadCount(Conversation $conversation, ConversationParticipant $participant): int
    {
        return (int) Message::query()
            ->where('conversation_id', $conversation->id)
            ->when(
                $participant->last_read_message_id !== null,
                fn ($q) => $q->where('id', '>', $participant->last_read_message_id),
            )
            ->count();
    }
}
