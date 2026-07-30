<?php

declare(strict_types=1);

namespace Modules\Community\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;

/**
 * @extends Factory<Resident>
 */
class ResidentFactory extends Factory
{use HasFactory;
    /**
     * @var class-string<Resident>
     */
    protected $model = Resident::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $joinedAt = fake()->dateTimeBetween('-2 years', '-1 month');

        return [

            'user_id' => User::factory()->resident(),

            'unit_id' => Unit::factory(),

            'residence_type' => fake()->randomElement([
                'owner',
                'tenant',
            ]),

            'status' => fake()->randomElement([
                'pending',
                'active',
                'suspended',
                'rejected',
            ]),

            'joined_at' => $joinedAt,

            'left_at' => fake()->boolean(10)
                ? fake()->dateTimeBetween($joinedAt, 'now')
                : null,

            'current_marker' => fake()->boolean(80),

            'approved_by' => User::query()
                ->where('role', 'manager')
                ->inRandomOrder()
                ->value('id'),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'current_marker' => true,
            'left_at' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
        ]);
    }

    public function owner(): static
    {
        return $this->state(fn () => [
            'residence_type' => 'owner',
        ]);
    }

    public function tenant(): static
    {
        return $this->state(fn () => [
            'residence_type' => 'tenant',
        ]);
    }
}
