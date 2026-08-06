<?php

declare(strict_types=1);

namespace Modules\ServiceListing\app\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Media\app\Http\Resources\Api\V1\MediaResource;
use Modules\ServiceListing\app\Models\ServiceListing;

/** @mixin ServiceListing */
class ServiceListingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'price' => $this->price,
            'status' => $this->status,
            'expires_at' => $this->expires_at,
            'closed_at' => $this->closed_at,
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
            'media' => MediaResource::collection($this->whenLoaded('media')),
        ];
    }
}
