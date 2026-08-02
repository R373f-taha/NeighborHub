<?php

declare(strict_types=1);

namespace Modules\Community\app\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Services\AnnouncementService;
use Modules\Interaction\app\Models\Reaction;

class ReactToAnnouncementAction
{

    public function __construct(
        private AnnouncementService $service
    ) {}



    public function execute(
        Announcement $announcement,
        User $user,
        string $type
    ): Reaction {


        return DB::transaction(function () use (
            $announcement,
            $user,
            $type
        ) {


            $alreadyReacted =
                $announcement
                    ->reactions()
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->lockForUpdate()
                    ->exists();



            if ($alreadyReacted) {


                throw ValidationException::withMessages([
                    'reaction' =>
                        'You already reacted to this announcement.'
                ]);

            }





            return $announcement
                ->reactions()
                ->create([
                    'user_id'=>$user->id,
                    'type'=>$type,
                ]);

        });

    }

}