<?php

declare(strict_types=1);

namespace Modules\Media\app\Support;

use Modules\Post\app\Models\Post;
use Modules\ServiceListing\app\Models\ServiceListing;


final class MediaParentType
{
    public const string POST = 'post';

    public const string SERVICE_LISTING = 'service_listing';

    /**
     * Owned alias => trusted parent class. This also feeds the central morph
     * map so stored mediable_type values and morph relations share one map.
     *
     * @return array<string, class-string>
     */
    public static function map(): array
    {
        return [
            self::POST => Post::class,
            self::SERVICE_LISTING => ServiceListing::class,
        ];
    }

    /** @return list<string> */
    public static function aliases(): array
    {
        return array_keys(self::map());
    }
}
