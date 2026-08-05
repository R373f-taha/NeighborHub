<?php

declare(strict_types=1);

namespace Modules\Issue\app\DTOs;

use Modules\Issue\app\Enums\IssuePriority;
use Modules\Issue\app\Http\Requests\V1\StoreIssueRequest;
use Modules\Issue\app\Http\Requests\V1\UpdateIssueRequest;
use Modules\Issue\app\Enums\IssueStatus;

final readonly class IssueData
{
    public function __construct(
        public ?int $communityId,
        public ?int $categoryId,
        public ?string $title,
        public ?string $description,
        public ?string $location,
        public ?IssuePriority $priority,
        public ?int $reportedBy,
    ) {}

    public static function fromStoreRequest(
        StoreIssueRequest $request,
        int $communityId
    ): self {
        return new self(
            communityId: $communityId,
            categoryId: $request->integer('category_id'),
            title: $request->string('title')->toString(),
            description: $request->string('description')->toString(),
            location: $request->string('location')->toString(),
            priority: IssuePriority::from(
                $request->string('priority')->toString()
            ),
            reportedBy: $request->user()->id,
        );
    }

    public static function fromUpdateRequest(
        UpdateIssueRequest $request
    ): self {
        return new self(
            communityId: null,
            categoryId: $request->has('category_id')
                ? $request->integer('category_id')
                : null,
            title: $request->has('title')
                ? $request->string('title')->toString()
                : null,
            description: $request->has('description')
                ? $request->string('description')->toString()
                : null,
            location: $request->has('location')
                ? $request->string('location')->toString()
                : null,
            priority: $request->has('priority')
                ? IssuePriority::from(
                    $request->string('priority')->toString()
                )
                : null,
            reportedBy: null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'community_id' => $this->communityId,
            'category_id' => $this->categoryId,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'priority' => $this->priority?->value,
            'status' => IssueStatus::OPEN->value,
            'reported_by' => $this->reportedBy,
        ], static fn ($value) => $value !== null);
    }
}