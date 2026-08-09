<?php

declare(strict_types=1);

namespace Modules\Interaction\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Modules\Auth\app\Models\User;
use Modules\Interaction\app\Enums\ReactionType;
use Modules\Interaction\app\Models\Reaction;

/**
 * @extends Factory<Reaction>
 */
class ReactionFactory extends Factory
{
    protected $model = Reaction::class;

    public function definition(): array
    {
        return [
            'reactionable_type' => null,
            'reactionable_id' => null,
            'user_id' => User::query()->inRandomOrder()->value('id'),
            'type' => fake()->randomElement(ReactionType::cases()),
        ];
    }

    public function forReactionable(Model $model): static
    {
        return $this->state([
            'reactionable_type' => $model->getMorphClass(),
            'reactionable_id' => $model->getKey(),
        ]);
    }
}
