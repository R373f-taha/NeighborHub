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
    public function run(): void
    {
        $residents = User::role('resident')->get();

        $providers = User::role('provider')->get();

        $categories = IssueCategory::query()
            ->where('is_active', true)
            ->get();

        if (
            $residents->isEmpty() ||
            $categories->isEmpty()
        ) {
            return;
        }

        Community::query()->each(
            function (Community $community) use (
                $residents,
                $providers,
                $categories
            ) {
                for ($i = 0; $i < 10; $i++) {

                    $status = fake()->randomElement(
                        IssueStatus::cases()
                    );

                    $assignedTo = null;

                    /*
                     * Issues with these statuses
                     * must have a provider assigned.
                     */
                    if (
                        in_array($status, [
                            IssueStatus::ASSIGNED,
                            IssueStatus::IN_PROGRESS,
                            IssueStatus::RESOLVED,
                            IssueStatus::CLOSED,
                        ], true)
                        && $providers->isNotEmpty()
                    ) {
                        $assignedTo = $providers->random()->id;
                    }

                    Issue::factory()->create([
                        'community_id' => $community->id,

                        'category_id' => $categories->random()->id,

                        'reported_by' => $residents->random()->id,

                        'status' => $status,

                        'assigned_to' => $assignedTo,
                    ]);
                }
            }
        );
    }
}