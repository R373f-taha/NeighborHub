<?php

declare(strict_types=1);

namespace  Modules\Poll\app\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Poll\app\Models\Poll;

class PollClosed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Poll $poll
    ) {}
}
