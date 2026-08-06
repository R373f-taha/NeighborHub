<?php

declare(strict_types=1);

namespace Modules\Media\app\Services;

use Illuminate\Support\Facades\Log;
use Modules\Auth\app\Models\User;

/**
 * Security-channel audit logging for Media mutations, mirroring the approved
 * Post/ServiceListing logger convention. Logs safe metadata only: never file
 * contents, request bodies, tokens, original storage paths, or user PII.
 */
class MediaLogger
{
    /** @param array<string, mixed> $context */
    private function log(string $action, array $context): void
    {
        Log::channel('security')->info('media.' . $action, $context);
    }

    public function uploaded(User $actor, int $communityId, int $mediaId, string $parentType, int $parentId): void
    {
        $this->log('uploaded', [
            'actor_user_id' => $actor->id,
            'community_id' => $communityId,
            'media_id' => $mediaId,
            'parent_type' => $parentType,
            'parent_id' => $parentId,
            'action' => 'upload',
            'result' => 'success',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function deleted(User $actor, int $communityId, int $mediaId, string $parentType, int $parentId): void
    {
        $this->log('deleted', [
            'actor_user_id' => $actor->id,
            'community_id' => $communityId,
            'media_id' => $mediaId,
            'parent_type' => $parentType,
            'parent_id' => $parentId,
            'action' => 'delete',
            'result' => 'success',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function reordered(User $actor, int $communityId, string $parentType, int $parentId, int $count): void
    {
        $this->log('reordered', [
            'actor_user_id' => $actor->id,
            'community_id' => $communityId,
            'parent_type' => $parentType,
            'parent_id' => $parentId,
            'action' => 'reorder',
            'result' => 'success',
            'count' => $count,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Post-commit filesystem cleanup failed for an already-deleted Media row.
     * Logged once at dispatch time (not per queue retry) with safe fields
     * only; the storage path is never logged.
     *
     * @param object{media_id:int,community_id:int,alias:string,parent_id:int,disk:string} $descriptor
     */
    public function cleanupFailed(object $descriptor): void
    {
        $this->log('cleanup_failed', [
            'media_id' => $descriptor->media_id,
            'community_id' => $descriptor->community_id,
            'parent_type' => $descriptor->alias,
            'parent_id' => $descriptor->parent_id,
            'disk' => $descriptor->disk,
            'action' => 'cleanup',
            'result' => 'retry_scheduled',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
