<?php

declare(strict_types=1);

namespace Modules\Poll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Poll\app\Models\Poll;

class PollFactory extends Factory
{
    protected $model = Poll::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'type' => 'single_choice',
            'status' => fake()->randomElement([
                'draft',
                'active',
                'closed',
            ]),
            'ends_at' => now()->addDays(fake()->numberBetween(3, 15)),
            'activated_at' => now(),
            'closed_at' => null,
        ];
    }
}