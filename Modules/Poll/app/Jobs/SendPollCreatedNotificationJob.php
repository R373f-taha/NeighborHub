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

class SendPollCreatedNotificationJob implements ShouldQueue
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
    public function handle(): void
    {
        Log::info('📊 Processing poll created notifications', [
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
                Log::warning('⚠️ No active residents found for poll notification', [
                    'poll_id' => $this->poll->id,
                    'community_id' => $this->poll->community_id,
                ]);
                return;
            }

            // Create notification for each resident
            $notificationData = [
                'title' => "📊 New Poll: {$this->poll->title}",
                'body' => "A new poll has been created in your community. Share your opinion before " . $this->poll->ends_at->diffForHumans(),
                'type' => 'poll_created',
                'data' => [
                    'poll_id' => $this->poll->id,
                    'poll_title' => $this->poll->title,
                    'poll_type' => $this->poll->type->value,
                    'community_id' => $this->poll->community_id,
                    'ends_at' => $this->poll->ends_at->toISOString(),
                ],
                'notifiable_type' => get_class($this->poll),
                'notifiable_id' => $this->poll->id,
            ];

            foreach ($residents as $resident) {
                Notification::create([
                    'user_id' => $resident->user_id,
                    'title' => $notificationData['title'],
                    'body' => $notificationData['body'],
                    'type' => $notificationData['type'],
                    'data' => $notificationData['data'],
                    'notifiable_type' => $notificationData['notifiable_type'],
                    'notifiable_id' => $notificationData['notifiable_id'],
                    'read_at' => null,
                ]);
            }

            Log::info('✅ Poll created notifications sent successfully', [
                'poll_id' => $this->poll->id,
                'recipients' => $residents->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to send poll created notifications', [
                'poll_id' => $this->poll->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
