<?php

declare(strict_types=1);

namespace Modules\Interaction\app\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Interaction\app\Models\Comment;

/** @mixin Comment */
class CommentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'parent_id' => $this->parent_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ]),
            $this->mergeWhen($this->resource->getAttribute('replies_count') !== null, fn () => [
                'replies_count' => (int) $this->resource->getAttribute('replies_count'),
            ]),
        ];
    }
}
