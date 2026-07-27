<?php

declare(strict_types=1);

namespace Modules\ServiceListing\Database\Seeders;

use Illuminate\Database\Seeder;


class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ServiceListingSeeder::class,
        ]);
    }
}