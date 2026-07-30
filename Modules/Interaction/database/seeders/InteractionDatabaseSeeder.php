<?php

declare(strict_types=1);

namespace Modules\Interaction\Database\Seeders;

use Illuminate\Database\Seeder;

class InteractionDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([

            CommentSeeder::class,

            ReactionSeeder::class,

        ]);
    }
}