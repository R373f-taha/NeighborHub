<?php

declare(strict_types=1);

namespace Modules\Issue\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([

            IssueSeeder::class,

            IssueStatusLogSeeder::class,

        ]);
    }
}