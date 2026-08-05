<?php

declare(strict_types=1);

namespace Modules\Issue\app\DTOs;

use Modules\Issue\app\Http\Requests\V1\AddIssueLogNoteRequest;

final readonly class IssueLogData
{
    public function __construct(
        public int $changedBy,
        public string $note,
    ) {}

    public static function fromRequest(
        AddIssueLogNoteRequest $request
    ): self {
        return new self(
            changedBy: $request->user()->id,
            note: $request->string('note')->toString(),
        );
    }
}