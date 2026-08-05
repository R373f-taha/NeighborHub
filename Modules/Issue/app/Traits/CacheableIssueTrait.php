<?php

declare(strict_types=1);

namespace Modules\Issue\app\Traits;

use Illuminate\Support\Facades\Cache;

use Modules\Issue\app\Models\Issue;

trait CacheableIssueTrait
{
    protected int $cacheTTL = 600;


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

        return Cache::remember(
            $this->issueCacheKey($issueId),
            $this->cacheTTL,
            function () use ($issueId) {

                return Issue::with([
                    'category',
                    'reporter',
                    'assignee',
                    'statusLogs',
                ])->find($issueId);

            }
        );
    }


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


    public function clearCommunityIssuesCache(
        int $communityId
    ): void {

        Cache::forget(
            $this->communityIssuesCacheKey($communityId)
        );
    }
}