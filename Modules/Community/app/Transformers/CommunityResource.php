<?php

declare(strict_types=1);

namespace Modules\Community\App\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Community\App\Transformers\UnitResource;
use Modules\Community\App\Transformers\ResidentResource;
use Modules\Post\App\Transformers\PostResource;
use Modules\Announcement\App\Transformers\AnnouncementResource;
use Modules\Issue\App\Transformers\IssueResource;
use Modules\Poll\App\Transformers\PollResource;
use Modules\ServiceListing\App\Transformers\ServiceListingResource;

class CommunityResource extends JsonResource
{
    public function toArray($request)
    {
        if (is_null($this->resource)) {
            return [];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
            'address' => $this->address,
            'description' => $this->when($this->description, $this->description),
            'cover_image' => $this->when($this->cover_image, $this->cover_image),
            'total_units' => $this->total_units,
            'is_active' => (bool) $this->is_active,
            'is_accepting_members' => $this->when(isset($this->is_accepting_members), $this->is_accepting_members),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),


            // 'units' => $this->whenLoaded('units', function () {
            //     return UnitResource::collection($this->units);
            // }),

            'residents' => $this->whenLoaded('residents', function () {
                return ResidentResource::collection($this->residents);
            }),

            'active_residents' => $this->whenLoaded('activeResidents', function () {
                return ResidentResource::collection($this->activeResidents);
            }),

            'managers' => $this->whenLoaded('managers', function () {
                return $this->managers->map(function ($manager) {
                    return [
                        'id' => $manager->id,
                        'name' => $manager->name,
                        'email' => $manager->email,
                        'avatar' => $manager->avatar,
                    ];
                });
            }),

            'created_by' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            //At the future tasks

            // 'announcements' => $this->whenLoaded('announcements', function () {
            //     return AnnouncementResource::collection($this->announcements);
            // }),

            // 'posts' => $this->whenLoaded('posts', function () {
            //     return PostResource::collection($this->posts);
            // }),

            // 'issues' => $this->whenLoaded('issues', function () {
            //     return IssueResource::collection($this->issues);
            // }),

            // 'polls' => $this->whenLoaded('polls', function () {
            //     return PollResource::collection($this->polls);
            // }),

            // 'service_listings' => $this->whenLoaded('serviceListings', function () {
            //     return ServiceListingResource::collection($this->serviceListings);
            // }),

            // 'conversations' => $this->whenLoaded('conversations', function () {
            //     return $this->conversations->map(function ($conversation) {
            //         return [
            //             'id' => $conversation->id,
            //             'type' => $conversation->type,
            //             'status' => $conversation->status,
            //             'last_message_at' => $conversation->last_message_at?->toISOString(),
            //         ];
            //     });
            // }),



        ];
    }
}
