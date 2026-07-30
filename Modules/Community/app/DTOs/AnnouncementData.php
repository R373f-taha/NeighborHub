<?php

declare(strict_types=1);

namespace Modules\Community\app\DTOs;

final readonly class AnnouncementData
{

    public function __construct(
        public int $communityId,
        public int $managerId,
        public string $title,
        public string $content,
        public string $priority,
        public ?string $pinnedUntil = null,
    ) {}


    public function toArray(): array
    {
        return [
            'community_id' => $this->communityId,
            'created_by_manager' => $this->managerId,
            'title' => $this->title,
            'content' => $this->content,
            'priority' => $this->priority,
            'pinned_until' => $this->pinnedUntil,
        ];
    }
}