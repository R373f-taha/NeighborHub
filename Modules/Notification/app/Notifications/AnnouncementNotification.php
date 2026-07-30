<?php

declare(strict_types=1);

namespace Modules\Notification\app\Notifications;


use Modules\Community\app\Models\Announcement;



class AnnouncementNotification
{


    public function __construct(
        private Announcement $announcement
    ) {}





    public function data(): array
    {

        return [

            'title'=>'New Announcement',


            'body'=>$this->announcement->title,


            'type'=>'announcement',


            'data'=>[

                'announcement_id'=>$this->announcement->id,

                'community_id'=>$this->announcement->community_id,

            ],


            'notifiable_type'=>
                Announcement::class,


            'notifiable_id'=>$this->announcement->id,


        ];

    }

}