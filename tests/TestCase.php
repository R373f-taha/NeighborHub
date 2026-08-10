<?php

declare(strict_types=1);

namespace Tests;

use App\Http\Middleware\RequestLoggerMiddleware;
use App\Http\Middleware\Security\CorsMiddleware;
use App\Http\Middleware\Security\HeadersMiddleware;
use App\Http\Middleware\Security\RequestValidatorMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\DatabaseSafetyGuard;
use Tests\Support\TestDatabaseManager;

abstract class TestCase extends BaseTestCase
{
    /**
     * Global infrastructure/cross-cutting middleware excluded from the general
     * Feature test suite.
     *
     * These four middleware are production-only concerns (request logging,
     * payload/header validation, security headers, CORS) that interfere with
     * application feature tests:
     *  - RequestValidatorMiddleware throws on every non-GET request because it
     *    calls Request::getContentLength(), which does not exist on the
     *    framework Request in this Laravel version;
     *  - CorsMiddleware blocks requests carrying an Origin header that is not
     *    on the trusted list in non-local environments;
     *  - the logger/headers middleware add cross-cutting side effects.
     *
     * The exclusion is TEST-ONLY: withoutMiddleware([...]) binds an anonymous
     * passthrough in the container for each named class, so ONLY these four
     * become no-ops while auth:sanctum, EnsureUserIsActive, Spatie permission
     * middleware, policies, role middleware, community/ownership checks and all
     * route middleware continue to execute normally. Production registration in
     * bootstrap/app.php is left unchanged.
     */
    protected array $excludedInfrastructureMiddleware = [
        RequestLoggerMiddleware::class,
        RequestValidatorMiddleware::class,
        HeadersMiddleware::class,
        CorsMiddleware::class,
    ];

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

        // Exclude ONLY the four infrastructure middleware from feature tests.
        // This binds a passthrough per class in the container; it does NOT call
        // withoutMiddleware() with no argument, so the rest of the stack
        // (auth, authorization, active-user, Spatie, policies) still runs.
        $this->withoutMiddleware($this->excludedInfrastructureMiddleware);

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
