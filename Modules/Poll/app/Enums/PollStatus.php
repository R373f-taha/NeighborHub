<?php

declare(strict_types=1);

namespace Modules\Poll\App\Enums;

enum PollStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Closed => 'Closed',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function isClosed(): bool
    {
        return $this === self::Closed;
    }

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    public function canVote(): bool
    {
        return $this === self::Active;
    }

    public function canViewResults(): bool
    {
        return $this === self::Closed;
    }
}
