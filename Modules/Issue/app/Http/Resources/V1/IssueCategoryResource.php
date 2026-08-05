<?php

declare(strict_types=1);

namespace Modules\Issue\app\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssueCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'name' => $this->name,

            'is_active' => $this->is_active,

        ];
    }
}