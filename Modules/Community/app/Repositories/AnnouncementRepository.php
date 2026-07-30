<?php

declare(strict_types=1);

namespace Modules\Community\app\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Repositories\Contracts\AnnouncementRepositoryInterface;

class AnnouncementRepository implements AnnouncementRepositoryInterface
{

    public function paginateByCommunity(
        int $communityId,
        int $perPage = 15
    ): LengthAwarePaginator {


        return Announcement::query()

            ->where(
                'community_id',
                $communityId
            )

            ->with([
                'creator:id,name,avatar',
                'media',
            ])

            ->withCount([
                'reactions',
                'comments',
            ])

            ->latest()

            ->paginate($perPage);
    }




    public function findById(
        int $communityId,
        int $announcementId
    ): ?Announcement {


        return Announcement::query()

            ->where(
                'community_id',
                $communityId
            )

            ->with([
                'creator:id,name,avatar',
                'comments.author:id,name,avatar',
                'media',
            ])

            ->withCount([
                'reactions',
                'comments',
            ])

            ->find($announcementId);
    }




    public function create(
        array $data
    ): Announcement {

        return Announcement::create($data);
    }




    public function update(
        Announcement $announcement,
        array $data
    ): bool {

        return $announcement->update($data);
    }




    public function delete(
        Announcement $announcement
    ): bool {

        return (bool) $announcement->delete();
    }




    public function hasUserReacted(
        Announcement $announcement,
        User $user
    ): bool {


        return $announcement
            ->reactions()
            ->where(
                'user_id',
                $user->id
            )
            ->exists();
    }
}