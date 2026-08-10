<?php

declare(strict_types=1);

namespace Modules\Poll\app\Enums;

enum PollCloseReason: string
{
    case Manual = 'manual';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Closed by Manager',
            self::Expired => 'Automatically Expired',
        };
    }
}
