<?php

declare(strict_types=1);

namespace Modules\Poll\app\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Notification\app\Models\Notification;
use Modules\Poll\app\Models\Poll;
use Modules\Community\app\Models\Resident;
use Modules\Poll\App\Services\V1\VotesManagementService;

class SendPollClosedNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 10;

    public function __construct(
        protected Poll $poll
    ) {}

    /**
     * Execute the job.
     */
    public function handle(VotesManagementService  $pollService): void
    {
        Log::info('📊 Processing poll closed notifications', [
            'poll_id' => $this->poll->id,
            'poll_title' => $this->poll->title,
            'community_id' => $this->poll->community_id,
        ]);

        try {
            // Get all active residents in the community
            $residents = Resident::where('community_id', $this->poll->community_id)
                ->where('status', 'active')
                ->where('current_marker', true)
                ->with('user')
                ->get();

            if ($residents->isEmpty()) {
                Log::warning('⚠️ No active residents found for poll results notification', [
                    'poll_id' => $this->poll->id,
                    'community_id' => $this->poll->community_id,
                ]);
                return;
            }

            // Get poll results
            $results = $pollService->getResults($this->poll);

            // Create notification for each resident
            foreach ($residents as $resident) {
                Notification::create([
                    'user_id' => $resident->user_id,
                    'title' => "📊 Poll Results: {$this->poll->title}",
                    'body' => $this->generateResultsBody($results),
                    'type' => 'poll_closed',
                    'data' => array_merge($results, [
                        'poll_id' => $this->poll->id,
                        'poll_title' => $this->poll->title,
                    ]),
                    'notifiable_type' => get_class($this->poll),
                    'notifiable_id' => $this->poll->id,
                    'read_at' => null,
                ]);
            }

            Log::info('✅ Poll results notifications sent successfully', [
                'poll_id' => $this->poll->id,
                'recipients' => $residents->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to send poll results notifications', [
                'poll_id' => $this->poll->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate results body for notification.
     */
    private function generateResultsBody(array $results): string
    {
        $lines = [];

        if (isset($results['results']) && is_array($results['results'])) {
            foreach ($results['results'] as $option) {
                $lines[] = "• {$option['label']}: {$option['votes']} votes ({$option['percentage']}%)";
            }
        }

        $lines[] = "";
        $lines[] = "Total participants: {$results['total_votes']}";
        $lines[] = "Turnout: {$results['turnout']}%";

        return implode("\n", $lines);
    }
}
