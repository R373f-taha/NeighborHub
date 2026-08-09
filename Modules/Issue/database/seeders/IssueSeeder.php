<?php

declare(strict_types=1);

namespace Modules\Issue\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Issue\app\Enums\IssueStatus;
use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Models\IssueCategory;

class IssueSeeder extends Seeder
{
    private const TARGET = 1000;

    public function run(): void
    {
        $missing = max(0, self::TARGET - Issue::count());

        if ($missing === 0) {
            return;
        }

        $providers = User::query()
            ->where('role', 'provider')
            ->get();

        $categories = IssueCategory::query()
            ->where('is_active', true)
            ->get();

        if ($categories->isEmpty()) {
            return;
        }

        Community::query()->each(function (Community $community) use (
            $providers,
            $categories,
            &$missing
        ): void {
            if ($missing <= 0) {
                return;
            }

            $residents = $community->residents()
                ->where('status', 'active')
                ->get();

            if ($residents->isEmpty()) {
                return;
            }

            $needed = min(
                20,
                $missing
            );

            for ($i = 0; $i < $needed; $i++) {
                $status = fake()->randomElement(IssueStatus::cases());

                $assignedTo = null;

                if (
                    $status !== IssueStatus::OPEN &&
                    $providers->isNotEmpty()
                ) {
                    $assignedTo = $providers->random()->id;
                }

                Issue::factory()->create([
                    'community_id' => $community->id,
                    'category_id' => $categories->random()->id,
                    'reported_by' => $residents->random()->user_id,
                    'status' => $status,
                    'assigned_to' => $assignedTo,
                ]);

                $missing--;
            }
        });
    }
}
