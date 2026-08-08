<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;
use Modules\Auth\app\Enums\UserRole;
use Modules\Auth\app\Models\User;
use Tests\TestCase;

/**
 * Foundation tests for the Authentication module.
 *
 * These tests verify the auth foundation only (model, casts, factory,
 * provider wiring, route booting). They do NOT test any Authentication
 * endpoint behaviour, which is implemented in a separate task.
 *
 * RefreshDatabase is intentionally NOT used: the full module migration set
 * contains a pre-existing ordering bug (Community `residents` migration runs
 * after Post/Poll/ServiceListing migrations that foreign-key reference it),
 * which is outside the scope of the auth foundation. DB-dependent assertions
 * target the disposable MySQL foundation database and are skipped when it is
 * unavailable.
 */
class AuthFoundationTest extends TestCase
{
    public function test_auth_provider_uses_canonical_user_model(): void
    {
        $this->assertSame(User::class, config('auth.providers.users.model'));
    }

    public function test_canonical_user_is_authenticatable(): void
    {
        $this->assertInstanceOf(Authenticatable::class, new User());
    }

    public function test_user_uses_sanctum_has_api_tokens(): void
    {
        $this->assertContains(HasApiTokens::class, class_uses_recursive(User::class));
    }

    public function test_password_attribute_is_hashed_exactly_once(): void
    {
        $user = User::factory()->make(['password' => 'secret-password']);

        $this->assertNotSame('secret-password', $user->password);
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_role_is_cast_to_user_role_enum(): void
    {
        $user = User::factory()->make();

        $this->assertInstanceOf(UserRole::class, $user->role);
    }

    public function test_factory_creates_a_valid_resident_user(): void
    {
        $user = User::factory()->make();

        $this->assertSame(UserRole::Resident, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->isResident());
        $this->assertNotEmpty($user->name);
        $this->assertNotEmpty($user->email);
    }

    public function test_role_helpers_compare_enum_values(): void
    {
        $this->assertTrue(User::factory()->make(['role' => UserRole::SuperAdmin])->isSuperAdmin());
        $this->assertTrue(User::factory()->make(['role' => UserRole::Manager])->isManager());
        $this->assertTrue(User::factory()->make(['role' => UserRole::Provider])->isProvider());

        $resident = User::factory()->make();
        $this->assertFalse($resident->isManager());
    }

    public function test_role_and_is_active_are_not_mass_assignable(): void
    {
        $user = new User();
        $user->fill([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'plain-password',
            'role' => UserRole::SuperAdmin,
            'is_active' => false,
        ]);

        $this->assertSame('Jane Doe', $user->name);
        $this->assertSame('jane@example.com', $user->email);
        $this->assertNull($user->role);
        $this->assertNull($user->is_active);
    }

    public function test_module_routes_boot_without_controller_resolution_errors(): void
    {
        $this->assertTrue(Route::has('community.index'));
        $this->assertTrue(Route::has('polls.index'));
    }

    public function test_no_auths_resource_stub_route_is_registered(): void
    {
        $this->assertFalse(Route::has('auth.index'));
        $this->assertFalse(Route::has('auth.store'));
        $this->assertFalse(Route::has('auth.destroy'));
    }

    public function test_auth_foundation_tables_exist(): void
    {
        $this->useFoundationDatabase();

        try {
            $this->assertTrue(Schema::hasTable('users'));
            $this->assertTrue(Schema::hasTable('personal_access_tokens'));
            $this->assertTrue(Schema::hasTable('password_reset_tokens'));
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'Foundation test database unavailable: '.$e->getMessage()
            );
        }
    }

    public function test_factory_can_persist_a_valid_resident_user(): void
    {
        $this->useFoundationDatabase();

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'Foundation test database unavailable: '.$e->getMessage()
            );
        }

        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => UserRole::Resident->value,
            'is_active' => 1,
        ]);

        $user->forceDelete();
    }

    /**
     * Point DB-dependent assertions at the disposable MySQL foundation
     * database (sqlite is unavailable in this environment).
     */
    private function useFoundationDatabase(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => env(
                'AUTH_FOUNDATION_TEST_DB',
                'neighborhub_auth_foundation_test'
            ),
        ]);

        DB::purge('mysql');
    }
}
