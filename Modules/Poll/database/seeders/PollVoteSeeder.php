<?php

declare(strict_types=1);
 
namespace Modules\Poll\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Community\app\Models\Resident;
use Modules\Poll\app\Models\Poll;
use Modules\Poll\app\Models\PollVote;

class PollVoteSeeder extends Seeder
{
    public function run(): void
    {
        $residents = Resident::query()
            ->get();


        Poll::query()
            ->each(function ($poll) use ($residents) {


                $options = $poll->options;


                foreach ($residents->random(10) as $resident) {


                    PollVote::factory()
                        ->create([

                            'poll_id' => $poll->id,
                            'option_id' =>$options->random()->id,
                            'voter_id' =>$resident->id,

                        ]);

                }

            });
    }
}