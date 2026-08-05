<?php

declare(strict_types=1);

namespace Modules\Messaging\app\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Messaging\app\Models\Message;

/** @mixin Message */
class MessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender' => $this->whenLoaded('sender', fn () => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
            ]),
            'content' => $this->content,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
