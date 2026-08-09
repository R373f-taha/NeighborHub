<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Enums\UserRole;
use Modules\Auth\app\Models\User;

class UserSeeder extends Seeder
{
    private const TARGET_TOTAL = 1000;

    private const TARGET_SUPER_ADMINS = 1;
    private const TARGET_MANAGERS = 100;
    private const TARGET_RESIDENTS = 849;
    private const TARGET_PROVIDERS = 50;

    public function run(): void
    {
        $this->createMissingUsers(
            UserRole::SuperAdmin,
            self::TARGET_SUPER_ADMINS,
            'superAdmin'
        );

        $this->createMissingUsers(
            UserRole::Manager,
            self::TARGET_MANAGERS,
            'manager'
        );

        $this->createMissingUsers(
            UserRole::Resident,
            self::TARGET_RESIDENTS,
            'resident'
        );

        $this->createMissingUsers(
            UserRole::Provider,
            self::TARGET_PROVIDERS,
            'provider'
        );
    }

    private function createMissingUsers(
        UserRole $role,
        int $target,
        string $factoryState
    ): void {
        $existing = User::where('role', $role)->count();
        $missing = max(0, $target - $existing);

        if ($missing === 0) {
            return;
        }

        User::factory()
            ->{$factoryState}()
            ->count($missing)
            ->create();
    }
}