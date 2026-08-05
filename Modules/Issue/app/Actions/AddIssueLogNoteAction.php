<?php

declare(strict_types=1);

namespace Modules\Issue\app\Actions;
use Modules\Issue\app\DTOs\IssueLogData;
use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Services\IssueService;
class AddIssueLogNoteAction
{

    public function __construct(
        private readonly IssueService $service
    ) {}

    public function execute(
        Issue $issue,
        IssueLogData $data
    ): void {

        $this->service->addLog($issue, $data);

    }

}