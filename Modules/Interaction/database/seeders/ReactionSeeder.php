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


        Announcement::query()
            ->each(function ($announcement) use ($users) {


                foreach ($users->random(10) as $user) {


                    Reaction::factory()
                        ->create([
                            'reactionable_type' => Announcement::class,

                            'reactionable_id' => $announcement->id,

                            'user_id' => $user->id,
                        ]);

                }

            });
    }
}