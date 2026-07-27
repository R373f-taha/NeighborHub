<?php

declare(strict_types=1);

namespace Modules\Poll\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([

            PollSeeder::class,

            PollOptionSeeder::class,

            PollVoteSeeder::class,

        ]);
    }
}