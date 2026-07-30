<?php

declare(strict_types=1);

namespace Modules\Community\app\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Community\app\Events\AnnouncementCreated;
use Modules\Community\app\Events\AnnouncementDeleted;

class LogAnnouncementActivity
{

    public function handle(
        AnnouncementCreated|AnnouncementDeleted $event
    ): void {

        $announcement = $event->announcement;


        if ($event instanceof AnnouncementCreated) {

            Log::channel('security')->info(
                'announcement.created',
                [
                    'announcement_id' => $announcement->id,
                    'community_id' => $announcement->community_id,
                    'created_by' => $announcement->created_by_manager,
                ]
            );

            return;
        }


        Log::channel('security')->warning(
            'announcement.deleted',
            [
                'announcement_id' => $announcement->id,
                'community_id' => $announcement->community_id,
                'deleted_by' => $announcement->created_by_manager,
            ]
        );
    }
}