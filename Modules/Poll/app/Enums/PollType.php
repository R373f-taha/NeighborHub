<?php

declare(strict_types=1);

namespace Modules\Poll\App\Enums;

enum PollType: string
{
    case SingleChoice = 'single_choice';

    public function label(): string
    {
        return match ($this) {
            self::SingleChoice => 'Single Choice',

        };
    }


}
