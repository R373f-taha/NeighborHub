<?php

declare(strict_types=1);

namespace Modules\Community\app\Services\V1;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Auth\app\Models\User;

class CommunityService
{

    private const CACHE_TTL = 600;

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
     * Create a new community
     *
     * @param array $data Community data (name, city, address, etc.)
     * @return Community The created community
     */
    public function create(array $data): Community
    {
        return DB::transaction(function () use ($data) {
            $community = Community::create($data);

            if (isset($data['manager_ids'])) {
                $community->managers()->attach($data['manager_ids']);
            }


            $this->clearCache($community->id);

            return $community;
        });
    }

    /**
     * Update an existing community
     *
     * @param Community $community The community model
     * @param array $data The updated data
     * @return Community The updated community
     */
    public function update(Community $community, array $data): Community
    {
        $communityId = $community->id;

        return DB::transaction(function () use ($community, $data, $communityId) {
            $community->update($data);

            if (isset($data['manager_ids'])) {
                $community->managers()->sync($data['manager_ids']);
            }

            $this->clearCache($communityId);

            return $community->fresh();
        });
    }

    /**
     * Delete a community
     *
     * @param Community $community The community to delete
     * @return bool True if deleted successfully
     */
    public function delete(Community $community): bool
    {
        $communityId = $community->id;

        $this->clearCache($communityId);

        return $community->delete();
    }



}
