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
        $joinedAt = fake()->dateTimeBetween('-2 years', '-1 month');

        return [
            'user_id' => User::factory()->resident(),
            'unit_id' => Unit::factory()->for(Community::factory()),
            'residence_type' => fake()->randomElement(['owner', 'tenant']),
            'status' => fake()->randomElement(['pending', 'active', 'suspended', 'rejected']),
            'joined_at' => $joinedAt,
            'left_at' => fake()->boolean(10)
                ? fake()->dateTimeBetween($joinedAt, 'now')
                : null,
            'current_marker' => false,
            'approved_by' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Resident $resident) {
            if ($resident->unit_id && ! $resident->community_id) {
                $communityId = Unit::where('id', $resident->unit_id)->value('community_id');
                if ($communityId !== null) {
                    $resident->community_id = $communityId;
                }
            }
        });
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
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
