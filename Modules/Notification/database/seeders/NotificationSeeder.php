<?php

declare(strict_types=1);

namespace Modules\Notification\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Announcement;
use Modules\Issue\app\Models\Issue;
use Modules\Notification\app\Models\Notification;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->get();


        Announcement::query()
            ->each(function ($announcement) use ($users) {


                foreach ($users->random(5) as $user) {

                    Notification::factory()
                        ->create([

                            'user_id' => $user->id,

                            'notifiable_type' => Announcement::class,

                            'notifiable_id' => $announcement->id,

                            'type' => 'announcement',

                        ]);

                }

            });



        Issue::query()
            ->each(function ($issue) use ($users) {


                foreach ($users->random(5) as $user) {

                    Notification::factory()
                        ->create([

                            'user_id' => $user->id,

                            'notifiable_type' => Issue::class,

                            'notifiable_id' => $issue->id,

                            'type' => 'issue',

                        ]);

                }

            });
    }
}