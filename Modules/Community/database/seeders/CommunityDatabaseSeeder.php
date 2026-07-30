<?php

declare(strict_types=1);

namespace Modules\Community\Database\Seeders;

use Illuminate\Database\Seeder;

class CommunityDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([

            CommunitySeeder::class,

            UnitSeeder::class,

            CommunityManagerSeeder::class,

            ResidentSeeder::class,

            AnnouncementSeeder::class,

        ]);
    }
}