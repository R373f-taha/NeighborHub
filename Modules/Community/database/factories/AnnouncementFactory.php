<?php

declare(strict_types=1);

namespace Modules\Community\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\CommunityManager;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        $assignment = CommunityManager::query()
            ->inRandomOrder()
            ->first();

        if ($assignment === null) {
            throw new \RuntimeException(
                'AnnouncementFactory requires existing community manager assignments.'
            );
        }

        return [
            'community_id' => $assignment->community_id,
            'created_by_manager' => $assignment->manager_id,
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
        return $this->state([
            'priority' => 'urgent',
        ]);
    }

    public function important(): static
    {
        return $this->state([
            'priority' => 'important',
        ]);
    }

    public function pinned(): static
    {
        return $this->state([
            'pinned_until' => now()->addDays(7),
        ]);
    }
}