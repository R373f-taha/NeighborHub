<?php

declare(strict_types=1);

namespace Modules\Community\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Community\app\Models\Community;

class CommunitySeeder extends Seeder
{
    public function run(): void
    {
        $existing = Community::count();
        $missing = max(0, 10 - $existing);

        if ($missing > 0) {
            Community::factory()
                ->count($missing)
                ->create();
        }
    }
}