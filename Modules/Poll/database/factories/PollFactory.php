<?php

declare(strict_types=1);

namespace Modules\Poll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Poll\app\Models\Poll;

/**
 * @extends Factory<Poll>
 */
class PollFactory extends Factory
{
    protected $model = Poll::class;


    public function definition(): array
    {
        return [

            'community_id' => Community::factory(),

            'created_by' => User::factory(),

            'title' => fake()->sentence(5),

            'description' => fake()->paragraph(),

            'type' => fake()->randomElement([
                'single_choice',

            ]),


            'status' => fake()->randomElement([
                'draft',
                'active',
                'closed',
            ]),


            'ends_at' => now()->addDays(
                fake()->numberBetween(3,15)
            ),


            'activated_at' => now(),

            'closed_at' => null,


        //    'colsed_by_manager' => User::factory(),

        ];
    }
}
