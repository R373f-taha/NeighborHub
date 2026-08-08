<?php

declare(strict_types=1);

namespace Modules\Messaging\app\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dedicated Mark Read response: exposes ONLY the actor's own read-state fields
 * (conversation_id, the actor's last_read_message_id, and derived
 * unread_count). Other participants' cursors are never exposed here.
 *
 * @param array{conversation_id: int, last_read_message_id: ?int, unread_count: int} $resource
 */
class ReadStateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'conversation_id' => $this->resource['conversation_id'],
            'last_read_message_id' => $this->resource['last_read_message_id'],
            'unread_count' => $this->resource['unread_count'],
        ];
    }
}
