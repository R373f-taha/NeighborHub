<?php

declare(strict_types=1);

namespace Modules\Messaging\app\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Messaging\app\Exceptions\InvalidConversationReadCursorException;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Models\ConversationParticipant;
use Modules\Messaging\app\Models\Message;


class ConversationReadStateService
{
    /**
     * Advance the actor's read cursor through an explicit Message, then return
     * the authoritative read state for the dedicated Mark Read response.
     *
     * @return array{conversation_id: int, last_read_message_id: ?int, unread_count: int}
     */
    public function markRead(User $actor, Community $community, int $conversationId, int $messageId): array
    {
        return DB::transaction(function () use ($actor, $community, $conversationId, $messageId): array {
            // Privacy-scope + serialize on the Conversation row (FOR UPDATE).
            $conversation = Conversation::query()
                ->where('id', $conversationId)
                ->where('community_id', $community->id)
                ->whereHas('participants', function (Builder $q) use ($actor): void {
                    $q->where('user_id', $actor->id)->whereNull('left_at');
                })
                ->lockForUpdate()
                ->first();

            if ($conversation === null) {
                abort(404);
            }

            // The actor's active participant row is the only mutable
            // authorization state relevant here, so lock it under the
            // Conversation lock.
            $participant = ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $actor->id)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->first();

            if ($participant === null) {
                abort(404);
            }

            // Resolve the target with same-conversation + joined_at visibility
            // (never a global findOrFail) so missing / wrong-conversation /
            // pre-join are indistinguishable 404s.
            $target = Message::query()
                ->where('id', $messageId)
                ->where('conversation_id', $conversation->id)
                ->where('created_at', '>=', $participant->joined_at)
                ->first();

            if ($target === null) {
                abort(404);
            }

            // The FK cannot enforce same-conversation identity; if the stored
            // cursor points elsewhere, fail closed rather than repair or
            // compare cross-conversation ids.
            if ($participant->last_read_message_id !== null) {
                $cursorValid = Message::query()
                    ->where('id', $participant->last_read_message_id)
                    ->where('conversation_id', $conversation->id)
                    ->exists();

                if (! $cursorValid) {
                    throw new InvalidConversationReadCursorException();
                }
            }

            // Monotonic advance only; same/older target is a no-op.
            if ($participant->last_read_message_id === null || $target->id > $participant->last_read_message_id) {
                $participant->last_read_message_id = $target->id;
                $participant->save();
            }

            return [
                'conversation_id' => (int) $conversation->id,
                'last_read_message_id' => $participant->last_read_message_id !== null ? (int) $participant->last_read_message_id : null,
                'unread_count' => $this->unreadCount((int) $conversation->id, $participant),
            ];
        });
    }

    /**
     * THE single unread definition for an active participant in one
     * Conversation. Counts OTHER-participant messages (sender_id != own),
     * visible at/after joined_at, and (when a cursor exists) newer than it.
     * Never uses legacy is_read/read_at.
     */
    public function unreadCount(int $conversationId, ConversationParticipant $participant): int
    {
        return (int) Message::query()
            ->where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $participant->user_id)
            ->where('created_at', '>=', $participant->joined_at)
            ->when(
                $participant->last_read_message_id !== null,
                fn (Builder $q) => $q->where('id', '>', $participant->last_read_message_id),
            )
            ->count();
    }

    /**
     * Unread count for the actor in one already-resolved Conversation (show).
     * Returns 0 if the actor has no active participant row.
     */
    public function unreadCountForUser(Conversation $conversation, User $user): int
    {
        $participant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();

        if ($participant === null) {
            return 0;
        }

        return $this->unreadCount((int) $conversation->id, $participant);
    }

    /**
     * Attach a derived unread_count to every Conversation on a list page using
     * ONE set-based query (no per-conversation COUNT -> no N+1). Each
     * conversation's `unread_count` runtime attribute is consumed by
     * ConversationResource.
     */
    public function attachUnreadCounts(LengthAwarePaginator $paginator, User $actor): LengthAwarePaginator
    {
        $conversations = $paginator->getCollection();
        $conversationIds = $conversations->pluck('id')->all();

        if ($conversationIds === []) {
            return $paginator;
        }

        $counts = DB::table('messages')
            ->join('conversation_participants', 'conversation_participants.conversation_id', '=', 'messages.conversation_id')
            ->whereIn('messages.conversation_id', $conversationIds)
            ->where('conversation_participants.user_id', $actor->id)
            ->whereNull('conversation_participants.left_at')
            ->whereColumn('messages.sender_id', '!=', 'conversation_participants.user_id')
            ->whereColumn('messages.created_at', '>=', 'conversation_participants.joined_at')
            ->where(function ($q): void {
                $q->whereNull('conversation_participants.last_read_message_id')
                    ->orWhereColumn('messages.id', '>', 'conversation_participants.last_read_message_id');
            })
            ->groupBy('messages.conversation_id')
            ->select('messages.conversation_id', DB::raw('COUNT(*) as unread'))
            ->get()
            ->keyBy('conversation_id');

        foreach ($conversations as $conversation) {
            $conversation->unread_count = (int) ($counts->get($conversation->id)?->unread ?? 0);
        }

        return $paginator;
    }
}
