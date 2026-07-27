<?php

declare(strict_types=1);

namespace Modules\Issue\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Issue\app\Models\Issue;

class IssueSeeder extends Seeder
{
    public function run(): void
    {
        $residents = User::query()
            ->where('role', 'resident')
            ->get();


        $managers = User::query()
            ->where('role', 'manager')
            ->get();


        Community::query()
            ->each(function (Community $community) use ($residents, $managers) {


                Issue::factory()
                    ->count(10)
                    ->create([

                        'community_id' => $community->id,

                        'reported_by' => $residents->random()->id,

                        'assigned_to' => fake()->boolean(60)
                            ? $managers->random()->id
                            : null,

                    ]);

            });
    }
}