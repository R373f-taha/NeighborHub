<?php

declare(strict_types=1);

namespace Modules\Community\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;

/**
 * @extends Factory<Resident>
 */
class ResidentFactory extends Factory
{
    protected $model = Resident::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->resident(),
            'unit_id' => Unit::factory(),
            'residence_type' => fake()->randomElement(['owner', 'tenant']),
            'status' => 'pending',
            'joined_at' => fake()->dateTimeBetween('-2 years', '-1 month'),
            'left_at' => null,
            'current_marker' => false,
            'approved_by' => null,
            'community_id' => Community::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Resident $resident): void {
            if ($resident->unit_id && ! $resident->community_id) {
                $resident->community_id = Unit::query()
                    ->whereKey($resident->unit_id)
                    ->value('community_id');
            }
        });
    }

    public function active(): static
    {
        return $this->state([
            'status' => 'active',
            'left_at' => null,
            'current_marker' => true,
        ]);
    }

    public function pending(): static
    {
        return $this->state([
            'status' => 'pending',
            'current_marker' => false,
        ]);
    }

    public function owner(): static
    {
        return $this->state([
            'residence_type' => 'owner',
        ]);
    }

    public function tenant(): static
    {
        return $this->state([
            'residence_type' => 'tenant',
        ]);
    }
}