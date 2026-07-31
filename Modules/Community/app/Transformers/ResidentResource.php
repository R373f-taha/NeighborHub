<?php

declare(strict_types=1);

namespace Modules\Community\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
                'phone' => $this->user?->phone,
            ],
            'unit' => [
                'id' => $this->unit?->id,
                'unit_number' => $this->unit?->unit_number,
                'building_name' => $this->unit?->building_name,
            ],
            'residence_type' => $this->residence_type,
            'status' => $this->status,
            'current_marker' => $this->current_marker,
            'joined_at' => $this->joined_at?->toISOString(),
            'left_at' => $this->left_at?->toISOString(),
        ];
    }
}
