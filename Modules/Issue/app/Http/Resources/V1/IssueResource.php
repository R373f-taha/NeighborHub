<?php

declare(strict_types=1);

namespace Modules\Issue\app\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'title' => $this->title,

            'description' => $this->description,

            'location' => $this->location,


            'priority' => $this->priority?->value,

            'status' => $this->status?->value,


            'category' => new IssueCategoryResource(
                $this->whenLoaded('category')
            ),


            'community' => [
                'id' => $this->community?->id,
                'name' => $this->community?->name,
            ],


            'reported_by' => [
                'id' => $this->reporter?->id,
                'name' => $this->reporter?->name,
            ],


            'assigned_to' => $this->assignee ? [

                'id' => $this->assignee->id,

                'name' => $this->assignee->name,

            ] : null,


            'status_logs' => IssueStatusLogResource::collection(
                $this->whenLoaded('statusLogs')
            ),


            // Interaction Module
            'comments' => $this->whenLoaded(
                'comments'
            ),


            'reactions' => $this->whenLoaded(
                'reactions'
            ),


            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,

        ];
    }
}