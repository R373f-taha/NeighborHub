<?php

declare(strict_types=1);

namespace Modules\Poll\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Poll\app\Models\Poll;

class PollSeeder extends Seeder
{
    private const PER_COMMUNITY = 5;

    public function run(): void
    {
        $managers = User::query()
            ->where('role', 'manager')
            ->pluck('id');

        if ($managers->isEmpty()) {
            return;
        }

        Community::query()->each(function (Community $community) use ($managers): void {
            Poll::factory()
                ->count(self::PER_COMMUNITY)
                ->create([
                    'community_id' => $community->id,
                    'created_by' => $managers->random(),
                ]);
        });
    }
}