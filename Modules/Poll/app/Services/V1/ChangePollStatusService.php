<?php

namespace Modules\Poll\app\Services\V1;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Poll\app\Models\Poll;

use Modules\Poll\app\Enums\PollStatus;
use Modules\Poll\app\Events\PollClosed;

use Modules\Auth\app\Models\User;


use Modules\Poll\app\Traits\CacheableTrait;

class ChangePollStatusService{

use CacheableTrait;

 // ========== Activate Poll ==========

    public function activatePoll(Poll $poll): Poll
    {
        return DB::transaction(function () use ($poll) {
            $poll->update([
                'status' => PollStatus::Active,
                'activated_at' => now(),
            ]);

            $this->clearCommunityCache($poll->community_id);
            $this->clearCache($poll->id);

            Log::info('📊 Poll activated', [
                'poll_id' => $poll->id,
                'community_id' => $poll->community_id,
            ]);

            return $poll;
        });
    }

    public function closePoll(Poll $poll, User $closer): Poll
    {
        return DB::transaction(function () use ($poll, $closer) {
            $poll->update([
                'status' => PollStatus::Closed,
                'closed_at' => now(),
                //'closed_by_manager' => $closer->id,
            ]);

            $this->clearCommunityCache($poll->community_id);
            $this->clearCache($poll->id);

            Log::info('📊 Poll closed', [
                'poll_id' => $poll->id,
                'community_id' => $poll->community_id,
                'closed_by' => $closer->id,
            ]);

            // Dispatch event (Queue will handle notifications)
            PollClosed::dispatch($poll);

            return $poll;
        });
    }

        public function closeExpiredPolls(): int
    {
        $expiredPolls = Poll::expired()->get();
        $count = 0;

        foreach ($expiredPolls as $poll) {
            try {
                $this->closePoll($poll, $poll->creator);
                $count++;
            } catch (\Exception $e) {
                Log::error('Failed to close expired poll', [
                    'poll_id' => $poll->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($count > 0) {
            Log::info('✅ Expired polls closed', ['count' => $count]);
        }

        return $count;
    }

}
