<?php

declare(strict_types=1);

namespace Modules\Poll\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Poll\app\Models\Poll;
use Modules\Poll\app\Models\PollOption;

class PollOptionSeeder extends Seeder
{
    public function run(): void
    {
        Poll::query()
            ->each(function ($poll) {


                PollOption::factory()
                    ->count(4)
                    ->create([

                        'poll_id' => $poll->id,

                    ]);

            });
    }
}