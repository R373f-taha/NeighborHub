<?php

namespace Modules\Post\app\Providers;

use Illuminate\Support\Facades\Gate;
use Nwidart\Modules\Support\ModuleServiceProvider;

use Modules\Post\app\Models\Post;
use Modules\Post\app\Policies\PostPolicy;

class PostServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Post';
    protected string $nameLower = 'post';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Post::class, PostPolicy::class);
    }
}
