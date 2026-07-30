<?php

declare(strict_types=1);

namespace Modules\Community\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Unit;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        Community::query()
            ->each(function (Community $community) {

                Unit::factory()
                    ->count($community->total_units)
                    ->create([
                        'community_id' => $community->id,
                    ]);

            });
    }
}