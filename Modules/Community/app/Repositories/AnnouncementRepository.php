<?php

declare(strict_types=1);

namespace Modules\Community\app\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Repositories\Contracts\AnnouncementRepositoryInterface;
use Modules\Community\app\Traits\AnnouncementCacheableTrait;

class AnnouncementRepository implements AnnouncementRepositoryInterface
{

    use AnnouncementCacheableTrait;



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


        $key = $this->announcementCacheKey(
            'single',
            $communityId,
            $announcementId
        );



        return $this->rememberAnnouncement(
            $key,
            self::ANNOUNCEMENT_CACHE_TTL,
            function () use (
                $communityId,
                $announcementId
            ) {


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


            },
            $communityId
        );

    }





    public function create(
        array $data
    ): Announcement {


        $announcement = Announcement::create($data);



        $this->clearAnnouncementCache(
            $announcement->community_id
        );



        return $announcement;

    }





    public function update(
        Announcement $announcement,
        array $data
    ): bool {


        $updated = $announcement->update($data);



        if ($updated) {

            $this->clearAnnouncementCache(
                $announcement->community_id
            );

        }



        return $updated;

    }





    public function delete(
        Announcement $announcement
    ): bool {


        $communityId = $announcement->community_id;



        $deleted = (bool) $announcement->delete();



        if ($deleted) {

            $this->clearAnnouncementCache(
                $communityId
            );

        }



        return $deleted;

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