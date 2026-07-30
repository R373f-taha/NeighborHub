<?php

declare(strict_types=1);

namespace Modules\Poll\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Poll\app\Models\Poll;

class PollSeeder extends Seeder
{
    public function run(): void
    {
        $managers = User::query()
            ->where('role','manager')
            ->get();


        Community::query()
            ->each(function ($community) use ($managers) {


                Poll::factory()
                    ->count(5)
                    ->create([

                        'community_id' => $community->id,

                        'created_by' => $managers->random()->id,

                        'colsed_by_manager' =>
                            $managers->random()->id,

                    ]);

            });
    }
}