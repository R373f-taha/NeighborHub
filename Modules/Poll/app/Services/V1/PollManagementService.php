<?php

 namespace Modules\Poll\App\Services\V1;

use Modules\Poll\app\Models\Poll;

use Modules\Poll\app\Enums\PollStatus;
use Modules\Poll\app\Traits\CacheableTrait;

 class PollManagementService{


use CacheableTrait;


    public function getActivePolls(int $communityId)
    {
        $cacheKey = "poll_active_community_{$communityId}";

        return $this->rememberWithLock($cacheKey, 300, function () use ($communityId) {
            return Poll::where('community_id', $communityId)
                ->where('status', PollStatus::Active)
                ->where('ends_at', '>', now())
                ->with(['options', 'creator'])
                ->orderBy('ends_at', 'asc')
                ->get();
        });
    }



 }
