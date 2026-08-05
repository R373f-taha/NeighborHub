<?php

declare(strict_types=1);

namespace Modules\Issue\app\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Issue\app\Models\Issue;

class IssueStatusUpdated
{
    use Dispatchable, SerializesModels;


    public function __construct(
        public readonly Issue $issue,
        public readonly string $oldStatus
    ) {}
}