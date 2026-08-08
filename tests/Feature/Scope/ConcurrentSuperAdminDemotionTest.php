<?php

declare(strict_types=1);

namespace Tests\Feature\Scope;

use Illuminate\Support\Facades\DB;
use Modules\Auth\app\Actions\AssignUserRoleAction;
use Modules\Auth\app\Enums\UserRole;
use Modules\Auth\app\Exceptions\LastSuperAdminException;
use Modules\Auth\app\Models\User;
use Modules\Auth\app\Support\AuthSecurityContext;
use PDO;
use PDOException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Concurrency regression test for the final-super-admin demotion protection.
 *
 * NOT committed / NOT staged — local validation only.
 *
 * This deliberately does NOT use RefreshDatabase: the race can only be
 * exercised when the seeded super_admin rows are COMMITTED and therefore
 * visible to a second, independent database connection. The same disposable
 * MySQL test database is used, with manual cleanup in tearDown().
 */
class ConcurrentSuperAdminDemotionTest extends TestCase
{
    /**
     * Exact locking query shape produced by AssignUserRoleAction (an explicit
     * row fetch — never an aggregate — so ORDER BY reaches the executed SQL and
     * InnoDB locks the matching user rows in deterministic users.id order).
     */
    private const LOCK_QUERY = <<<'SQL'
        SELECT `users`.`id`
        FROM `users`
        WHERE EXISTS (
            SELECT *
            FROM `roles`
            INNER JOIN `model_has_roles` ON `roles`.`id` = `model_has_roles`.`role_id`
            WHERE `users`.`id` = `model_has_roles`.`model_id`
              AND `model_has_roles`.`model_type` = ?
              AND `roles`.`name` = ?
        )
        ORDER BY `users`.`id` ASC
        FOR UPDATE
        SQL;

    private const MODEL_TYPE = 'Modules\Auth\app\Models\User';

    /** @var int[] */
    private array $createdUserIds = [];

    /**
     * Committed super_admin assignments that were temporarily removed so this
     * test owns the entire super_admin set. Stored as [model_id => role_id]
     * pairs so they are restored verbatim. Guard-agnostic: committed state can
     * arrive under any guard (other tests assign via the factory which uses the
     * configured default guard), so every super_admin row is stripped and
     * restored regardless of guard. Global state is left untouched.
     *
     * @var array<int, int>
     */
    private array $strippedSuperAdmins = [];

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = (string) config('auth.defaults.guard', 'api');

        foreach (['super_admin', 'manager', 'resident', 'provider'] as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        // This test does NOT use RefreshDatabase (it needs COMMITTED rows
        // visible to a second connection), so it shares the disposable
        // database with any committed state left by other tests. The
        // at-least-one-super-admin invariant is global, so isolate it:
        // temporarily strip the Spatie super_admin role from every committed
        // super_admin, making this test the sole owner of the set, and
        // restore them afterwards.
        $this->stripCommittedSuperAdmins();
    }

    protected function tearDown(): void
    {
        // Remove only the users this test created so committed state never
        // leaks into other tests in the shared disposable database.
        if ($this->createdUserIds !== []) {
            DB::table('model_has_roles')
                ->whereIn('model_id', $this->createdUserIds)
                ->where('model_type', self::MODEL_TYPE)
                ->delete();
            DB::table('users')->whereIn('id', $this->createdUserIds)->delete();
        }

        $this->restoreStrippedSuperAdmins();

        parent::tearDown();
    }

    private function stripCommittedSuperAdmins(): void
    {
        // Collect every committed super_admin assignment across ALL guards.
        $this->strippedSuperAdmins = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'super_admin')
            ->where('model_has_roles.model_type', self::MODEL_TYPE)
            ->pluck('model_has_roles.role_id', 'model_has_roles.model_id')
            ->map(fn ($roleId) => (int) $roleId)
            ->all();

