<?php

declare(strict_types=1);

namespace Modules\Poll\App\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Poll\App\Enums\PollStatus;
use UnitEnum;

class PollResource extends JsonResource
{
    public function toArray($request)
    {
        $status = is_string($this->status) ? $this->status : $this->status?->value;
        $isActive = $status === 'active';
        $isClosed = $status === 'closed';
        $isExpired = $this->ends_at && $this->ends_at->isPast();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,

            'type' => is_string($this->type) ? $this->type : $this->type?->value,
            'type_label' => $this->type instanceof PollStatus ? $this->type->label() : null,

            'status' => $status,
            'status_label' => $this->status instanceof PollStatus? $this->status->label() : null,

            'ends_at' => $this->ends_at?->toISOString(),
            'activated_at' => $this->activated_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'close_reason' => is_string($this->close_reason) ? $this->close_reason : $this->close_reason?->value,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            'options' =>$this->whenLoaded('options'),

            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),

            'community' => $this->whenLoaded('community', function () {
                return [
                    'id' => $this->community->id,
                    'name' => $this->community->name,
                ];
            }),

            'stats' => [
                'total_votes' => $this->getTotalVotesCount(),
                'turnout' => $this->getTurnoutPercentage(),
                'can_vote' => $isActive && !$isExpired,
                'can_view_results' => $isClosed,
            ],
        ];
    }
}
