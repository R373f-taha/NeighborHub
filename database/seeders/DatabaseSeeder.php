<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([

            // 1 - Authentication
            \Modules\Auth\Database\Seeders\DatabaseSeeder::class,


            // 2 - Community
            \Modules\Community\Database\Seeders\DatabaseSeeder::class,


            // 3 - Posts
            \Modules\Post\Database\Seeders\DatabaseSeeder::class,


            // 4 - Service Listings
            \Modules\ServiceListing\Database\Seeders\DatabaseSeeder::class,


            // 5 - Polls
            \Modules\Poll\Database\Seeders\DatabaseSeeder::class,


            // 6 - Issues
            \Modules\Issue\Database\Seeders\DatabaseSeeder::class,


            // 7 - Interaction
            \Modules\Interaction\Database\Seeders\DatabaseSeeder::class,


            // 8 - Media
            \Modules\Media\Database\Seeders\DatabaseSeeder::class,


            // 9 - Messaging
            \Modules\Messaging\Database\Seeders\DatabaseSeeder::class,


            // 10 - Notifications
            \Modules\Notification\Database\Seeders\DatabaseSeeder::class,

        ]);
    }
}