<?php

declare(strict_types=1);

namespace Modules\Community\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Models\Community;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * @var class-string<Announcement>
     */
    protected $model = Announcement::class;


    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),

            'created_by_manager' => User::factory()->manager(),

            'title' => fake()->sentence(6),

            'content' => fake()->paragraphs(3, true),

            'priority' => fake()->randomElement([
                'normal',
                'important',
                'urgent',
            ]),

            'pinned_until' => fake()->boolean(30)
                ? fake()->dateTimeBetween('now', '+30 days')
                : null,
        ];
    }


    public function urgent(): static
    {
        return $this->state(fn () => [
            'priority' => 'urgent',
        ]);
    }


    public function important(): static
    {
        return $this->state(fn () => [
            'priority' => 'important',
        ]);
    }


    public function pinned(): static
    {
        return $this->state(fn () => [
            'pinned_until' => now()->addDays(7),
        ]);
    }
}