<?php

declare(strict_types=1);

namespace Modules\Poll\app\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait CacheableTrait
{
    protected const CACHE_TTL = 600; // 10 minutes
    protected const LOCK_TIMEOUT = 10; // seconds

    /**
     * Generate a standardized cache key.
     */
    protected function cacheKey(string $type, int $id): string
    {
        return "poll_{$type}_{$id}";
    }

    /**
     * Get cache tags for a specific poll.
     */
    protected function pollTags(int $pollId): array
    {
        return ['polls', 'poll_' . $pollId];
    }

    /**
     * Get cache tags for a community.
     */
    protected function communityTags(int $communityId): array
    {
        return ['communities', 'community_' . $communityId];
    }

    /**
     * Get cache tags for all polls.
     */
    protected function allPollsTags(): array
    {
        return ['polls', 'all_polls'];
    }

   
    protected function extractIdFromKey(string $key): ?int
    {
        // مثال: poll_results_123 → 123
        // مثال: poll_list_community_5 → 5
        // مثال: poll_active_community_5 → 5

        $parts = explode('_', $key);
        return (int) end($parts);
    }


    protected function generateTagsFromKey(string $key): ?array
    {
        // poll_results_123 → pollTags(123)
        if (str_starts_with($key, 'poll_results_') ||
            str_starts_with($key, 'poll_stats_') ||
            str_starts_with($key, 'poll_votes_') ||
            str_starts_with($key, 'poll_single_')) {

            $id = $this->extractIdFromKey($key);
            return $this->pollTags($id);
        }

        // poll_list_community_5 → communityTags(5)
        if (str_starts_with($key, 'poll_list_community_') ||
            str_starts_with($key, 'poll_active_community_')) {

            $id = $this->extractIdFromKey($key);
            return $this->communityTags($id);
        }

        // poll_all → allPollsTags()
        if ($key === 'poll_all' || $key === 'poll_all_polls') {
            return $this->allPollsTags();
        }

        return null;
    }

    /**
     * Clear all cache entries related to a specific poll.
     */
    protected function clearCache(int $pollId): void
    {
        try {
            Cache::tags($this->pollTags($pollId))->flush();
        } catch (\Exception $e) {
            Log::warning('Cache tags not supported, using fallback', [
                'driver' => config('cache.default'),
                'poll_id' => $pollId,
            ]);
        }

        Cache::forget($this->cacheKey('results', $pollId));
        Cache::forget($this->cacheKey('stats', $pollId));
        Cache::forget($this->cacheKey('votes', $pollId));
        Cache::forget("poll_single_{$pollId}");
        Cache::forget('poll_list_community_' . $pollId);
        Cache::forget('poll_active_community_' . $pollId);

        Log::info("🗑️ Cache cleared for poll ID: {$pollId}", [
            'tags' => $this->pollTags($pollId),
        ]);
    }

    /**
     * Clear community-wide cache.
     */
    protected function clearCommunityCache(int $communityId): void
    {
        try {
            Cache::tags($this->communityTags($communityId))->flush();
        } catch (\Exception $e) {
            Log::warning('Cache tags not supported for community', [
                'community_id' => $communityId,
            ]);
        }

        Cache::forget("poll_list_community_{$communityId}");
        Cache::forget("poll_active_community_{$communityId}");

        Log::info("🗑️ Community cache cleared for ID: {$communityId}", [
            'tags' => $this->communityTags($communityId),
        ]);
    }

    /**
     * Clear all polls cache.
     */
    protected function clearAllPollsCache(): void
    {
        try {
            Cache::tags($this->allPollsTags())->flush();
        } catch (\Exception $e) {
            Log::warning('Cache tags not supported for all polls');
        }

        Log::info("🗑️ All polls cache cleared");
    }

    /**
     * Protect against Cache Stampede using a distributed lock
     */
    protected function rememberWithLock(string $key, int $ttl, callable $callback, ?array $tags = null): mixed
    {
        if ($tags === null) {
            $tags = $this->generateTagsFromKey($key);
        }

        if ($tags !== null && !empty($tags)) {
            $value = Cache::tags($tags)->get($key);
        } else {
            $value = Cache::get($key);
        }

        if ($value !== null) {
            return $value;
        }

        $lockKey = "lock_{$key}";
        $lock = Cache::lock($lockKey, self::LOCK_TIMEOUT);

        try {
            $lock->block(self::LOCK_TIMEOUT);

            // Double-check cache after acquiring lock
            if ($tags !== null && !empty($tags)) {
                $value = Cache::tags($tags)->get($key);
            } else {
                $value = Cache::get($key);
            }

            if ($value !== null) {
                return $value;
            }

            $value = $callback();

            // Add random TTL to prevent simultaneous expiration
            $randomTtl = $ttl + random_int(0, 60);

            if ($tags !== null && !empty($tags)) {
                Cache::tags($tags)->put($key, $value, $randomTtl);
            } else {
                Cache::put($key, $value, $randomTtl);
            }

            Log::info("🔒 Cache rebuilt with lock", [
                'key' => $key,
                'tags' => $tags,
                'ttl' => $randomTtl,
            ]);

            return $value;

        } finally {
            $lock->release();
        }
    }


    protected function rememberForever(string $key, callable $callback): mixed
    {
        $tags = $this->generateTagsFromKey($key);

        if ($tags !== null && !empty($tags)) {
            return Cache::tags($tags)->rememberForever($key, $callback);
        }

        return Cache::rememberForever($key, $callback);
    }



}
