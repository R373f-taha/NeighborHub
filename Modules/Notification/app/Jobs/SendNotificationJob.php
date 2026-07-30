<?php

declare(strict_types=1);

namespace Modules\Notification\app\Jobs;


use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Modules\Community\app\Models\Announcement;
use Modules\Notification\app\Services\NotificationService;


class SendNotificationJob implements ShouldQueue
{

    use Dispatchable,
        InteractsWithQueue,
        Queueable,
        SerializesModels;



    public function __construct(
        public Announcement $announcement
    ){}




    public function handle(
        NotificationService $service
    ): void {


        $community =
            $this->announcement
                ->community;



        $residents =
            $community
                ->residents()
                ->with('user')
                ->get();



        foreach($residents as $resident){


            if(!$resident->user){
                continue;
            }



            $service->send(

                $resident->user,

                'New Announcement',

                $this->announcement->title,

                'announcement',

                $this->announcement,

                [
                    'announcement_id'=>$this->announcement->id
                ]

            );

        }


    }

}