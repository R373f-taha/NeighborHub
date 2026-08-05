<?php

declare(strict_types=1);

namespace Modules\Issue\app\Actions;
use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Services\IssueService;

class DeleteIssueAction
{

    public function __construct(
        private readonly IssueService $service
    ) {}
    public function execute(
        Issue $issue
    ): bool {

        return $this->service->delete(
            $issue
        );
    }
}