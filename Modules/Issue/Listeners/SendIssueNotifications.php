<?php

declare(strict_types=1);

namespace Modules\Issue\app\Listeners;

use Modules\Issue\app\Events\IssueAssigned;
use Modules\Issue\app\Events\IssueStatusUpdated;
use Modules\Issue\app\Jobs\SendIssueNotificationJob;


class SendIssueNotifications
{
    public function handle(
        object $event
    ): void {

        if ($event instanceof IssueAssigned) {

            SendIssueNotificationJob::dispatch(
                $event->issue,
                'assigned'
            );

            return;
        }


        if ($event instanceof IssueStatusUpdated) {

            SendIssueNotificationJob::dispatch(
                $event->issue,
                'status_updated'
            );

        }

    }
}