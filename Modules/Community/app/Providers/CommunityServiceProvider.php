<?php

declare(strict_types=1);

namespace Modules\Community\app\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Modules\Community\app\Repositories\Contracts\AnnouncementRepositoryInterface;
use Modules\Community\app\Repositories\AnnouncementRepository;

class CommunityServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Community';

    protected string $nameLower = 'community';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

  public function register(): void
{
    parent::register();

    $this->app->bind(
        AnnouncementRepositoryInterface::class,
        AnnouncementRepository::class
    );
}

    public function boot(): void
{
    parent::boot();
}
}