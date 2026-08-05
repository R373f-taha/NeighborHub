<?php

declare(strict_types=1);

namespace Modules\Media\app\Exceptions;

use RuntimeException;

class MediaLimitExceededException extends RuntimeException
{
    public static function forParent(int $limit): self
    {
        return new self("A parent may have at most {$limit} images.");
    }
}
