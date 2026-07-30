<?php

declare(strict_types=1);

namespace Modules\Community\app\Providers;

use Modules\Community\App\Http\Middleware\ManagerMiddleware;

use Modules\Community\App\Http\Middleware\ManagerOrSuperAdminMiddleware;
use Modules\Community\App\Http\Middleware\ResidentMiddleware;

use Modules\Community\App\Http\Middleware\SuperAdminMiddleware;
use Modules\Community\App\Http\Middleware\ProviderMiddleware;


use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Policies\CommunityPolicy;
use Modules\Community\app\Services\V1\CommunityService;

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

        // ✅ Register Middlewares
        $this->registerMiddleware();
    }

    public function register(): void
    {
        $this->app->bind(
            CommunityService::class,
        );
    }

    /**
     * ✅ Register custom middlewares from the module
     */
    protected function registerMiddleware(): void
    {


        Route::aliasMiddleware('resident', ResidentMiddleware::class);
         Route::aliasMiddleware('manager', ManagerMiddleware::class);
        Route::aliasMiddleware('super.admin', SuperAdminMiddleware::class);
        Route::aliasMiddleware('provider', ProviderMiddleware::class);
        Route::aliasMiddleware('manager.or.super.admin', ManagerOrSuperAdminMiddleware::class);
    }
}
