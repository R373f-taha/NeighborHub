<?php

namespace Modules\Interaction\app\Providers;

use Illuminate\Support\Facades\Gate;
use Nwidart\Modules\Support\ModuleServiceProvider;

use Modules\Interaction\app\Models\Comment;
use Modules\Interaction\app\Policies\CommentPolicy;

class InteractionServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Interaction';
    protected string $nameLower = 'interaction';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Comment::class, CommentPolicy::class);
    }
}
