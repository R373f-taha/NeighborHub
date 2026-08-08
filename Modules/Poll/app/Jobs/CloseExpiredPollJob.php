<?php

declare(strict_types=1);

namespace Modules\Poll\app\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Poll\app\Models\Poll;
use Modules\Poll\app\Enums\PollStatus;
use Modules\Poll\app\Enums\PollCloseReason;
use Modules\Poll\app\Events\PollClosed;
use Modules\Poll\app\Traits\CacheableTrait;

class CloseExpiredPollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, CacheableTrait;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }


    public function __construct(
        protected Poll $poll
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('🔒 Starting to close expired poll via job', [
            'poll_id' => $this->poll->id,
            'poll_title' => $this->poll->title,
            'community_id' => $this->poll->community_id,
        ]);

        try {
            if ($this->poll->status !== PollStatus::Active) {
                Log::warning('⚠️ Poll is not active, skipping closure', [
                    'poll_id' => $this->poll->id,
                    'current_status' => $this->poll->status,
                ]);
                return;
            }

            if ($this->poll->ends_at > now()) {
                Log::warning('⚠️ Poll has not expired yet, skipping closure', [
                    'poll_id' => $this->poll->id,
                    'ends_at' => $this->poll->ends_at,
                ]);
                return;
            }

            $this->poll->update([
                'status' => PollStatus::Closed,
                'closed_at' => now(),
                'close_reason' => PollCloseReason::Expired,
            ]);

            $this->clearCache($this->poll->id);
            $this->clearCommunityCache($this->poll->community_id);
            $this->clearAllPollsCache();

            event(new PollClosed($this->poll));

            Log::info('✅ Expired poll closed successfully via job', [
                'poll_id' => $this->poll->id,
                'poll_title' => $this->poll->title,
                'closed_at' => $this->poll->closed_at,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to close expired poll via job', [
                'poll_id' => $this->poll->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // ✅ إعادة المحاولة
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('❌ CloseExpiredPollJob failed permanently', [
            'poll_id' => $this->poll->id,
            'poll_title' => $this->poll->title,
            'error' => $exception->getMessage(),
        ]);
    }
}
