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


        if ($this->service->hasUserReacted(
            $announcement,
            $user
        )) {

            throw ValidationException::withMessages([
                'reaction' =>
                    'You already reacted to this announcement.'
            ]);
        }
        return DB::transaction(function () use (
            $announcement,
            $user,
            $type
        ) {

            return $announcement
                ->reactions()
                ->create([
                    'user_id' => $user->id,
                    'type' => $type,
                ]);
        });
    }
}