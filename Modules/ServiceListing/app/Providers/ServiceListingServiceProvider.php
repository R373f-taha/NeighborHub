<?php

namespace Modules\ServiceListing\app\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Modules\ServiceListing\app\Models\ServiceListing;
use Modules\ServiceListing\app\Policies\ServiceListingPolicy;

class ServiceListingServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'ServiceListing';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'servicelisting';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Gate::policy(ServiceListing::class, ServiceListingPolicy::class);
    }

  
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
