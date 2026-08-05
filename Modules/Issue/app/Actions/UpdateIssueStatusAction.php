<?php

declare(strict_types=1);

namespace Modules\Issue\app\Actions;
use Modules\Issue\app\DTOs\IssueStatusData;
use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Services\IssueService;

class UpdateIssueStatusAction
{

    public function __construct(
        private readonly IssueService $service
    ) {}

    public function execute(
        Issue $issue,
        IssueStatusData $data
    ): Issue {

        return $this->service->updateStatus(
            $issue,
            $data
        );

    }

}