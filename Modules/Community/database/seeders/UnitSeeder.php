<?php

declare(strict_types=1);

namespace Modules\Community\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Unit;

class UnitSeeder extends Seeder
{
    private const TARGET = 1500;

    public function run(): void
    {
        $existing = Unit::count();
        $missing = max(0, self::TARGET - $existing);

        if ($missing === 0) {
            return;
        }

        $communities = Community::query()->get();

        if ($communities->isEmpty()) {
            return;
        }

        $counts = Unit::query()
            ->selectRaw('community_id, COUNT(*) as total')
            ->groupBy('community_id')
            ->pluck('total', 'community_id');

        foreach (range(1, $missing) as $i) {
            $community = $communities[($i - 1) % $communities->count()];

            $number = ($counts[$community->id] ?? 0) + 1;
            $counts[$community->id] = $number;

            Unit::factory()->create([
                'community_id' => $community->id,
                'unit_number' => 'A-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            ]);
        }

        foreach ($communities as $community) {
            $community->update([
                'total_units' => $counts[$community->id] ?? 0,
            ]);
        }
    }
}