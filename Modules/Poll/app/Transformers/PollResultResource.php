<?php

declare(strict_types=1);

namespace Modules\Poll\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PollResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'poll_id' => $this['poll_id'],
            'title' => $this['title'],
            'description' => $this['description'],
            'total_votes' => $this['total_votes'],
            'turnout' => $this['turnout'],
            'status' => $this['status'],
            'closed_at' => $this['closed_at'],
            'options' => $this['options'],
        ];
    }
}
