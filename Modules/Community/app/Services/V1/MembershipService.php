<?php

declare(strict_types=1);

namespace Modules\Community\app\Services\V1;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Auth\app\Models\User;

class MembershipService{ private const CACHE_TTL = 600;

    /**
     * Lock timeout in seconds = 10 seconds
     * Prevents deadlock if a process hangs
     */
    private const LOCK_TIMEOUT = 10;

    /**
     * Generate a standardized cache key
     *
     * @param string $type The type of cache (stats, residents_stats, etc.)
     * @param int $id The community ID
     * @return string The cache key
     *
     * Example: community_stats_5
     */
    private function cacheKey(string $type, int $id): string
    {
        return "community_{$type}_{$id}";
    }

    /**
     * Clear all cache entries related to a specific community
     * Called after any data modification (create, update, delete)
     *
     * @param int|null $communityId The community ID
     * @return void
     */
    private function clearCache(?int $communityId): void
    {
        if ($communityId === null) {
            Log::warning('clearCache called with null ID');
            return;
        }

        Cache::forget($this->cacheKey('stats', $communityId));

        Cache::forget($this->cacheKey('residents_stats', $communityId));

        Cache::forget("community_single_{$communityId}");

        Cache::forget('community_list');

        Log::info("Cache cleared for community ID: {$communityId}");
    }

    /**
     * Protect against Cache Stampede
     * Uses a distributed lock to ensure only one process rebuilds the cache
     *
     * Cache Stampede: When many requests try to rebuild the same cache
     * entry simultaneously, causing a spike in database load
     *
     * @param string $key The cache key to remember
     * @param int $ttl The time to live in seconds
     * @param callable $callback The function to generate the value
     * @return mixed The cached value
     */
    private function rememberWithLock(string $key, int $ttl, callable $callback): mixed
    {

        $value = Cache::get($key);

        if ($value !== null) {
            return $value;
        }


        $lockKey = "lock_{$key}";
        $lock = Cache::lock($lockKey, self::LOCK_TIMEOUT);

        try {

            $lock->block(self::LOCK_TIMEOUT);


            $value = Cache::get($key);
            if ($value !== null) {
                return $value;
            }


            $value = $callback();

            // Random TTL helps distribute cache expiration times
            // Prevents all cache entries from expiring simultaneously

            $randomTtl = $ttl + random_int(0, 60);
            Cache::put($key, $value, $randomTtl);

            Log::info("Cache rebuilt with lock for key: {$key}, TTL: {$randomTtl}");

            return $value;

        } finally {

            $lock->release();
        }
    }
 /**
     * Request to join a community
     * Uses a lock to prevent duplicate join requests
     *
     * @param Community $community The community to join
     * @param User $user The user requesting to join
     * @param array $data Join request data (unit_id, residence_type)
     * @return Resident The created resident record
     * @throws \RuntimeException If user is already a member
     */
   public function joinCommunity(Community $community, User $user, array $data)
{
    $lockKey = "join_community_{$community->id}_{$user->id}";
    $lock = Cache::lock($lockKey, 10);

    try {
        $lock->block(5);

        $existing = Resident::where('user_id', $user->id)
            ->where('unit_id', $data['unit_id'])
            ->first();

        if ($existing) {
            // if (in_array($existing->status, ['pending', 'active'])) {
            //     throw new \RuntimeException('You already have a membership in this unit');
            // }

            $existing->update([
                'community_id' => $community->id,
                'residence_type' => $data['residence_type'],
                'status' => 'pending',
                'current_marker' => true,
                'joined_at' => now(),
            ]);

            $this->clearCache($community->id);

            $data=['message'=>'You already have a membership in this unit, but your status has been reset to pending.and we update your record',
            'record'=>$existing->fresh()];

            return $data;
        }

        return DB::transaction(function () use ($community, $user, $data) {
            $resident = Resident::create([
                'user_id' => $user->id,
                'community_id' => $community->id,
                'unit_id' => $data['unit_id'],
                'residence_type' => $data['residence_type'],
                'status' => 'pending',
                'current_marker' =>false,

            ]);

            $this->clearCache($community->id);

            return $resident;
        });
    } finally {
        $lock->release();
    }
}
    /**
     * Approve a pending resident
     *
     * @param Community $community The community
     * @param Resident $resident The resident to approve
     * @return Resident The updated resident
     */
   /**
 * Approve a pending resident
 *
 * @param Community $community The community
 * @param Resident $resident The resident to approve
 * @return Resident The updated resident
 * @throws \RuntimeException If resident does not belong to this community
 */
public function approveResident(Community $community, Resident $resident): Resident
{
    if ($resident->community_id !== $community->id) {
        throw new \RuntimeException('This resident does not belong to the specified community');
    }

    if ($resident->status !== 'pending') {
        throw new \RuntimeException('Only pending residents can be approved');
    }

    return DB::transaction(function () use ($resident) {
        $resident->update([
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->clearCache($resident->community_id);

        return $resident->fresh();
    });
}

/**
 * Reject a pending resident
 *
 * @param Community $community The community
 * @param Resident $resident The resident to reject
 * @return Resident The updated resident
 * @throws \RuntimeException If resident does not belong to this community
 */
public function rejectResident(Community $community, Resident $resident): Resident
{
    if ($resident->community_id !== $community->id) {
        throw new \RuntimeException('This resident does not belong to the specified community');
    }

    if ($resident->status !== 'pending') {
        throw new \RuntimeException('Only pending residents can be rejected');
    }

    return DB::transaction(function () use ($resident) {
        $resident->update([
            'status' => 'rejected',
            'current_marker' => false,
        ]);

        $this->clearCache($resident->community_id);

        return $resident->fresh();
    });
}

    /**
     * Suspend an active resident
     *
     * @param Resident $resident The resident to suspend
     * @return Resident The updated resident
     */
    public function suspendResident(Resident $resident,$community): Resident
    {
         if ($resident->community_id !== $community->id) {
        throw new \RuntimeException('This resident does not belong to the specified community');
    }

    if ($resident->status !== 'pending') {
        throw new \RuntimeException('Only pending residents can be rejected');
    }

        return DB::transaction(function () use ($resident) {
            $resident->update([
                'status' => 'suspended',
            ]);

            $this->clearCache($resident->community_id);

            return $resident->fresh();
        });
    }


}
