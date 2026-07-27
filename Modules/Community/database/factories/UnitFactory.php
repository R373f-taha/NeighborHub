<?php

declare(strict_types=1);

namespace Modules\Community\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Community\app\Models\Unit;
use Modules\Community\app\Models\Community;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * @var class-string<Unit>
     */
    protected $model = Unit::class;


    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [

            'unit_number' => 'A-' . fake()->unique()->numberBetween(100, 9999),

            'building_name' => fake()->optional()->streetName(),

            'rooms' => fake()->numberBetween(1, 6),

            'unit_type' => fake()->randomElement([
                'apartment',
                'villa',
            ]),

            'is_active' => true,

        ];
    }


    public function apartment(): static
    {
        return $this->state(fn () => [
            'unit_type' => 'apartment',
        ]);
    }


    public function villa(): static
    {
        return $this->state(fn () => [
            'unit_type' => 'villa',
        ]);
    }


    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}