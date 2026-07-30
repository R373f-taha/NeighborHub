<?php

declare(strict_types=1);

namespace Modules\Community\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\CommunityManager;

class CommunityManagerSeeder extends Seeder
{
    public function run(): void
    {
        $managers = User::query()
            ->where('role', 'manager')
            ->get();

        if ($managers->isEmpty()) {
            return;
        }

        Community::query()
            ->each(function (Community $community) use ($managers) {

                $manager = $managers->random();

                CommunityManager::updateOrCreate([
                    'community_id' => $community->id,
                    'manager_id' => $manager->id,
                ]);

            });
    }
}