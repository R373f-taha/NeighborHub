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
                
                $existingCount = $community->units()->count();
                $missing = max(0, $community->total_units - $existingCount);

                for ($i = 1; $i <= $missing; $i++) {
                    $unitNumber = 'A-' . str_pad((string)($existingCount + $i), 4, '0', STR_PAD_LEFT);
                    
                    Unit::factory()->create([
                        'community_id' => $community->id,
                        'unit_number' => $unitNumber,
                    ]);
                }

            });
    }
}