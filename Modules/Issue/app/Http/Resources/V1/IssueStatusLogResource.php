<?php

declare(strict_types=1);

namespace Modules\Issue\app\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssueStatusLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'old_status' => $this->old_status?->value,

            'new_status' => $this->new_status?->value,

            'note' => $this->note,

            'changed_by' => $this->changer?->id,

            'changer' => [
                'id' => $this->changer?->id,
                'name' => $this->changer?->name,
            ],

            'created_at' => $this->created_at,

        ];
    }
}