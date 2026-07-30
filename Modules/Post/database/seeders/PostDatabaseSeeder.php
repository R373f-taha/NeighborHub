<?php

declare(strict_types=1);

namespace Modules\Post\Database\Seeders;

use Illuminate\Database\Seeder;


class PostDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PostSeeder::class,
        ]);
    }
}