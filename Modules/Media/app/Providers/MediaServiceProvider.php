<?php

namespace Modules\Media\app\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Media\app\Support\MediaParentType;
use Nwidart\Modules\Support\ModuleServiceProvider;

class MediaServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Media';

    protected string $nameLower = 'media';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap(MediaParentType::map());
    }
}
