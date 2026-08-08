<?php

declare(strict_types=1);

namespace Tests\Feature\Scope;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Auth\app\Models\User;
use Monolog\Handler\NullHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Validates the Role Assignment endpoint and Spatie/enum synchronization.
 *
 * NOT committed / NOT staged — local validation only.
 */
class RoleAssignmentTest extends ScopeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    private function seedRbac(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        // Use the application's default guard (api) so it matches
        // UserFactory::configure() (assignRole) and the Gate::before permission
        // check that authorizes the role-assignment endpoint. The base TestCase
        // already creates the four roles; here we only grant the assign_role
        // permission that only super_admin may use (UserPolicy::assignRole).
        $guard = (string) config('auth.defaults.guard', 'api');
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => $guard]);
        $assign = Permission::firstOrCreate(['name' => 'assign_role', 'guard_name' => $guard]);
        $superAdmin->givePermissionTo($assign);
    }

    private function superAdmin(): User
    {
        return User::factory()->superAdmin()->create(['is_active' => true]);
    }

    public function test_anonymous_request_is_unauthenticated(): void
    {
        $target = User::factory()->resident()->create();

        $this->patchJson("/api/v1/users/{$target->id}/role", ['role' => 'manager'])
            ->assertStatus(401);
    }

    public function test_resident_cannot_assign_role(): void
    {
        $actor = User::factory()->resident()->create(['is_active' => true]);
        $target = User::factory()->resident()->create();

        $this->patchJson("/api/v1/users/{$target->id}/role", ['role' => 'manager'], $this->token($actor))
            ->assertStatus(403);
    }

    public function test_manager_cannot_assign_role(): void
    {
        $actor = User::factory()->manager()->create(['is_active' => true]);
        $target = User::factory()->resident()->create();

        $this->patchJson("/api/v1/users/{$target->id}/role", ['role' => 'manager'], $this->token($actor))
            ->assertStatus(403);
    }

    public function test_provider_cannot_assign_role(): void
    {
        $actor = User::factory()->provider()->create(['is_active' => true]);
        $target = User::factory()->resident()->create();

        $this->patchJson("/api/v1/users/{$target->id}/role", ['role' => 'manager'], $this->token($actor))
            ->assertStatus(403);
    }

    public function test_invalid_role_is_unprocessable(): void
    {
        $actor = $this->superAdmin();
        $target = User::factory()->resident()->create();

        $this->patchJson("/api/v1/users/{$target->id}/role", ['role' => 'wizard'], $this->token($actor))
            ->assertStatus(422);
    }

    public function test_unknown_user_is_not_found(): void
    {
        $actor = $this->superAdmin();

        $this->patchJson('/api/v1/users/999999/role', ['role' => 'manager'], $this->token($actor))
            ->assertStatus(404);
    }

    public function test_user_cannot_change_own_role(): void
    {
        $actor = $this->superAdmin();

        $this->patchJson("/api/v1/users/{$actor->id}/role", ['role' => 'manager'], $this->token($actor))
            ->assertStatus(403);
    }

    public function test_super_admin_can_assign_role_and_response_contract(): void
    {
        $actor = $this->superAdmin();
        $target = User::factory()->resident()->create();

        $response = $this->patchJson("/api/v1/users/{$target->id}/role", ['role' => 'manager'], $this->token($actor))
            ->assertStatus(200);

        $response->assertJsonPath('message', 'User role updated successfully.');
        $response->assertJsonPath('data.id', $target->id);
        $response->assertJsonPath('data.role', 'manager');
    }

    public function test_role_change_synchronizes_spatie_and_enum_column(): void
    {
        $actor = $this->superAdmin();
        $target = User::factory()->resident()->create();

        $this->patchJson("/api/v1/users/{$target->id}/role", ['role' => 'manager'], $this->token($actor))
            ->assertStatus(200);

        $fresh = User::find($target->id);
        $this->assertTrue($fresh->hasRole('manager'), 'Spatie role not updated.');
        $this->assertSame('manager', $fresh->getRawOriginal('role'), 'Enum column not synchronized.');
        $this->assertFalse($fresh->hasRole('resident'), 'Old Spatie role not removed.');
    }

    public function test_assigning_same_role_is_idempotent(): void
    {
        $actor = $this->superAdmin();
        $target = User::factory()->manager()->create();

        $this->patchJson("/api/v1/users/{$target->id}/role", ['role' => 'manager'], $this->token($actor))
            ->assertStatus(200)
            ->assertJsonPath('message', 'User role updated successfully.');

        $fresh = User::find($target->id);
        $this->assertTrue($fresh->hasRole('manager'));
        $this->assertSame('manager', $fresh->getRawOriginal('role'));
    }

    public function test_final_super_admin_is_protected(): void
    {
        // The final-superadmin invariant is enforced inside the Action (the
        // policy can never route a request that would reach zero super_admins
        // because the actor must itself be a distinct super_admin; the guard
        // exists to defeat concurrent demotions). Verify it directly.
        $sole = User::factory()->superAdmin()->create();

        // Make this test deterministic regardless of any cross-test state in
        // the shared disposable DB: ensure $sole is the only super_admin for
        // the duration of this transaction (rolled back afterwards).
        User::role('super_admin')
            ->whereKeyNot($sole->id)
            ->get()
            ->each(fn (User $other) => $other->syncRoles([]));

        $this->assertSame(1, User::role('super_admin')->count(), 'sole must be the only super_admin');

        $actor = User::factory()->resident()->create();

        $threw = false;
        try {
            app(\Modules\Auth\app\Actions\AssignUserRoleAction::class)
                ->execute($actor, $sole, 'manager', new \Modules\Auth\app\Support\AuthSecurityContext('127.0.0.1', 'tester'));
        } catch (\RuntimeException $e) {
            $threw = true;
            $this->assertStringContainsString('last remaining super_admin', $e->getMessage());
        }

        $this->assertTrue($threw, 'Expected the last super_admin demotion to be refused.');

        $still = User::find($sole->id);
        $this->assertTrue($still->hasRole('super_admin'), 'Last super_admin was demoted.');
        $this->assertSame('super_admin', $still->getRawOriginal('role'), 'Enum desynced on protected demotion.');
    }

    public function test_demotion_allowed_when_multiple_super_admins(): void
    {
        $sa1 = User::factory()->superAdmin()->create();
        $sa2 = User::factory()->superAdmin()->create();

        app(\Modules\Auth\app\Actions\AssignUserRoleAction::class)
            ->execute($sa1, $sa2, 'manager', new \Modules\Auth\app\Support\AuthSecurityContext('127.0.0.1', 'tester'));

        $this->assertTrue(User::find($sa1->id)->hasRole('super_admin'));
        $this->assertFalse(User::find($sa2->id)->hasRole('super_admin'));
        $this->assertSame('manager', User::find($sa2->id)->getRawOriginal('role'));
    }

    public function test_unexpected_runtime_exception_is_not_mapped_to_403(): void
    {
        // The controller must only render the intentional business denial
        // (LastSuperAdminException) as 403. An arbitrary/unexpected runtime
        // failure must NOT be disguised as an authorization denial; it should
        // surface as a genuine server error so defects are not hidden.
        $actor = $this->superAdmin();
        $target = User::factory()->resident()->create();

        $this->mock(\Modules\Auth\app\Actions\AssignUserRoleAction::class, function ($mock): void {
            $mock->shouldReceive('execute')
                ->andThrow(new \RuntimeException('Unexpected database failure.'));
        });

        $response = $this->patchJson("/api/v1/users/{$target->id}/role", ['role' => 'manager'], $this->token($actor));

        $this->assertNotSame(403, $response->status(), 'Unexpected runtime failures must not be disguised as 403.');
        $this->assertGreaterThanOrEqual(500, $response->status(), 'Unexpected runtime failures must surface as a server error.');
    }

    public function test_security_log_emitted_with_safe_fields_only(): void
    {
        $handler = new TestHandler();
        $logger = Log::channel('security')->getLogger();
        $original = $logger instanceof Logger ? $logger->getHandlers() : [];
        if ($logger instanceof Logger) {
            $logger->setHandlers([$handler]);
        }

        $actor = $this->superAdmin();
        $target = User::factory()->resident()->create();

        $this->patchJson("/api/v1/users/{$target->id}/role", ['role' => 'provider'], $this->token($actor))
            ->assertStatus(200);

        if ($logger instanceof Logger) {
            $logger->setHandlers($original ?: [new NullHandler()]);
        }

        $found = false;
        foreach ($handler->getRecords() as $record) {
            if (str_contains($record['message'] ?? '', 'user.role.updated')) {
                $found = true;
                $ctx = $record['context'];
                $this->assertSame($actor->id, $ctx['actor_user_id']);
                $this->assertSame($target->id, $ctx['target_user_id']);
                $this->assertSame('resident', $ctx['old_role']);
                $this->assertSame('provider', $ctx['new_role']);
                $this->assertSame('success', $ctx['result']);
                $this->assertArrayNotHasKey('password', $ctx);
                $this->assertArrayNotHasKey('token', $ctx);
                $this->assertArrayNotHasKey('Authorization', $ctx);
                $this->assertArrayNotHasKey('body', $ctx);
            }
        }
        $this->assertTrue($found, 'user.role.updated security log not emitted.');
    }

    /**
     * Authority-boundary regression: final-super-admin protection MUST follow
     * the authoritative Spatie role and can NEVER be bypassed by a stale
     * users.role compatibility value.
     *
     * Setup is intentionally inconsistent: Spatie says super_admin, the legacy
     * users.role mirror says 'resident', and this user is the ONLY Spatie
     * super_admin. Demoting them must still be refused.
     */
    public function test_last_super_admin_protection_follows_spatie_not_the_role_column(): void
    {
        $sole = User::factory()->superAdmin()->create();

        // Ensure $sole is the final Spatie super_admin.
        User::role('super_admin')
            ->whereKeyNot($sole->id)
            ->get()
            ->each(fn (User $other) => $other->syncRoles([]));

        // Introduce drift on the mirror column ONLY — do not touch Spatie.
        DB::table('users')->where('id', $sole->id)->update(['role' => 'resident']);

        $this->assertTrue($sole->fresh()->hasRole('super_admin'), 'Spatie still reports super_admin.');
        $this->assertSame(
            'resident',
            DB::table('users')->where('id', $sole->id)->value('role'),
            'Mirror deliberately drifted to resident.',
        );

        $actor = User::factory()->resident()->create();

        $threw = false;
        try {
            app(\Modules\Auth\app\Actions\AssignUserRoleAction::class)
                ->execute(
                    $actor,
                    $sole->refresh(),
                    'manager',
                    new \Modules\Auth\app\Support\AuthSecurityContext('127.0.0.1', 'drift-test'),
                );
        } catch (\Modules\Auth\app\Exceptions\LastSuperAdminException $e) {
            $threw = true;
        }

        $this->assertTrue(
            $threw,
            'Demotion must be refused from the authoritative Spatie role, not the drifted users.role mirror.',
        );

        // The Spatie super_admin survives; the stale mirror could not bypass it.
        $still = User::find($sole->id);
        $this->assertTrue($still->hasRole('super_admin'), 'Final Spatie super_admin must remain.');
        $this->assertSame(1, User::role('super_admin')->count(), 'Exactly one Spatie super_admin remains.');
    }
}
