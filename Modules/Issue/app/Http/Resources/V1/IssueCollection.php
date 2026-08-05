<?php

declare(strict_types=1);

namespace Modules\Issue\app\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class IssueCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [

            'data' => IssueResource::collection(
                $this->collection
            ),

        ];
    }
}