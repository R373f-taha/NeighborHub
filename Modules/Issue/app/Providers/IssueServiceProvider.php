<?php

declare(strict_types=1);

namespace Modules\Issue\app\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Models\IssueCategory;
use Modules\Issue\app\Observers\IssueObserver;

use Modules\Issue\app\Repositories\Contracts\IssueRepositoryInterface;
use Modules\Issue\app\Repositories\IssueRepository;


class IssueServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->app->bind(
            IssueRepositoryInterface::class,
            IssueRepository::class
        );
    }


public function boot(): void
{
    $this->loadRoutesFrom(
        module_path('Issue', 'routes/api.php')
    );

    Issue::observe(IssueObserver::class);

    Route::model('issue', Issue::class);
    Route::model('category', IssueCategory::class);
}

}