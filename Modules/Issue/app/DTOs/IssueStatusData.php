<?php

declare(strict_types=1);

namespace Modules\Issue\app\DTOs;

use Modules\Issue\app\Enums\IssueStatus;
use Modules\Issue\app\Http\Requests\V1\UpdateIssueStatusRequest;

final readonly class IssueStatusData
{
    public function __construct(
        public IssueStatus $status,
        public int $changedBy,
        public ?string $note,
    ) {}

   public static function fromRequest(
    UpdateIssueStatusRequest $request
): self {

    return new self(
        status: IssueStatus::from(
            $request->input('status')
        ),
        changedBy: $request->user()->id,
        note: $request->input('note'),
    );
}
}