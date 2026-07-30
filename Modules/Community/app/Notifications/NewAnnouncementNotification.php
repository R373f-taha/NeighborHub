<?php

declare(strict_types=1);

namespace Modules\Community\app\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Modules\Community\app\Models\Announcement;


class NewAnnouncementNotification extends Notification implements ShouldQueue
{

    use Queueable;



    public function __construct(
        private Announcement $announcement
    ) {}



    public function via(
        $notifiable
    ): array {

        return [
            'database'
        ];
    }



    public function toDatabase(
        $notifiable
    ): array {


        return [

            'type' => 'announcement',

            'announcement_id' =>
                $this->announcement->id,

            'community_id' =>
                $this->announcement->community_id,

            'title' =>
                $this->announcement->title,

            'message' =>
                'New announcement published',

        ];
    }
}