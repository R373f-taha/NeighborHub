<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Auth\app\Models\User;
use Modules\Auth\Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Provisions the official RBAC contract (roles, permissions and their
 * assignments for the api guard) for the disposable test database, exactly
 * once per database lifecycle.
 *
 * Invoked from {@see TestDatabaseManager::rebuild()}, i.e. every time the test
 * database is dropped/recreated/migrated, and always BEFORE any per-test
 * transaction begins. The role/permission definitions are derived from the
 * project's official {@see RolePermissionSeeder} — the single source of truth —
 * so the contract under test is never duplicated or allowed to drift.
 *
 * Only IMMUTABLE RBAC definitions are shared across tests. The four demo users
 * the seeder also creates are removed immediately, together with their role and
 * permission bindings, so no mutable user state ever leaks between tests; each
 * test creates its own users via factories and assigns the already-provisioned
 * roles.
 *
 * Centralising the contract here — instead of re-running RolePermissionSeeder
 * inside every test's RefreshDatabase transaction — eliminates the historical
 * MySQL deadlock (SQLSTATE 40001 / vendor code 1213). That deadlock was caused
 * by Spatie's syncPermissions taking an exclusive lock on role_has_permissions
 * and then, after the permission cache was forgotten mid-transaction, reloading
 * the permission set with LOCK IN SHARE MODE inside the SAME transaction: an
 * intra-transaction lock-upgrade that, once it rolled a transaction back, could
 * cascade into hundreds of secondary "Unknown database" failures on slower or
 * more contended machines. Provisioning outside the transaction removes the
 * root cause while keeping the exact same authorization contract.
 */
final class RbacProvisioner
{
    /**
     * Demo users created by RolePermissionSeeder. Removed after seeding so
     * only immutable RBAC definitions are shared between tests.
     */
    private const DEMO_USER_EMAILS = [
        'superadmin@neighborhub.com',
        'manager@neighborhub.com',
        'resident@neighborhub.com',
        'provider@neighborhub.com',
    ];

    /**
     * Publish the official role/permission contract and strip the mutable demo
     * users. Idempotent: the seeder relies on firstOrCreate/updateOrCreate, so
     * it is safe to run on every rebuild.
     */
    public static function provision(): void
    {
        // Single source of truth: the official seeder publishes the exact
        // role/permission/guard contract the application's route middleware and
        // policies rely on.
        Artisan::call('db:seed', [
            '--class' => RolePermissionSeeder::class,
            '--force' => true,
        ]);

        self::removeDemoUsers();

        // The in-memory permission cache was populated by the seeder's own
        // syncPermissions calls; drop it so the first test lazily reloads the
        // now-stable, committed contract.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Delete the seeder's demo users and their Spatie bindings. Only immutable
     * RBAC definitions may be shared across tests.
     */
    private static function removeDemoUsers(): void
    {
        $demoIds = DB::table('users')
            ->whereIn('email', self::DEMO_USER_EMAILS)
            ->pluck('id');

        if ($demoIds->isEmpty()) {
            return;
        }

        // model_has_roles / model_has_permissions are plain columns without a
        // foreign key, so remove the bindings before deleting the users to keep
        // no dangling references. Scoped by User's morph type when one exists.
        $morphType = self::userMorphType();

        DB::table('model_has_roles')
            ->whereIn('model_id', $demoIds)
            ->when($morphType !== null, static fn ($q) => $q->where('model_type', $morphType))
            ->delete();

        DB::table('model_has_permissions')
            ->whereIn('model_id', $demoIds)
            ->when($morphType !== null, static fn ($q) => $q->where('model_type', $morphType))
            ->delete();

        DB::table('users')->whereIn('id', $demoIds)->delete();
    }

    private static function userMorphType(): ?string
    {
        return class_exists(User::class) ? (new User())->getMorphClass() : null;
    }
}
