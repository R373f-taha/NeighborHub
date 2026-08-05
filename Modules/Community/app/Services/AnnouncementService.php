<?php

declare(strict_types=1);

namespace Modules\Community\app\Services;

use Illuminate\Support\Facades\DB;
use Modules\Auth\app\Models\User;
use Modules\Community\app\DTOs\AnnouncementData;
use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Repositories\Contracts\AnnouncementRepositoryInterface;
use Modules\Community\app\Events\AnnouncementCreated;
use Modules\Community\app\Events\AnnouncementDeleted;
use Modules\Community\app\Traits\AnnouncementCacheableTrait;

class AnnouncementService
{

    use AnnouncementCacheableTrait;


    public function __construct(
        private AnnouncementRepositoryInterface $repository
    ) {}



    public function create(
        AnnouncementData $data
    ): Announcement {


        return DB::transaction(function () use ($data) {


            $announcement =
                $this->repository->create(
                    $data->toArray()
                );



            event(
                new AnnouncementCreated(
                    $announcement
                )
            );



            $this->clearAnnouncementCache(
                $announcement->community_id
            );



            return $announcement;

        });

    }





    public function update(
        Announcement $announcement,
        array $data
    ): bool {


        return DB::transaction(function () use (
            $announcement,
            $data
        ) {


            $updated =
                $this->repository->update(
                    $announcement,
                    $data
                );



            if ($updated) {

                $this->clearAnnouncementCache(
                    $announcement->community_id
                );

            }



            return $updated;

        });

    }





    public function delete(
        Announcement $announcement
    ): bool {


        return DB::transaction(function () use ($announcement) {


            $communityId =
                $announcement->community_id;



            $deleted =
                $this->repository->delete(
                    $announcement
                );



            if ($deleted) {


                event(
                    new AnnouncementDeleted(
                        $announcement
                    )
                );



                $this->clearAnnouncementCache(
                    $communityId
                );

            }



            return $deleted;

        });

    }





    public function hasUserReacted(
        Announcement $announcement,
        User $user
    ): bool {


        return $this->repository
            ->hasUserReacted(
                $announcement,
                $user
            );

    }


}