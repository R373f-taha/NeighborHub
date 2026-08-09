<?php

declare(strict_types=1);

namespace Modules\Community\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;

class ResidentSeeder extends Seeder
{
    private const TARGET = 849;

    public function run(): void
    {
        $missing = max(0, self::TARGET - Resident::count());

        if ($missing === 0) {
            return;
        }

        $users = User::query()
            ->where('role', 'resident')
            ->doesntHave('resident')
            ->limit($missing)
            ->get();

        $units = Unit::query()
            ->where('is_active', true)
            ->get();

        if ($users->isEmpty() || $units->isEmpty()) {
            return;
        }

        foreach ($users as $index => $user) {
            $unit = $units[$index % $units->count()];

            Resident::factory()
                ->active()
                ->create([
                    'user_id' => $user->id,
                    'unit_id' => $unit->id,
                    'community_id' => $unit->community_id,
                    'approved_by' => null,
                ]);
        }
    }
}