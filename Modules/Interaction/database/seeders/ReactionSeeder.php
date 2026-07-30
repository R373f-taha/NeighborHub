<?php

declare(strict_types=1);

namespace Modules\Interaction\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Announcement;
use Modules\Interaction\app\Models\Reaction;

class ReactionSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->get();

        if ($users->isEmpty()) {
            return;
        }

        Announcement::query()
            ->each(function ($announcement) use ($users) {

                $take = min(10, $users->count());

                foreach ($users->random($take) as $user) {

                    Reaction::firstOrCreate([
                        'reactionable_type' => Announcement::class,
                        'reactionable_id' => $announcement->id,
                        'user_id' => $user->id,
                    ]);

                }

            });
    }
}