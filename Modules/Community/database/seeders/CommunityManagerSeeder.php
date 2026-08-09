<?php

declare(strict_types=1);

namespace Modules\Community\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\CommunityManager;

class CommunityManagerSeeder extends Seeder
{
    private const MANAGERS_PER_COMMUNITY = 2;

    public function run(): void
    {
        $managers = User::query()
            ->where('role', 'manager')
            ->get();

        if ($managers->isEmpty()) {
            return;
        }

        Community::query()->each(function (Community $community) use ($managers): void {
            $existing = CommunityManager::query()
                ->where('community_id', $community->id)
                ->pluck('manager_id');

            $missing = max(
                0,
                self::MANAGERS_PER_COMMUNITY - $existing->count()
            );

            if ($missing === 0) {
                return;
            }

            $managers
                ->whereNotIn('id', $existing)
                ->shuffle()
                ->take($missing)
                ->each(function (User $manager) use ($community): void {
                    CommunityManager::create([
                        'community_id' => $community->id,
                        'manager_id' => $manager->id,
                    ]);
                });
        });
    }
}