<?php

declare(strict_types=1);

namespace Modules\Issue\app\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Modules\Issue\app\Models\Issue;

use Modules\Issue\app\Notifications\IssueAssignedNotification;
use Modules\Issue\app\Notifications\IssueStatusChangedNotification;


class SendIssueNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;


    public function __construct(
        public readonly Issue $issue,
        public readonly string $type,
    ) {}


    public function handle(): void
    {
        match ($this->type) {

            'assigned' => $this->sendAssignedNotification(),

            'status_updated' => $this->sendStatusNotification(),

            default => null,

        };
    }


    private function sendAssignedNotification(): void
    {
        if ($this->issue->assignee) {

            $this->issue->assignee
                ->notify(
                    new IssueAssignedNotification(
                        $this->issue
                    )
                );

        }
    }


    private function sendStatusNotification(): void
    {
        if ($this->issue->reporter) {

            $this->issue->reporter
                ->notify(
                    new IssueStatusChangedNotification(
                        $this->issue
                    )
                );

        }
    }
}