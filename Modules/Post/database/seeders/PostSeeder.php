<?php

declare(strict_types=1);

namespace Modules\Post\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Community\app\Models\Community;
use Modules\Post\app\Models\Post;


class PostSeeder extends Seeder
{
    public function run(): void
    {

        Community::with('residents')
            ->get()
            ->each(function ($community) {


                if ($community->residents->isEmpty()) {
                    return;
                }


                Post::factory()
                    ->count(10)
                    ->create([

                        'community_id' => $community->id,

                        'resident_id' =>
                            $community
                                ->residents
                                ->random()
                                ->id,

                    ]);

            });

    }
}