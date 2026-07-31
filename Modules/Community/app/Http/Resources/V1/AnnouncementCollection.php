<?php

declare(strict_types=1);

namespace Modules\Community\app\Http\Resources\V1;

use Illuminate\Http\Resources\Json\ResourceCollection;

class AnnouncementCollection extends ResourceCollection
{

    public $collects = AnnouncementResource::class;


    public function toArray($request): array
    {
        return parent::toArray($request);
    }
}