<?php

declare(strict_types=1);

namespace Modules\Media\app\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolved ownership context for a Media row, derived entirely from trusted
 * DB state (never from request input). Only constructed for owned parent
 * types; every other stored mediable_type is rejected upstream with a
 * privacy-safe 404.
 */
final class MediaContext
{
    public function __construct(
        public readonly string $alias,
        public readonly int $communityId,
        public readonly Model $parent,
    ) {}
}
