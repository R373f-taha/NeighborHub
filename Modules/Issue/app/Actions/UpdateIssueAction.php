<?php

declare(strict_types=1);

namespace Modules\Issue\app\Actions;
use Modules\Issue\app\DTOs\IssueData;
use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Services\IssueService;


class UpdateIssueAction
{

    public function __construct(
        private readonly IssueService $service) {}


    public function execute(
        Issue $issue,
        IssueData $data
    ): Issue {

        return $this->service->update(
            $issue,
            $data
        );
    }
}