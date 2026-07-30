<?php

declare(strict_types=1);

namespace Modules\Community\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Community\app\Models\Community;

class CommunitySeeder extends Seeder
{
    public function run(): void
    {
        Community::factory()
            ->count(10)
            ->create();
    }
}