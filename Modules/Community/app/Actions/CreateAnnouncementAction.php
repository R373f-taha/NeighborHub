<?php

declare(strict_types=1);

namespace Modules\Community\app\Actions;

use Modules\Auth\app\Models\User;
use Modules\Community\app\DTOs\AnnouncementData;
use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Services\AnnouncementService;

class CreateAnnouncementAction
{
    public function __construct(
        private AnnouncementService $service
    ) {}


    public function execute(
        User $manager,
        array $data
    ): Announcement {

        $announcementData = new AnnouncementData(
            communityId: $data['community_id'],
            managerId: $manager->id,
            title: $data['title'],
            content: $data['content'],
            priority: $data['priority'],
            pinnedUntil: $data['pinned_until'] ?? null,
        );


        return $this->service->create($announcementData);
    }
}