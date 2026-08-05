<?php

namespace Modules\Issue\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Models\IssueCategory;
use Modules\Issue\app\Enums\IssueStatus;
use Modules\Issue\app\Enums\IssuePriority;
use Modules\Community\app\Models\Community;
use Modules\Auth\app\Models\User;
class IssueFactory extends Factory
{
    protected $model = Issue::class;

    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),

            'category_id' => IssueCategory::factory(),

            'title' => fake()->sentence(),

            'description' => fake()->paragraph(),

            'location' => fake()->address(),

            'priority' => fake()->randomElement(
                IssuePriority::cases()
            ),

            'status' => IssueStatus::OPEN,

            'reported_by' => User::factory(),

            'assigned_to' => null,
        ];
    }
}