<?php

declare(strict_types=1);

namespace Modules\Community\app\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait CacheableTraits
{
    protected const CACHE_TTL = 600;
    protected const LOCK_TIMEOUT = 10;

    /**
     * Generate a standardized cache key
     */
    protected function cacheKey(string $type, int $id): string
    {
        return "community_{$type}_{$id}";
    }

    /**
     * Clear all cache entries related to a specific community
     */
    protected function clearCache(?int $communityId): void
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
     * Protect against Cache Stampede using a distributed lock
     */
    protected function rememberWithLock(string $key, int $ttl, callable $callback): mixed
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

            $randomTtl = $ttl + random_int(0, 60);
            Cache::put($key, $value, $randomTtl);

            Log::info("Cache rebuilt with lock for key: {$key}, TTL: {$randomTtl}");

            return $value;

        } finally {
            $lock->release();
        }
    }
}
