<?php

declare(strict_types=1);

namespace Modules\Community\app\Actions;

use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Services\AnnouncementService;

class DeleteAnnouncementAction
{

    public function __construct(
        private AnnouncementService $service
    ) {}


    public function execute(
        Announcement $announcement
    ): bool {

        return $this->service->delete($announcement);
    }
}