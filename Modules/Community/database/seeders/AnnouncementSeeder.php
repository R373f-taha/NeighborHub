<?php

declare(strict_types=1);

namespace Modules\Community\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Models\Community;

class AnnouncementSeeder extends Seeder
{
    private const TARGET = 500;

    private const PER_COMMUNITY = 10;

    public function run(): void
    {
        $existing = Announcement::count();
        $missing = max(0, self::TARGET - $existing);

        if ($missing === 0) {
            return;
        }

        $communities = Community::query()
            ->with('managers')
            ->get();

        if ($communities->isEmpty()) {
            return;
        }

        foreach ($communities as $community) {
            if ($missing <= 0) {
                break;
            }

            $managers = $community->managers;

            if ($managers->isEmpty()) {
                continue;
            }

            $existingInCommunity = Announcement::query()
                ->where('community_id', $community->id)
                ->count();

            $needed = min(
                self::PER_COMMUNITY - $existingInCommunity,
                $missing
            );

            if ($needed <= 0) {
                continue;
            }

            Announcement::factory()
                ->count($needed)
                ->create([
                    'community_id' => $community->id,
                    'created_by_manager' => $managers->random()->id,
                ]);

            $missing -= $needed;
        }
    }
}