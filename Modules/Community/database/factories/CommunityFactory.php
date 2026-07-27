<?php

declare(strict_types=1);

namespace Modules\Community\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Community\app\Models\Community;

/**
 * @extends Factory<Community>
 */
class CommunityFactory extends Factory
{
    /**
     * @var class-string<Community>
     */
    protected $model = Community::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Community',

            'city' => fake()->city(),

            'address' => fake()->address(),

            'total_units' => fake()->numberBetween(20, 150),

            'is_active' => fake()->boolean(90),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}