        if ($this->strippedSuperAdmins !== []) {
            DB::table('model_has_roles')
                ->whereIn('role_id', array_values($this->strippedSuperAdmins))
                ->whereIn('model_id', array_keys($this->strippedSuperAdmins))
                ->where('model_type', self::MODEL_TYPE)
                ->delete();
        }
    }

    private function restoreStrippedSuperAdmins(): void
    {
        if ($this->strippedSuperAdmins === []) {
            return;
        }

        $rows = [];
        foreach ($this->strippedSuperAdmins as $modelId => $roleId) {
            $rows[] = [
                'role_id' => $roleId,
                'model_type' => self::MODEL_TYPE,
                'model_id' => $modelId,
            ];
        }

        DB::table('model_has_roles')->insertOrIgnore($rows);
    }

    /**
     * Two concurrent demotions of two DIFFERENT super_admins must not be able
     * to remove both. The second transaction is serialized behind the first's
     * locked super_admin set; by the time it runs, only one super_admin
     * remains and the demotion is refused.
     */
    public function test_concurrent_demotion_of_two_super_admins_keeps_at_least_one(): void
    {
        $action = app(AssignUserRoleAction::class);
        $context = new AuthSecurityContext('127.0.0.1', 'concurrency-test');

        // Initial state: A and B are both super_admin; an unprivileged actor
        // drives the demotions (the Action itself performs no authorization —
        // that is the policy's job — so any user may be passed as the actor).
        $actor = $this->createUser(UserRole::Resident);
        $a = $this->createUser(UserRole::SuperAdmin);
        $b = $this->createUser(UserRole::SuperAdmin);

        $this->assertSame(
            2,
            User::role('super_admin')->count(),
            'Precondition: exactly two super_admins.',
        );

        // --- Phase 1: prove the locked set serializes concurrent access. ---
        // The first transaction (Laravel's own connection) locks the entire
        // super_admin set in users.id order. A second, independent connection
        // then attempts the SAME locking read: it MUST block on the held locks
        // rather than observing a stale "two super_admins" snapshot. We assert
        // it hits InnoDB's lock-wait timeout, which is only possible if it was
        // genuinely blocked — i.e. it could not race past the first lock.
        DB::beginTransaction();

        $lockedIds = User::role('super_admin')
            ->select('users.id')
            ->orderBy('users.id')
            ->lockForUpdate()
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id);

        $this->assertSame(
            [$a->id, $b->id],
            $lockedIds->all(),
            'First transaction locked both super_admin rows in id order.',
        );

        $conn2 = $this->secondPdoConnection();
        $conn2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn2->exec('SET SESSION innodb_lock_wait_timeout = 2');
        $conn2->beginTransaction();

        $blockedByLock = false;
        try {
            $conn2->prepare(self::LOCK_QUERY)
                ->execute([self::MODEL_TYPE, 'super_admin']);
        } catch (PDOException $e) {
            $code = (int) ($e->errorInfo[1] ?? 0);

            $blockedByLock = $code === 1205
                || str_contains($e->getMessage(), 'Lock wait timeout');
        }

        $this->assertTrue(
            $blockedByLock,
            'Concurrent transaction must be blocked by the locked super_admin set '
            .'(it cannot observe a stale count while the first transaction holds the lock).',
        );

        $conn2->rollBack();

        // --- Phase 2: the winning transaction demotes A via the REAL action. ---
        //
        // Run inside the already-open transaction: Laravel nests this as a
        // savepoint on the same connection, so the held set locks stay with us.
        // count == 2 > 1, so the demotion of A is permitted.
        $action->execute($actor, $a->refresh(), 'manager', $context);

        DB::commit();
        // Locks released; A's demotion is now durable.

        // --- Phase 3: the losing transaction retries and is refused. ---
        //
        // Only B is still a super_admin. The real action locks the (now
        // single-element) set, counts it in PHP, and refuses the demotion.
        $denied = false;
        try {
            $action->execute($actor, $b->refresh(), 'manager', $context);
        } catch (LastSuperAdminException) {
            $denied = true;
        }

        $this->assertTrue(
            $denied,
            'The second demotion must be refused once only one super_admin remains.',
        );

        // --- Phase 4: final invariant and synchronization assertions. ---
        $finalSuperAdminCount = User::role('super_admin')->count();
        $this->assertGreaterThanOrEqual(1, $finalSuperAdminCount, 'At least one super_admin must remain.');
        $this->assertSame(1, $finalSuperAdminCount, 'Exactly one super_admin must remain.');

        $freshA = User::find($a->id);
        $freshB = User::find($b->id);

        // Winner: A was demoted; Spatie and users.role stay synchronized.
        $this->assertFalse($freshA->hasRole('super_admin'), 'Demoted A must not keep super_admin.');
        $this->assertTrue($freshA->hasRole('manager'), 'Demoted A must have the new Spatie role.');
        $this->assertSame('manager', $freshA->getRawOriginal('role'), 'Demoted A enum column must match Spatie.');

        // Loser: B stays super_admin; no partial drift.
        $this->assertTrue($freshB->hasRole('super_admin'), 'Protected B must keep super_admin.');
        $this->assertSame('super_admin', $freshB->getRawOriginal('role'), 'Protected B enum column must stay super_admin.');
    }

    private function createUser(UserRole $role): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);

        if (! $user->hasRole($role->value)) {
            $user->syncRoles([$role->value]);
        }

        $this->createdUserIds[] = $user->id;

        return $user;
    }

    private function secondPdoConnection(): PDO
    {
        $config = DB::connection('mysql')->getConfig();

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database'],
        );

        return new PDO($dsn, (string) $config['username'], (string) $config['password']);
    }
}
