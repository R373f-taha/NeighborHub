<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\DatabaseSafetyGuard;
use Tests\Support\TestDatabaseManager;

abstract class TestCase extends BaseTestCase
{
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        // Provision the approved disposable database before any test trait
        // (e.g. RefreshDatabase) runs. The schema is migrated here, so the
        // suite never depends on a manually pre-created database.
        TestDatabaseManager::provision();

        // RefreshDatabase would otherwise run migrate:fresh in-process; the
        // manager has already migrated, so wrap each test in a transaction.
        RefreshDatabaseState::$migrated = true;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Layer 2: the booted application must resolve the disposable test
        // database before any test logic or destructive helper can run.
        DatabaseSafetyGuard::assertBootedApplicationSafe($this->app);

        // Ensure the official Spatie roles exist for the application's default
        // guard before any test creates a user. UserFactory::configure() calls
        // assignRole() on every created user, which throws RoleDoesNotExist when
        // the role has not been seeded. The official RolePermissionSeeder cannot
        // be reused here: it throws PermissionDoesNotExist because it syncs
        // web-guard permissions onto an api-guard super_admin role (pre-existing
        // production defect). Roles only are seeded, per-test inside the
        // RefreshDatabase transaction, keeping the suite self-contained so a
        // developer can run `php artisan test <file>` with no manual seeding.
        $this->seedApplicationRoles();
    }

    /**
     * Seed the four official roles (matching Modules\Auth\Enums\UserRole) for
     * the configured default guard. Idempotent; safe under transaction rollback.
     */
    protected function seedApplicationRoles(): void
    {
        if (! class_exists(Role::class)) {
            return;
        }

        $guard = (string) config('auth.defaults.guard', 'api');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['super_admin', 'manager', 'resident', 'provider'] as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
