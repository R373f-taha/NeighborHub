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
        User::updateOrCreate(
            ['email' => 'admin@neighborhub.test'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        // Development Postman Account
        User::updateOrCreate(
            ['email' => 'postman@neighborhub.local'],
            [
                'name' => 'Postman Resident',
                'password' => bcrypt('password'),
                'role' => 'resident',
                'is_active' => true,
            ]
        );

        // Managers
        $existingManagers = User::where('role', 'manager')->count();
        $missingManagers = max(0, 5 - $existingManagers);
        if ($missingManagers > 0) {
            User::factory()->manager()->count($missingManagers)->create();
        }

        // Residents
        $existingResidents = User::where('role', 'resident')->where('email', '!=', 'postman@neighborhub.local')->count();
        $missingResidents = max(0, 30 - $existingResidents);
        if ($missingResidents > 0) {
            User::factory()->resident()->count($missingResidents)->create();
        }

        // Service Providers
        $existingProviders = User::where('role', 'service_provider')->count();
        $missingProviders = max(0, 10 - $existingProviders);
        if ($missingProviders > 0) {
            User::factory()->provider()->count($missingProviders)->create();
        }
    }
}