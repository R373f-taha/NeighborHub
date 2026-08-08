<?php

declare(strict_types=1);

namespace Modules\ServiceListing\app\Services;

use Illuminate\Support\Facades\Log;
use Modules\Auth\app\Models\User;


class ServiceListingMutationLogger
{
    /**
     * @param array<string, mixed> $context
     */
    private function log(string $action, array $context): void
    {
        Log::channel('security')->info('service_listing.' . $action, $context);
    }

    public function created(User $actor, int $communityId, int $serviceListingId): void
    {
        $this->log('created', [
            'actor_user_id' => $actor->id,
            'community_id' => $communityId,
            'service_listing_id' => $serviceListingId,
            'action' => 'create',
            'result' => 'success',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function updated(User $actor, int $communityId, int $serviceListingId): void
    {
        $this->log('updated', [
            'actor_user_id' => $actor->id,
            'community_id' => $communityId,
            'service_listing_id' => $serviceListingId,
            'action' => 'update',
            'result' => 'success',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function deleted(User $actor, int $communityId, int $serviceListingId): void
    {
        $this->log('deleted', [
            'actor_user_id' => $actor->id,
            'community_id' => $communityId,
            'service_listing_id' => $serviceListingId,
            'action' => 'delete',
            'result' => 'success',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Successful status transition. Logs only safe status metadata
     * (old/new status) and never the request body, content, or credentials.
     */
    public function statusUpdated(User $actor, int $communityId, int $serviceListingId, string $oldStatus, string $newStatus): void
    {
        $this->log('status_updated', [
            'actor_user_id' => $actor->id,
            'community_id' => $communityId,
            'service_listing_id' => $serviceListingId,
            'action' => 'status_update',
            'result' => 'success',
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
