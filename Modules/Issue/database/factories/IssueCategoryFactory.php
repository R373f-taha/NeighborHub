<?php

declare(strict_types=1);

namespace Modules\Issue\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Issue\app\Models\IssueCategory;

/**
 * @extends Factory<IssueCategory>
 */
class IssueCategoryFactory extends Factory
{
    protected $model = IssueCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Plumbing',
                'Electricity',
                'Elevator',
                'Cleaning',
                'Security',
                'Other',
            ]),
            'is_active' => true,
        ];
    }
}