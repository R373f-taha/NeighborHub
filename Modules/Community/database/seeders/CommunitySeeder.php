<?php

declare(strict_types=1);

namespace Modules\Community\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Community\app\Models\Community;

class CommunitySeeder extends Seeder
{
    private const TARGET = 50;

    public function run(): void
    {
        $existing = Community::count();
        $missing = max(0, self::TARGET - $existing);

        if ($missing === 0) {
            return;
        }

        Community::factory()
            ->count($missing)
            ->create();
    }
}