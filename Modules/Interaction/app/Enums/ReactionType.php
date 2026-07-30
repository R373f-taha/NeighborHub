<?php

declare(strict_types=1);

namespace Modules\Interaction\app\Enums;

enum ReactionType: string
{
    case Like = 'like';
    case Love = 'love';
    case Support = 'support';
    case Helpful = 'helpful';
    case Celebrate = 'celebrate';
}
