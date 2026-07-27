<?php

declare(strict_types=1);

namespace Modules\Poll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Poll\app\Models\PollVote;

class PollVoteFactory extends Factory
{
    protected $model = PollVote::class;

    public function definition(): array
    {
        return [
            'submitted_at' => now(),
            'voted_at' => now(),
        ];
    }
}