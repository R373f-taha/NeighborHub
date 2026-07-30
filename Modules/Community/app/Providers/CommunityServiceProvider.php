<?php

declare(strict_types=1);

namespace Modules\Community\app\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Policies\CommunityPolicy;

class CommunityServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Community';
    protected string $moduleNameLower = 'community';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/api.php'));

        // Register Policies
        Gate::policy(Community::class, CommunityPolicy::class);
    }

    public function register(): void
    {
        $this->app->bind(
            \Modules\Community\app\Services\CommunityService::class,
        );
    }
}
