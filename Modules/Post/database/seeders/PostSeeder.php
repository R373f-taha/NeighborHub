<?php

declare(strict_types=1);

namespace Modules\Post\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Community\app\Models\Community;
use Modules\Post\app\Models\Post;

class PostSeeder extends Seeder
{
    private const TARGET = 1000;

    private const PER_COMMUNITY = 20;

    public function run(): void
    {
        $existing = Post::count();
        $missing = max(0, self::TARGET - $existing);

        if ($missing === 0) {
            return;
        }

        Community::query()->each(function (Community $community) use (&$missing): void {
            if ($missing === 0) {
                return;
            }

            $residents = $community->residents()
                ->where('status', 'active')
                ->get();

            if ($residents->isEmpty()) {
                return;
            }

            $existingInCommunity = $community->posts()->count();

            $needed = min(
                max(0, self::PER_COMMUNITY - $existingInCommunity),
                $missing
            );

            if ($needed === 0) {
                return;
            }

            Post::factory()
                ->count($needed)
                ->create([
                    'community_id' => $community->id,
                    'resident_id' => $residents->random()->id,
                ]);

            $missing -= $needed;
        });
    }
}