<?php

namespace Modules\Issue\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Issue\app\Models\IssueCategory;

class IssueCategoryFactory extends Factory
{
    protected $model = IssueCategory::class;


    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Plumbing',
                'Electricity',
                'Elevator',
                'Cleaning',
                'Security',
                'Maintenance',
            ]),

            'is_active' => true,
        ];
    }
}