<?php

declare(strict_types=1);

namespace Modules\Messaging\app\Services;

use Modules\Auth\app\Models\User;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Models\ConversationParticipant;


class ConversationAccessService
{
    /**
     * The caller's ACTIVE ConversationParticipant row for this conversation
     * (left_at IS NULL), or null when they are not a current participant.
     */
    public function findActiveParticipant(User $user, Conversation $conversation): ?ConversationParticipant
    {
        return $conversation->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();
    }

    /**
     * Whether the caller is a CURRENT participant (left_at IS NULL) of the
     * conversation. joined_at is participant metadata only; it does not
     * affect membership status.
     */
    public function isActiveParticipant(User $user, Conversation $conversation): bool
    {
        return $this->findActiveParticipant($user, $conversation) !== null;
    }
}
