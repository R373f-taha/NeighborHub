<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Enums\UserRole;
use Modules\Auth\app\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        $admin = User::updateOrCreate(
            ['email' => 'admin@neighborhub.test'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'role' => UserRole::SuperAdmin,
                'is_active' => true,
            ]
        );

        $admin->assignRole(UserRole::SuperAdmin->value);

        // Development Postman Account
        $postman = User::updateOrCreate(
            ['email' => 'postman@neighborhub.local'],
            [
                'name' => 'Postman Resident',
                'password' => bcrypt('password'),
                'role' => UserRole::Resident,
                'is_active' => true,
            ]
        );

        $postman->assignRole(UserRole::Resident->value);

        // Managers
        $existingManagers = User::where('role', UserRole::Manager)->count();
        $missingManagers = max(0, 5 - $existingManagers);

        if ($missingManagers > 0) {
            User::factory()
                ->manager()
                ->count($missingManagers)
                ->create();
        }

        // Residents
        $existingResidents = User::where('role', UserRole::Resident)
            ->where('email', '!=', 'postman@neighborhub.local')
            ->count();

        $missingResidents = max(0, 30 - $existingResidents);

        if ($missingResidents > 0) {
            User::factory()
                ->resident()
                ->count($missingResidents)
                ->create();
        }

        // Providers
        $existingProviders = User::where('role', UserRole::Provider)->count();
        $missingProviders = max(0, 10 - $existingProviders);

        if ($missingProviders > 0) {
            User::factory()
                ->provider()
                ->count($missingProviders)
                ->create();
        }
    }
}