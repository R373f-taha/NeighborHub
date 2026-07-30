<?php

declare(strict_types=1);

namespace Modules\Community\app\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Community\app\Models\Announcement;

class AnnouncementCreated
{
    use Dispatchable, SerializesModels;


    public function __construct(
        public Announcement $announcement
    ) {}
}