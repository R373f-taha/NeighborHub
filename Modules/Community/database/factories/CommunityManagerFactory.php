<?php

declare(strict_types=1);

namespace Modules\Community\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\CommunityManager;

/**
 * @extends Factory<CommunityManager>
 */
class CommunityManagerFactory extends Factory
{
    protected $model = CommunityManager::class;

    public function definition(): array
    {
        return [
            'community_id' => Community::query()
                ->inRandomOrder()
                ->value('id'),

            'manager_id' => User::query()
                ->where('role', 'manager')
                ->inRandomOrder()
                ->value('id'),
        ];
    }

    public function forCommunity(Community $community): static
    {
        return $this->state([
            'community_id' => $community->id,
        ]);
    }

    public function forManager(User $manager): static
    {
        return $this->state([
            'manager_id' => $manager->id,
        ]);
    }
}