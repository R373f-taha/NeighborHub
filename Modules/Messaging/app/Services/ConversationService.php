<?php

declare(strict_types=1);

namespace Modules\Messaging\app\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Models\ConversationParticipant;
use Modules\Messaging\app\Models\Message;


class ConversationService
{
    public const int DEFAULT_PER_PAGE = 15;
    public const int MAX_PER_PAGE = 100;

    public function __construct(private ConversationAccessService $access) {}

    /**
     * Paginated list of the caller's ACTIVE conversations within a Community.
     *
     * DB-scoped by community_id + an active participant row for the caller.
     * Never loads all Community conversations to filter in PHP. Deterministic
     * order: conversations.created_at DESC, conversations.id DESC.
     */
    public function listForParticipant(Community $community, User $user, int $perPage): LengthAwarePaginator
    {
        return Conversation::query()
            ->where('community_id', $community->id)
            ->whereHas('participants', $this->activeParticipantScope($user))
            ->with([
                'participants' => fn ($q) => $q->whereNull('left_at'),
                'participants.user:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->clampPerPage($perPage));
    }

    /**
     * Resolve a single Conversation ONLY when:
     *   - it exists with the requested id;
     *   - conversation.community_id = route Community id;
     *   - the caller has an active participant row (left_at IS NULL).
     *
     * Any failure returns null -> caller renders a privacy-safe 404. This is
     * the primary privacy authority; it is NOT a findOrFail()+policy flow.
     */
    public function resolveForParticipant(Community $community, User $user, int $conversationId): ?Conversation
    {
        return Conversation::query()
            ->where('id', $conversationId)
            ->where('community_id', $community->id)
            ->whereHas('participants', $this->activeParticipantScope($user))
            ->first();
    }

    /**
     * Paginated message history for an already-resolved Conversation, scoped
     * to the caller's joined_at (MSG-2 privacy baseline: a participant reads
     * messages created at or after their joined_at; never earlier).
     *
     * Ordered created_at DESC, id DESC (deterministic). Sender is eager-loaded
     * as minimal id/name only. Side-effect free.
     */
    public function messagesForParticipant(
        Conversation $conversation,
        ConversationParticipant $participant,
        int $perPage,
    ): LengthAwarePaginator {
        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('created_at', '>=', $participant->joined_at)
            ->with(['sender:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->clampPerPage($perPage));
    }

    public function clampPerPage(int $perPage): int
    {
        return max(1, min($perPage, self::MAX_PER_PAGE));
    }

    /**
     * Closure reused by both list and resolve scopes: an active participant
     * row (left_at IS NULL) for the caller.
     *
     * @return \Closure(Builder): void
     */
    private function activeParticipantScope(User $user): \Closure
    {
        return static function (Builder $q) use ($user): void {
            $q->where('user_id', $user->id)
                ->whereNull('left_at');
        };
    }
}
