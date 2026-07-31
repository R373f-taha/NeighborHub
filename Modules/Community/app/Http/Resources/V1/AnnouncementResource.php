<?php

declare(strict_types=1);

namespace Modules\Community\app\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'title' => $this->title,

            'content' => $this->content,

            'priority' => $this->priority,

            'pinned_until' => $this->pinned_until,

            'creator' => [

                'id' => $this->creator?->id,

                'name' => $this->creator?->name,

                'avatar' => $this->creator?->avatar,

            ],


            'reactions_count' =>
                $this->whenLoaded(
                    'reactions',
                    fn () => $this->reactions->count()
                ),


            'comments_count' =>
                $this->whenLoaded(
                    'comments',
                    fn () => $this->comments->count()
                ),


            'media' =>
                $this->whenLoaded('media'),


            'created_at' => $this->created_at,

        ];
    }
}