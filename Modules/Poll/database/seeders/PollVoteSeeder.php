<?php

declare(strict_types=1);

namespace Modules\Poll\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Community\app\Models\Resident;
use Modules\Poll\app\Models\Poll;
use Modules\Poll\app\Models\PollVote;

class PollVoteSeeder extends Seeder
{
    private const VOTES_PER_POLL = 20;

    public function run(): void
    {
        $residents = Resident::query()
            ->where('status', 'active')
            ->get();

        if ($residents->isEmpty()) {
            return;
        }

        Poll::query()
            ->whereIn('status', ['active', 'closed'])
            ->each(function (Poll $poll) use ($residents): void {

                $options = $poll->options;

                if ($options->isEmpty()) {
                    return;
                }

                $existingVoters = PollVote::query()
                    ->where('poll_id', $poll->id)
                    ->pluck('voter_id');

                $availableResidents = $residents
                    ->whereNotIn('id', $existingVoters);

                $count = min(
                    self::VOTES_PER_POLL,
                    $availableResidents->count()
                );

                foreach ($availableResidents->random($count) as $resident) {
                    PollVote::updateOrCreate(
                        [
                            'poll_id' => $poll->id,
                            'voter_id' => $resident->id,
                        ],
                        [
                            'option_id' => $options->random()->id,
                            'submitted_at' => now(),
                            'voted_at' => now(),
                        ]
                    );
                }
            });
    }
}