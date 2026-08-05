<?php

declare(strict_types=1);

namespace Modules\Messaging\app\Services;

use Illuminate\Support\Facades\Log;
use Modules\Auth\app\Models\User;


class ConversationMutationLogger
{
    private function log(string $action, array $context): void
    {
        Log::channel('security')->info('messaging.'.$action, $context);
    }

    /**
     * Successful new message send. Logs safe identifiers ONLY — never content
     * or request bodies.
     */
    public function messageSent(User $actor, int $communityId, int $conversationId, int $messageId): void
    {
        $this->log('message_sent', [
            'actor_user_id' => $actor->id,
            'community_id' => $communityId,
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'action' => 'send_message',
            'result' => 'success',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
