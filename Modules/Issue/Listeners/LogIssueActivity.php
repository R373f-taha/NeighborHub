<?php

declare(strict_types=1);

namespace Modules\Issue\app\Listeners;

use Illuminate\Support\Facades\Log;


class LogIssueActivity
{
    public function handle(
        object $event
    ): void {


        if (isset($event->issue)) {

            Log::info(
                'Issue activity',
                [
                    'issue_id' => $event->issue->id,
                    'event' => class_basename($event),
                ]
            );

        }


        if (isset($event->log)) {

            Log::info(
                'Issue log added',
                [
                    'log_id' => $event->log->id,
                ]
            );

        }

    }
}