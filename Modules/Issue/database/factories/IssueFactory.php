<?php

declare(strict_types=1);

namespace Modules\Issue\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Issue\app\Enums\IssuePriority;
use Modules\Issue\app\Enums\IssueStatus;
use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Models\IssueCategory;

class IssueFactory extends Factory
{
    protected $model = Issue::class;

    public function definition(): array
    {
        $status = fake()->randomElement(
            IssueStatus::cases()
        );

        return [
            'community_id' => Community::factory(),

            'category_id' => IssueCategory::factory(),

            'title' => fake()->sentence(),

            'description' => fake()->paragraph(),

            'location' => fake()->address(),

            'priority' => fake()->randomElement(
                IssuePriority::cases()
            ),

            'status' => $status,

            'reported_by' => User::factory(),

            'assigned_to' => null,
        ];
    }
}