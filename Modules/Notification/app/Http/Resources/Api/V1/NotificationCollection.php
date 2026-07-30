<?php

declare(strict_types=1);

namespace Modules\Notification\app\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\ResourceCollection;


class NotificationCollection extends ResourceCollection
{

    public $collects = NotificationResource::class;

}