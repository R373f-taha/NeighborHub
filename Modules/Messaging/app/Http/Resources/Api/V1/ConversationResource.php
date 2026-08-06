<?php

declare(strict_types=1);

namespace Modules\Messaging\app\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Messaging\app\Models\Conversation;

/** @mixin Conversation */
class ConversationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'type' => $this->type,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // DERIVED unread_count for the authenticated participant only (set
            // as a runtime attribute by ConversationReadStateService). Never a
            // stored counter; defaults to 0 when not attached. Other
            // participants' read cursors are never exposed.
            'unread_count' => $this->resource->getAttribute('unread_count') ?? 0,
            'participants' => $this->whenLoaded('participants', function () {
                // Only minimal public conversation identity is exposed:
                // the user's id + name. We never expose the participant
                // database id, joined_at, left_at, last_read_message_id,
                // email, phone, tokens, roles, or pivot internals.
                return $this->participants
                    ->map(fn ($participant) => [
                        'id' => $participant->user?->id,
                        'name' => $participant->user?->name,
                    ])
                    ->values();
            }),
        ];
    }
}
