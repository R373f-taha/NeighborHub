<?php

declare(strict_types=1);

namespace Modules\Post\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Community\app\Models\Community;
use Modules\Post\app\Models\Post;

class PostSeeder extends Seeder
{
    private const TARGET_PER_COMMUNITY = 10;

    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        Community::query()->each(function (Community $community): void {
            $residents = $community->residents()
                ->where('status', 'active')
                ->get(['residents.id']);

            if ($residents->isEmpty()) {
                return;
            }

            $missing = max(0, self::TARGET_PER_COMMUNITY - $community->posts()->count());

            for ($i = 0; $i < $missing; $i++) {
                Post::factory()
                    ->forCommunity($community)
                    ->forResident($residents->random())
                    ->create();
            }
        });
    }
}
