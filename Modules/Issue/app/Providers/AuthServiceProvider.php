<?php

declare(strict_types=1);

namespace Modules\Issue\app\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Policies\IssuePolicy;


class AuthServiceProvider extends ServiceProvider
{

    protected $policies = [

        Issue::class => IssuePolicy::class,

    ];



    public function boot(): void
    {
        $this->registerPolicies();


        Gate::policy(
            Issue::class,
            IssuePolicy::class
        );
    }

}