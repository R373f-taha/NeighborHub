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
    /**
     * @var class-string<CommunityManager>
     */
    protected $model = CommunityManager::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),

            'manager_id' => User::factory()->manager(),
        ];
    }
}