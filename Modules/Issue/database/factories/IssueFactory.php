<?php

declare(strict_types=1);

namespace Modules\Issue\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
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

            'community_id' => Community::factory(),

            'title' => fake()->sentence(5),

            'description' => fake()->paragraph(3),

            'category' => fake()->randomElement([
                'maintenance',
                'security',
                'cleaning',
                'noise',
                'parking',
                'other',
            ]),

            'location' => fake()->randomElement([
                'Building A',
                'Building B',
                'Entrance',
                'Parking',
                'Garden',
            ]),

            'priority' => fake()->randomElement([
                'low',
                'medium',
                'high',
                'urgent',
            ]),

            'status' => fake()->randomElement([
                'open',
                'assigned',
                'in_progress',
                'resolved',
                'closed',
            ]),

            'reported_by' => User::factory()->resident(),

            'assigned_to' => null,
        ];
    }


    public function open(): static
    {
        return $this->state(fn () => [
            'status' => 'open',
        ]);
    }


    public function urgent(): static
    {
        return $this->state(fn () => [
            'priority' => 'urgent',
        ]);
    }


    public function assigned(): static
    {
        return $this->state(fn () => [
            'status' => 'assigned',
            'assigned_to' => User::factory()->manager(),
        ]);
    }
}