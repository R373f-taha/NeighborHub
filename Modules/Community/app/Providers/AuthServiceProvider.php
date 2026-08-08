<?php

declare(strict_types=1);

namespace Modules\Community\app\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Policies\AnnouncementPolicy;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Policies\CommunityPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [

        Announcement::class => AnnouncementPolicy::class,
        Community::class => CommunityPolicy::class,
    ];



    public function boot(): void
    {
        $this->registerPolicies();

        Gate::policy(
            Announcement::class,
            AnnouncementPolicy::class
        );

        Gate::policy(
            Community::class,
            CommunityPolicy::class
        );
    }
}
