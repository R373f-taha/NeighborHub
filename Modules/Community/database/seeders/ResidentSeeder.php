<?php

declare(strict_types=1);

namespace Modules\Community\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;

class ResidentSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->where('role', 'resident')
            ->doesntHave('resident')
            ->get();

        $units = Unit::query()
            ->where('is_active', true)
            ->get();

        $managers = User::query()
            ->where('role', 'manager')
            ->pluck('id');

        foreach ($users as $user) {
            if ($units->isEmpty()) {
                break;
            }

            $unit = $units->pop();

            Resident::factory()
                ->active()
                ->create([
                    'user_id' => $user->id,
                    'unit_id' => $unit->id,
                    'community_id' => $unit->community_id,
                    'approved_by' => $managers->isNotEmpty() ? $managers->random() : null,
                ]);
        }
    }
}