<?php

declare(strict_types=1);

namespace Modules\Post\app\Services;

use Illuminate\Support\Facades\Log;
use Modules\Auth\app\Models\User;

class ModerationLogger
{
    public function postDeletedByModerator(User $actor, int $communityId, int $postId, int $ownerResidentId): void
    {
        Log::channel('security')->warning('content.post.deleted_by_moderator', [
            'actor_user_id' => $actor->id,
            'actor_role' => $actor->role->value ?? $actor->role,
            'community_id' => $communityId,
            'post_id' => $postId,
            'owner_resident_id' => $ownerResidentId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function commentDeletedByModerator(User $actor, int $communityId, int $postId, int $commentId, int $ownerUserId): void
    {
        Log::channel('security')->warning('content.comment.deleted_by_moderator', [
            'actor_user_id' => $actor->id,
            'actor_role' => $actor->role->value ?? $actor->role,
            'community_id' => $communityId,
            'post_id' => $postId,
            'comment_id' => $commentId,
            'owner_user_id' => $ownerUserId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
