<?php

declare(strict_types=1);

namespace Modules\Issue\app\Enums;

enum IssuePriority: string
{
    case LOW = 'low';

    case MEDIUM = 'medium';

    case HIGH = 'high';

    case URGENT = 'urgent';
}