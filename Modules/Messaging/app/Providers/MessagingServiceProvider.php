<?php

namespace Modules\Messaging\app\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Policies\ConversationPolicy;

class MessagingServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Messaging';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'messaging';

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

        Gate::policy(Conversation::class, ConversationPolicy::class);

        $this->configureRateLimiting();
    }

    /**
     * Owned send-message rate limiter, registered module-locally (Auth is not
     * modified). Abuse-sensitive: 60 send attempts per minute keyed primarily
     * by the authenticated user id (never solely by IP for authenticated
     * traffic). Anonymous requests fall back to IP, though auth:sanctum guards
     * the route.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('messaging-send', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Define module schedules.
     *
     * @param $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
