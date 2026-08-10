<?php

declare(strict_types=1);

namespace Modules\Poll\app\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait CacheableTrait
{
    protected const CACHE_TTL = 600; // 10 minutes
    protected const LOCK_TIMEOUT = 10; // seconds

    protected function cacheKey(string $type, int $id): string
    {
        return "poll_{$type}_{$id}";
    }

    protected function pollTags(int $pollId): array
    {
        return ['polls', 'poll_' . $pollId];
    }

    protected function communityTags(int $communityId): array
    {
        return ['communities', 'community_' . $communityId];
    }

    protected function allPollsTags(): array
    {
        return ['polls', 'all_polls'];
    }

    protected function extractIdFromKey(string $key): ?int
    {
        $parts = explode('_', $key);
        $last = end($parts);
        return is_numeric($last) ? (int) $last : null;
    }

    protected function generateTagsFromKey(string $key): ?array
    {
        if (str_starts_with($key, 'poll_results_') ||
            str_starts_with($key, 'poll_stats_') ||
            str_starts_with($key, 'poll_votes_') ||
            str_starts_with($key, 'poll_single_')) {
            $id = $this->extractIdFromKey($key);
            return $id ? $this->pollTags($id) : null;
        }

        if (str_starts_with($key, 'poll_list_community_') ||
            str_starts_with($key, 'poll_active_community_')) {
            $id = $this->extractIdFromKey($key);
            return $id ? $this->communityTags($id) : null;
        }

        if ($key === 'poll_all' || $key === 'poll_all_polls') {
            return $this->allPollsTags();
        }

        return null;
    }

    /**
     * ✅ مسح الكاش بشكل صحيح — بيستخدم Tags
     */
    protected function clearCache(int $pollId): void
    {
        $tags = $this->pollTags($pollId);
        $keys = [
            $this->cacheKey('results', $pollId),
            $this->cacheKey('stats', $pollId),
            $this->cacheKey('votes', $pollId),
            "poll_single_{$pollId}",
        ];

        // نمسح كل مفتاح باستخدام Tags (هاد يلي كان ناقص!)
        foreach ($keys as $key) {
            try {
                Cache::tags($tags)->forget($key);
            } catch (\Exception $e) {
                Cache::forget($key); // fallback
            }
        }

        // هدول ما عندن Tags بالأصل فنمسحون عادي
        Cache::forget("poll_list_community_{$pollId}");
        Cache::forget("poll_active_community_{$pollId}");

        Log::info("🗑️ Cache cleared for poll ID: {$pollId}");
    }

    protected function clearCommunityCache(int $communityId): void
    {
        $tags = $this->communityTags($communityId);

        try {
            Cache::tags($tags)->flush();
        } catch (\Exception $e) {
            Log::warning('Cache tags flush failed', ['community_id' => $communityId]);
        }

        Cache::forget("poll_list_community_{$communityId}");
        Cache::forget("poll_active_community_{$communityId}");

        Log::info("🗑️ Community cache cleared for ID: {$communityId}");
    }

    protected function clearAllPollsCache(): void
    {
        try {
            Cache::tags($this->allPollsTags())->flush();
        } catch (\Exception $e) {
            Log::warning('Cache tags flush failed for all polls');
        }

        Log::info("🗑️ All polls cache cleared");
    }

    protected function rememberWithLock(string $key, int $ttl, callable $callback, ?array $tags = null): mixed
    {
        if ($tags === null) {
            $tags = $this->generateTagsFromKey($key);
        }

        $value = $this->getFromCache($key, $tags);
        if ($value !== null) {
            return $value;
        }

        $lockKey = "lock_{$key}";
        $lock = Cache::lock($lockKey, self::LOCK_TIMEOUT);

        try {
            $lock->block(self::LOCK_TIMEOUT);
        } catch (\Exception $e) {
            Log::warning('Cache lock failed, executing without cache', ['key' => $key]);
            return $callback();
        }

        try {
            $value = $this->getFromCache($key, $tags);
            if ($value !== null) {
                return $value;
            }

            $value = $callback();
            $randomTtl = $ttl + random_int(0, 60);

            $this->putToCache($key, $value, $randomTtl, $tags);

            Log::info("🔒 Cache rebuilt with lock", [
                'key' => $key,
                'ttl' => $randomTtl,
            ]);

            return $value;
        } finally {
            try {
                $lock->release();
            } catch (\Exception $e) {
            }
        }
    }

    protected function rememberForever(string $key, callable $callback): mixed
    {
        $tags = $this->generateTagsFromKey($key);
        $value = $this->getFromCache($key, $tags);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->putToCache($key, $value, null, $tags, true);

        return $value;
    }


    private function getFromCache(string $key, ?array $tags): mixed
    {
        if ($tags !== null && !empty($tags)) {
            return Cache::tags($tags)->get($key);
        }
        return Cache::get($key);
    }


    private function putToCache(string $key, mixed $value, ?int $ttl, ?array $tags, bool $forever = false): void
    {
        if ($tags !== null && !empty($tags)) {
            if ($forever) {
                Cache::tags($tags)->forever($key, $value);
            } else {
                Cache::tags($tags)->put($key, $value, $ttl);
            }
        } else {
            if ($forever) {
                Cache::forever($key, $value);
            } else {
                Cache::put($key, $value, $ttl);
            }
        }
    }
}
