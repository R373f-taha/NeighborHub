<?php

declare(strict_types=1);

namespace Modules\Issue\app\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

use Modules\Issue\app\Models\Issue;


class IssueStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;


    public function __construct(
        public readonly Issue $issue
    ) {}



    public function via(
        object $notifiable
    ): array {

        return [
            'database',
        ];

    }



    public function toDatabase(
        object $notifiable
    ): array {

        return [

            'type' => 'issue_status_changed',

            'issue_id' => $this->issue->id,

            'status' => $this->issue->status->value,

            'message' =>
                "Issue #{$this->issue->id} status changed to {$this->issue->status->value}",

        ];

    }
}