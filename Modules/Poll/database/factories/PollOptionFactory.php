<?php

declare(strict_types=1);

namespace Modules\Poll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Poll\app\Models\Poll;
use Modules\Poll\app\Models\PollOption;

/**
 * @extends Factory<PollOption>
 */
class PollOptionFactory extends Factory
{
    protected $model = PollOption::class;

    public function definition(): array
    {
        return [
            'poll_id' => Poll::factory(),
            'text' => fake()->randomElement([
                'Yes',
                'No',
                'Maybe',
                'Option A',
                'Option B',
            ]),
        ];
    }
}