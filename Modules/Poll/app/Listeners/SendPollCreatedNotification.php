<?php

declare(strict_types=1);

namespace Modules\Poll\app\Listeners;

use Modules\Poll\app\Jobs\SendPollCreatedNotificationJob;
use Modules\Poll\app\Events\PollCreated;
use Illuminate\Support\Facades\Log;

class SendPollCreatedNotification
{
    public function handle(PollCreated $event): void
    {
        Log::info('📢 PollCreated event received, dispatching job', [
            'poll_id' => $event->poll->id,
            'poll_title' => $event->poll->title,
        ]);

        SendPollCreatedNotificationJob::dispatch($event->poll)
            ->onQueue('notifications');
    }
}
