<?php

declare(strict_types=1);

namespace Modules\Interaction\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\app\Models\User;
use Modules\Interaction\app\Enums\ReactionType;
use Modules\Interaction\app\Models\Reaction;
use Modules\Post\app\Models\Post;

/**
 * @extends Factory<Reaction>
 */
class ReactionFactory extends Factory
{
    protected $model = Reaction::class;

    public function definition(): array
    {
        return [
            'reactionable_type' => (new Post)->getMorphClass(),
            'reactionable_id' => Post::factory(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(ReactionType::cases()),
        ];
    }
}