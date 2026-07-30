<?php

declare(strict_types=1);

namespace Modules\Community\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Models\Community;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $managers = User::query()
            ->where('role', 'manager')
            ->pluck('id');


        Community::query()
            ->each(function (Community $community) use ($managers) {

                Announcement::factory()
                    ->count(5)
                    ->create([
                        'community_id' => $community->id,
                        'created_by_manager' => $managers->random(),
                    ]);

            });
    }
}