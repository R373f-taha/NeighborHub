<?php

declare(strict_types=1);

namespace Modules\Issue\app\Observers;

use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Events\IssueCreated;
use Modules\Issue\app\Events\IssueDeleted;

class IssueObserver
{
    public function created(Issue $issue): void
    {
        IssueCreated::dispatch($issue);
    }


    public function deleted(Issue $issue): void
    {
        IssueDeleted::dispatch($issue);
    }
}