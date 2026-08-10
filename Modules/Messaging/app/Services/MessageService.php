<?php

declare(strict_types=1);

namespace Modules\Messaging\app\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Models\ConversationParticipant;
use Modules\Messaging\app\Models\Message;


class MessageService
{
    private const CONVERSATION_UNAVAILABLE = 'The conversation cannot currently accept messages.';

    /**
     * Send a Message into an accessible, active Conversation.
     */
    public function send(User $actor, Community $community, int $conversationId, string $content): Message
    {
        return DB::transaction(function () use ($actor, $community, $conversationId, $content): Message {
            // Privacy-scoped Conversation serialization lock. Scoped by id +
            // community_id + actor active participation, so a non-participant /
            // wrong-community / left-actor resolves nothing and never locks an
            // arbitrary private Conversation.
            $conversation = $this->lockConversation($actor, $community, $conversationId);

            if ($conversation === null) {
                abort(404);
            }

            if ($conversation->status !== 'active') {
                throw ValidationException::withMessages(['conversation' => self::CONVERSATION_UNAVAILABLE]);
            }

            // Recheck the actor's active participation under the Conversation
            // lock so a concurrent leave cannot race this write.
            $actorParticipant = ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $actor->id)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->first();

            if ($actorParticipant === null) {
                abort(404);
            }

            // Server-authoritative fields only; legacy is_read/read_at keep DB
            // defaults and last_read_message_id is not advanced.
            return Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $actor->id,
                'community_id' => $conversation->community_id,
                'content' => $content,
            ]);
        });
    }

    /**
     * Privacy-scoped Conversation row lock. Resolves ONLY when the
     * conversation exists, belongs to the route Community, and the actor has
     * an active participant row. FOR UPDATE makes the Conversation the
     * per-conversation serialization point shared with Mark Read.
     */
    private function lockConversation(User $actor, Community $community, int $conversationId): ?Conversation
    {
        return Conversation::query()
            ->where('id', $conversationId)
            ->where('community_id', $community->id)
            ->whereHas('participants', function (Builder $q) use ($actor): void {
                $q->where('user_id', $actor->id)
                    ->whereNull('left_at');
            })
            ->lockForUpdate()
            ->first();
    }
}
