<?php

declare(strict_types=1);

namespace Modules\Issue\app\Enums;

enum IssueStatus: string
{
    case OPEN = 'open';

    case ASSIGNED = 'assigned';

    case IN_PROGRESS = 'in_progress';

    case RESOLVED = 'resolved';

    case CLOSED = 'closed';
}