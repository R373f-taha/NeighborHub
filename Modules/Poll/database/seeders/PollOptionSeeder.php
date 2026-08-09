<?php

declare(strict_types=1);

namespace Modules\Poll\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Poll\app\Models\Poll;
use Modules\Poll\app\Models\PollOption;

class PollOptionSeeder extends Seeder
{
    private const OPTIONS_PER_POLL = 4;

    public function run(): void
    {
        Poll::query()->each(function (Poll $poll): void {
            $existingCount = $poll->options()->count();

            $missing = max(
                0,
                self::OPTIONS_PER_POLL - $existingCount
            );

            if ($missing > 0) {
                PollOption::factory()
                    ->count($missing)
                    ->create([
                        'poll_id' => $poll->id,
                    ]);
            }
        });
    }
}