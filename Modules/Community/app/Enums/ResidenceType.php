<?php

declare(strict_types=1);

namespace Modules\Community\app\Enums;

enum ResidenceType: string
{
    case Owner = 'owner';
    case Tenant = 'tenant';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'مالك',
            self::Tenant => 'مستأجر',
        };
    }
}
