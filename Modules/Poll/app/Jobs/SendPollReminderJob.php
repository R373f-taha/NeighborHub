<?php

namespace Modules\Poll\app\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Poll\app\Models\Poll;
use Modules\Poll\app\Enums\PollStatus;
use Modules\Community\app\Models\Resident;
use Modules\Notification\app\Models\Notification;

class SendPollReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        protected Poll $poll
    ) {}

    public function handle(): void
    {
        Log::info('⏰ Sending poll reminders', [
            'poll_id' => $this->poll->id,
            'poll_title' => $this->poll->title,
            'ends_at' => $this->poll->ends_at,
        ]);

        try {
            $residents = Resident::where('community_id', $this->poll->community_id)
                ->where('status', 'active')
                ->where('current_marker', true)
                ->with('user')
                ->get();

            if ($residents->isEmpty()) {
                Log::warning('⚠️ No residents found for reminder', [
                    'poll_id' => $this->poll->id,
                ]);
                return;
            }

            $hoursRemaining = now()->diffInHours($this->poll->ends_at);

            $timeLeft = $this->formatTimeLeft($hoursRemaining);

            foreach ($residents as $resident) {
                Notification::create([
                    'user_id' => $resident->user_id,
                    'title' => "⏰ تذكير: استطلاع '{$this->poll->title}' ينتهي قريباً!",
                    'body' => "متبقي {$timeLeft} على انتهاء الاستطلاع. لا تفوت فرصة التصويت!",
                    'type' => 'poll_reminder',
                    'data' => [
                        'poll_id' => $this->poll->id,
                        'poll_title' => $this->poll->title,
                        'ends_at' => $this->poll->ends_at->toIso8601String(),
                        'hours_remaining' => $hoursRemaining,
                    ],
                    'notifiable_type' => get_class($this->poll),
                    'notifiable_id' => $this->poll->id,
                    'read_at' => null,
                ]);
            }

            Log::info('✅ Poll reminders sent successfully', [
                'poll_id' => $this->poll->id,
                'recipients' => $residents->count(),
                'hours_remaining' => $hoursRemaining,
            ]);

            $this->poll->update([
                'closed_at' => now(),
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to send poll reminders', [
                'poll_id' => $this->poll->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function formatTimeLeft(int $hours): string
    {
        if ($hours >= 24) {
            $days = floor($hours / 24);
            $remainingHours = $hours % 24;
            return "{$days} يوم و {$remainingHours} ساعة";
        }

        return "{$hours} ساعة";
    }
}
