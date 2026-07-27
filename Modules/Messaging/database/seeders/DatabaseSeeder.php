<?php

declare(strict_types=1);

namespace Modules\Messaging\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([

            ConversationSeeder::class,

            ConversationParticipantSeeder::class,

            MessageSeeder::class,

        ]);
    }
}