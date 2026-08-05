<?php

declare(strict_types=1);

namespace Modules\Issue\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Models\IssueCategory;

class IssueSeeder extends Seeder
{
    public function run(): void
    {

        $residents = User::role('resident')->get();

        $managers = User::role('manager')->get();

        $categories = IssueCategory::query()
            ->where('is_active', true)
            ->get();



        if (
            $residents->isEmpty() ||
            $categories->isEmpty()
        ) {
            return;
        }



        Community::query()
            ->each(function (Community $community) use (
                $residents,
                $managers,
                $categories
            ) {


                Issue::factory()
                    ->count(10)
                    ->create([

                        'community_id' => $community->id,

                        'category_id' => $categories->random()->id,

                        'reported_by' => $residents->random()->id,

                        'assigned_to' =>
                            $managers->isNotEmpty()
                                && fake()->boolean(60)
                                    ? $managers->random()->id
                                    : null,

                    ]);


            });

    }
}