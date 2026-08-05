<?php

declare(strict_types=1);

namespace Modules\Issue\app\Listeners;

use Modules\Issue\app\Traits\CacheableIssueTrait;

class ClearIssueCache
{
    use CacheableIssueTrait;


    public function handle(
        object $event
    ): void {

        if (!isset($event->issue)) {
            return;
        }


        $this->clearIssueCache(
            $event->issue
        );
    }
}