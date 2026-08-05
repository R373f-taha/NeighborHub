<?php

declare(strict_types=1);

namespace Modules\Issue\app\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

use Modules\Issue\app\Events\IssueCreated;
use Modules\Issue\app\Events\IssueAssigned;
use Modules\Issue\app\Events\IssueStatusUpdated;
use Modules\Issue\app\Events\IssueDeleted;
use Modules\Issue\app\Events\IssueLogAdded;

use Modules\Issue\app\Listeners\ClearIssueCache;
use Modules\Issue\app\Listeners\LogIssueActivity;
use Modules\Issue\app\Listeners\SendIssueNotifications;


class EventServiceProvider extends ServiceProvider
{
    protected $listen = [

        IssueCreated::class => [
            ClearIssueCache::class,
            LogIssueActivity::class,
           
        ],


        IssueAssigned::class => [
            ClearIssueCache::class,
            LogIssueActivity::class,
            SendIssueNotifications::class,
        ],


        IssueStatusUpdated::class => [
            ClearIssueCache::class,
            LogIssueActivity::class,
            SendIssueNotifications::class,
        ],


        IssueDeleted::class => [
            ClearIssueCache::class,
            LogIssueActivity::class,
        ],


        IssueLogAdded::class => [
            LogIssueActivity::class,
        ],

    ];
}