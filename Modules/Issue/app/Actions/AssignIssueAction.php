<?php

declare(strict_types=1);

namespace Modules\Issue\app\Actions;
use Modules\Issue\app\DTOs\AssignIssueData;
use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Services\IssueService;

class AssignIssueAction
{

    public function __construct(
        private readonly IssueService $service
    ) {}

    public function execute(
        Issue $issue,
        AssignIssueData $data
    ): Issue {

        return $this->service->assign(
            $issue,
            $data
        );

    }

}