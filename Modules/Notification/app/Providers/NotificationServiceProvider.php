<?php

declare(strict_types=1);

namespace Modules\Notification\app\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Modules\Notification\app\Repositories\Contracts\NotificationRepositoryInterface;
use Modules\Notification\app\Repositories\NotificationRepository;

class NotificationServiceProvider extends ModuleServiceProvider
{

    protected string $name = 'Notification';

    protected string $nameLower = 'notification';



    protected array $providers = [
        RouteServiceProvider::class,
    ];



    public function register(): void
    {

        $this->app->bind(

            NotificationRepositoryInterface::class,

            NotificationRepository::class

        );

    }

}