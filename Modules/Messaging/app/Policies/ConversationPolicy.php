<?php

declare(strict_types=1);

namespace Modules\Messaging\app\Policies;

use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Services\ConversationAccessService;


class ConversationPolicy
{
    public function __construct(private ConversationAccessService $access) {}


    public function viewAny(User $user, Community $community): bool
    {
        return true;
    }

    /**
     * Viewing message history. Re-verify current active participation.
     */
    public function viewMessages(User $user, Conversation $conversation): bool
    {
        return $this->access->isActiveParticipant($user, $conversation);
    }

   
    public function sendMessage(User $user, Community $community): bool
    {
        return true;
    }

   
    public function markRead(User $user, Community $community): bool
    {
        return true;
    }
}
