<?php

declare(strict_types=1);

namespace Modules\Community\app\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Community\app\Events\AnnouncementCreated;
use Modules\Community\app\Events\AnnouncementDeleted;
use Modules\Community\app\Listeners\LogAnnouncementActivity;
use Modules\Community\app\Listeners\SendAnnouncementNotification;

class EventServiceProvider extends ServiceProvider
{

    protected $listen = [

        AnnouncementCreated::class => [

            SendAnnouncementNotification::class,

            LogAnnouncementActivity::class,

        ],
        AnnouncementDeleted::class => [
         LogAnnouncementActivity::class,

        ],

    ];

    public function boot(): void
    {
        parent::boot();
    }
}