<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        User::factory()->superAdmin()->create([
            'name' => 'Super Admin',
            'email' => 'admin@neighborhub.test',
        ]);

        // Managers
        User::factory()
            ->manager()
            ->count(5)
            ->create();

        // Residents
        User::factory()
            ->resident()
            ->count(30)
            ->create();

        // Service Providers
        User::factory()
            ->provider()
            ->count(10)
            ->create();
    }
}