<?php

declare(strict_types=1);

namespace Modules\Auth\app\Exceptions;

use RuntimeException;


class LastSuperAdminException extends RuntimeException
{
    public static function forDemotion(): self
    {
        return new self('Cannot demote the last remaining super_admin.');
    }
}
