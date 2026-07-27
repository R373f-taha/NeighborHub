<?php

declare(strict_types=1);

namespace Modules\Interaction\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\app\Models\User;
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


            'user_id' => User::factory(),


            'type' => fake()->randomElement([
                'like',
                'love',
                'support',
                'helpful',
                'celebrate',
            ]),
        ];
    }
}