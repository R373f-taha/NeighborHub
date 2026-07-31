<?php

declare(strict_types=1);

namespace Modules\Community\app\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait CacheableTraits
{
    protected const CACHE_TTL = 600;
    protected const LOCK_TIMEOUT = 10;

    // ============================================================
    // 1. CACHE KEYS
    // ============================================================

    /**
     * Generate a standardized cache key
     *
     * @param string $type (stats, residents_stats, single, etc.)
     * @param int $id The community ID
     * gnerate a cache key for a specific community and type
     * @return string
     *
     * Example: community_stats_1
     */
    protected function cacheKey(string $type, int $id): string
    {
        return "community_{$type}_{$id}";
    }

    /**
     * Get cache tags for a specific community
     *
     * @param int|null $communityId
     * Storage keyname with appropriate tags
     * @return array
     */
    protected function getCommunityTags(?int $communityId): array
    {
        $tags = ['community'];

        if ($communityId !== null) {
            $tags[] = "community_{$communityId}";
        }

        return $tags;
    }

    /**
     * Get cache tags for residents list
     *
     * @param int $communityId
     * @return array
     */
    protected function getResidentsTags(int $communityId): array
    {
        return ['residents', "residents_{$communityId}"];
    }

    // ============================================================
    // 2. CACHE CLEARING
    // ============================================================

    /**
     * Clear all cache entries related to a specific community using Tags
     *
     * @param int|null $communityId
     * @return void
     */
    protected function clearCache(?int $communityId): void
    {
        if ($communityId === null) {
            Log::warning('clearCache called with null ID');
            return;
        }

        Cache::tags($this->getCommunityTags($communityId))->flush();
        Cache::tags(['communities_list'])->flush();

        Log::info("Cache cleared for community ID: {$communityId} using tags");
    }

    /**
     * Clear all community-related cache
     *
     * @return void
     */
    protected function clearCommunityCache(): void
    {
        Cache::tags(['community'])->flush();
        Cache::tags(['communities_list'])->flush();

        Log::info('All community cache cleared');
    }

    /**
     * Clear residents cache for a specific community
     *
     * @param int $communityId
     * @return void
     */
    protected function clearResidentsCache(int $communityId): void
    {
        Cache::tags($this->getResidentsTags($communityId))->flush();
        Log::info("Residents cache cleared for community ID: {$communityId}");
    }

    // ============================================================
    // 3. CORE: REMEMBER WITH LOCK (Anti-Stampede)
    // ============================================================

    /**
     * Core method: Remember with Lock to protect against Cache Stampede
     *

     * @param string $key The cache key
     * @param int $ttl Time to live in seconds
     * @param callable $callback The function to generate the value
     * @param string|array $tags Cache tags (optional)
     * @return mixed
     */
    protected function rememberWithLock(string $key, int $ttl, callable $callback, string|array $tags = ['community']): mixed
    {
        $value = Cache::tags($tags)->get($key);

        //ex: Cache::tags(['community'])->get('community_stats_1')

        if ($value !== null) {
            return $value;
        }

        $lockKey = "lock_{$key}";
        $lock = Cache::lock($lockKey, self::LOCK_TIMEOUT);

        try {
            $lock->block(self::LOCK_TIMEOUT);

            $value = Cache::tags($tags)->get($key);
            if ($value !== null) {
                return $value;
            }

            $value = $callback();

            //  Store with random TTL to prevent stampede

            $randomTtl = $ttl + random_int(0, 60);
            Cache::tags($tags)->put($key, $value, $randomTtl);

            Log::info("Cache rebuilt with lock for key: {$key}, TTL: {$randomTtl}");

            return $value;

        } finally {
            $lock->release();
        }
    }



    /**
     * Remember community stats with lock
     * Uses 'community' tags
     *
     * @param string $key
     * @param int $ttl
     * @param callable $callback
     * @return mixed
     */
    protected function rememberStats(string $key, int $ttl, callable $callback): mixed
    {
        return $this->rememberWithLock($key, $ttl, $callback, ['community']);
    }

    /**
     * Remember community residents stats with lock
     * Uses 'community' tags
     *
     * @param string $key
     * @param int $communityId
     * @param int $ttl
     * @param callable $callback
     * @return mixed
     */
    protected function rememberResidentsStats(string $key, int $communityId, int $ttl, callable $callback): mixed
    {
        $tags = $this->getCommunityTags($communityId);

        return $this->rememberWithLock($key, $ttl, $callback, $tags);
    }

    /**
     * Remember single community data with lock
     * Uses community tags
     *
     * @param string $key
     * @param int $communityId
     * @param int $ttl
     * @param callable $callback
     * @return mixed
     */
    protected function rememberSingleCommunity(string $key, int $communityId, int $ttl, callable $callback): mixed
    {
        $tags = $this->getCommunityTags($communityId);

        return $this->rememberWithLock($key, $ttl, $callback, $tags);
    }

    /**
     * Remember communities list with lock
     * Uses 'communities_list' tag
     *
     * @param string $key
     * @param int $ttl
     * @param callable $callback
     * @return mixed
     */
    protected function rememberList(string $key, int $ttl, callable $callback): mixed
    {
        return $this->rememberWithLock($key, $ttl, $callback, ['communities_list']);
    }

    /**
     * Remember residents list with lock
     * Uses residents tags
     *
     * @param string $key
     * @param int $communityId
     * @param int $ttl
     * @param callable $callback
     * @return mixed
     */
    protected function rememberResidents(string $key, int $communityId, int $ttl, callable $callback): mixed
    {
        $tags = $this->getResidentsTags($communityId);

        return $this->rememberWithLock($key, $ttl, $callback, $tags);
    }

    /**
     * Remember any custom data with lock
     * You can pass your own tags
     *
     * @param string $key
     * @param int $ttl
     * @param callable $callback
     * @param string|array $tags
     * @return mixed
     */
    protected function rememberWithCustomTags(string $key, int $ttl, callable $callback, string|array $tags): mixed
    {
        return $this->rememberWithLock($key, $ttl, $callback, $tags);
    }
}
