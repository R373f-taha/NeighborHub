<?php

declare(strict_types=1);

namespace Modules\Poll\app\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\Auth\app\Models\User;
use Modules\Poll\App\Console\CloseExpiredPolls;
use Modules\Poll\app\Console\SendPollReminders;
use Modules\Poll\app\Events\PollCreated;
use Modules\Poll\app\Events\PollClosed;
use Modules\Poll\app\Jobs\SendPollClosedNotificationJob;
use Modules\Poll\app\Listeners\SendPollCreatedNotification;
use Modules\Poll\app\Listeners\SendPollClosedNotification;
use Modules\Poll\app\Models\Poll;
use Modules\Poll\app\Policies\PollPolicy;

class PollServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Poll';
    protected string $moduleNameLower = 'poll';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/api.php'));

        //  Register Events & Listeners
        Event::listen(
            PollCreated::class,
            SendPollCreatedNotification::class
        );

        Event::listen(
            PollClosed::class,
            SendPollClosedNotification::class
        );
            $this->commands([
       CloseExpiredPolls::class,
       SendPollReminders::class,
    ]);

          Gate::policy(Poll::class, PollPolicy::class);

        // Register Commands
          $this->registerGates();



    }

    public function register(): void
    {
        $this->app->bind(
            \Modules\Poll\app\Services\V1\PollService::class,
        );
    }

    protected function registerGates(): void
    {
        Gate::define('view_polls', function (User $user) {
            return $user->isResident() || $user->isManager() || $user->isSuperAdmin();
        });

        Gate::define('create_poll', function (User $user) {
            return $user->isManager() || $user->isSuperAdmin();
        });

        Gate::define('vote_poll', function (User $user) {
            return $user->isResident();
        });

        Gate::define('close_poll', function (User $user) {
            return $user->isManager() || $user->isSuperAdmin();
        });

        Gate::define('activate_poll', function (User $user) {
            return $user->isManager() || $user->isSuperAdmin();
        });

        Gate::define('view_poll_results', function (User $user, Poll $poll) {
            // Manager can always view results
            if ($user->isManager() || $user->isSuperAdmin()) {
                return true;
            }

            // Resident can only view results after poll is closed
            if ($user->isResident()) {
                return $poll->isClosed();
            }

            return false;
        });
    }
}
