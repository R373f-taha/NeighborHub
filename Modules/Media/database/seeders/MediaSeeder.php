<?php

declare(strict_types=1);

namespace Modules\Media\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Announcement;
use Modules\Issue\app\Models\Issue;
use Modules\Media\app\Models\Media;

class MediaSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->get();


        Announcement::query()
            ->each(function ($announcement) use ($users) {

                Media::factory()
                    ->count(2)
                    ->create([

                        'mediable_type' => Announcement::class,

                        'mediable_id' => $announcement->id,

                        'uploaded_by' => $users->random()->id,

                    ]);

            });



        Issue::query()
            ->each(function ($issue) use ($users) {

                Media::factory()
                    ->count(2)
                    ->create([

                        'mediable_type' => Issue::class,

                        'mediable_id' => $issue->id,

                        'uploaded_by' => $users->random()->id,

                    ]);

            });
    }
}