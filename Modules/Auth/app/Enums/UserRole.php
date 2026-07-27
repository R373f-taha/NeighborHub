<?php

declare(strict_types=1);

namespace Modules\Auth\app\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Manager = 'manager';
    case Resident = 'resident';
    case Provider = 'provider';
}
