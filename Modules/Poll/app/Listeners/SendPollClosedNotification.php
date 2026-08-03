<?php

declare(strict_types=1);

namespace Modules\Poll\app\Listeners;

use Modules\Poll\app\Jobs\SendPollClosedNotificationJob;
use Modules\Poll\app\Events\PollClosed;
use Illuminate\Support\Facades\Log;

class SendPollClosedNotification
{
    /**
     * Handle the event.
     */
    public function handle(PollClosed $event): void
    {
        Log::info('📢 PollClosed event received, dispatching job', [
            'poll_id' => $event->poll->id,
            'poll_title' => $event->poll->title,
        ]);

        SendPollClosedNotificationJob::dispatch($event->poll)
            ->onQueue('notifications');
    }
}
