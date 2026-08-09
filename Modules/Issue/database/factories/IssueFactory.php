<?php

declare(strict_types=1);

namespace Modules\Issue\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Issue\app\Enums\IssuePriority;
use Modules\Issue\app\Enums\IssueStatus;
use Modules\Issue\app\Models\Issue;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    protected $model = Issue::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'location' => fake()->address(),
            'priority' => fake()->randomElement(IssuePriority::cases()),
            'status' => IssueStatus::OPEN,
            'assigned_to' => null,
        ];
    }
}
