<?php

declare(strict_types=1);

namespace Modules\Issue\app\Traits;

use Illuminate\Support\Facades\Cache;
use Modules\Issue\app\Models\Issue;

trait CacheableIssueTrait
{
    protected const ISSUE_CACHE_TTL = 600;
    protected const ISSUE_CACHE_LOCK_TIMEOUT = 10;

    protected function issueCacheKey(
        int $issueId
    ): string {
        return "issue:{$issueId}";
    }

    protected function communityIssuesCacheKey(
        int $communityId
    ): string {
        return "community:{$communityId}:issues";
    }


    public function getCachedIssue(
        int $issueId
    ): mixed {
        $key = $this->issueCacheKey($issueId);

        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        $lock = Cache::lock(
            "lock:{$key}",
            self::ISSUE_CACHE_LOCK_TIMEOUT
        );

        try {

            $lock->block(
                self::ISSUE_CACHE_LOCK_TIMEOUT
            );


            $cached = Cache::get($key);

            if ($cached !== null) {
                return $cached;
            }

            $issue = Issue::with([
                'category',
                'reporter',
                'assignee',
                'statusLogs',
            ])->find($issueId);


            Cache::put(
                $key,
                $issue,
                self::ISSUE_CACHE_TTL + random_int(0, 60)
            );

            return $issue;
        } finally {
            $lock->release();
        }
    }

    /**
     * Clear all cached data related to an issue.
     */
    public function clearIssueCache(
        Issue $issue
    ): void {
        Cache::forget(
            $this->issueCacheKey($issue->id)
        );

        Cache::forget(
            $this->communityIssuesCacheKey(
                $issue->community_id
            )
        );
    }

    /**
     * Clear cached issues list for a community.
     */
    public function clearCommunityIssuesCache(
        int $communityId
    ): void {
        Cache::forget(
            $this->communityIssuesCacheKey($communityId)
        );
    }
}