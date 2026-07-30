<?php

declare(strict_types=1);

namespace Modules\Community\app\Listeners;

use Modules\Community\app\Events\AnnouncementCreated;
use Modules\Notification\app\Jobs\SendNotificationJob;

class SendAnnouncementNotification
{
    public function handle(
        AnnouncementCreated $event
    ): void {

        SendNotificationJob::dispatch(
            $event->announcement
        );

    }
}