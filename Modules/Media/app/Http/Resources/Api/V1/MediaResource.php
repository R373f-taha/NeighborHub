<?php

declare(strict_types=1);

namespace Modules\Media\app\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Media\app\Models\Media;
use Modules\Media\app\Services\MediaStorage;

/** @mixin Media */
class MediaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'url' => app(MediaStorage::class)->url($this->file_path, $this->disk),
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'size' => $this->file_size,
            'position' => (int) $this->position,
            'created_at' => $this->created_at,
        ];
    }
}
