<?php

declare(strict_types=1);

namespace Modules\Post\app\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Post\app\Models\Post;

/** @mixin Post */
class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'category' => $this->category,
            'content' => $this->content,
            'is_pinned' => $this->is_pinned,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'author' => $this->whenLoaded('author', function () {
                $author = [
                    'id' => $this->author->id,
                ];

                if ($this->author->relationLoaded('user')) {
                    $author['name'] = $this->author->user?->name;
                }

                return $author;
            }),
            $this->mergeWhen($this->resource->getAttribute('comments_count') !== null, fn () => [
                'comments_count' => (int) $this->resource->getAttribute('comments_count'),
            ]),
        ];
    }
}
