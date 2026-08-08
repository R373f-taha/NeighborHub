<?php

declare(strict_types=1);

namespace Modules\Media\app\Exceptions;

use RuntimeException;

class MediaPositionConflictException extends RuntimeException
{
    public static function forPosition(int $position): self
    {
        return new self("Position {$position} is already in use for this parent.");
    }
}
