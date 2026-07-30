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

class AnnouncementService
{

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


            return $announcement;
        });
    }



    public function update(
        Announcement $announcement,
        array $data
    ): bool {

        return $this->repository->update(
            $announcement,
            $data
        );
    }



    public function delete(
        Announcement $announcement
    ): bool {


        return DB::transaction(function () use ($announcement) {

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