<?php

declare(strict_types=1);

namespace Modules\Community\app\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Policies\CommunityPolicy;
use Modules\Community\app\Services\V1\CommunityService;
use Modules\Community\app\Repositories\Contracts\AnnouncementRepositoryInterface;
use Modules\Community\app\Repositories\AnnouncementRepository;
use Modules\Community\app\Http\Middleware\ManagerMiddleware;
use Modules\Community\app\Http\Middleware\ManagerOrSuperAdminMiddleware;
use Modules\Community\app\Http\Middleware\ResidentMiddleware;
use Modules\Community\app\Http\Middleware\SuperAdminMiddleware;
use Modules\Community\app\Http\Middleware\ProviderMiddleware;

class CommunityServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Community';
    protected string $moduleNameLower = 'community';

    public function register(): void
    {
        $this->app->bind(
            AnnouncementRepositoryInterface::class,
            AnnouncementRepository::class
        );

        $this->app->singleton(CommunityService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/api.php'));

        Gate::policy(Community::class, CommunityPolicy::class);

        $this->registerMiddleware();
    }


    protected function registerMiddleware(): void
    {
        Route::aliasMiddleware('resident', ResidentMiddleware::class);
        Route::aliasMiddleware('manager', ManagerMiddleware::class);
        Route::aliasMiddleware('super.admin', SuperAdminMiddleware::class);
        Route::aliasMiddleware('provider', ProviderMiddleware::class);
        Route::aliasMiddleware('manager.or.super.admin', ManagerOrSuperAdminMiddleware::class);
    }
}