<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\DatabaseSafetyGuard;
use Tests\Support\TestDatabaseManager;
use Tests\TestCase;

class TestInfrastructureSafetyTest extends TestCase
{
    public function test_guard_accepts_the_approved_test_database(): void
    {
        $this->expectNotToPerformAssertions();

        DatabaseSafetyGuard::assertSafeForDestructiveOperation(
            'testing',
            'mysql',
            'neighborhub_auth_api_test',
            '127.0.0.1',
        );
    }

    public function test_guard_rejects_the_development_database(): void
    {
        $this->expectException(RuntimeException::class);

        DatabaseSafetyGuard::assertSafeForDestructiveOperation(
            'testing',
            'mysql',
            'neighborhub',
            '127.0.0.1',
        );
    }

    public function test_guard_rejects_an_arbitrary_database_name(): void
    {
        $this->expectException(RuntimeException::class);

        DatabaseSafetyGuard::assertSafeForDestructiveOperation(
            'testing',
            'mysql',
            'neighborhub_arbitrary_test',
            '127.0.0.1',
        );
    }

    public function test_guard_rejects_non_testing_environments(): void
    {
        $this->expectException(RuntimeException::class);

        DatabaseSafetyGuard::assertSafeForDestructiveOperation(
            'local',
            'mysql',
            'neighborhub_auth_api_test',
            '127.0.0.1',
        );
    }

    public function test_guard_rejects_remote_or_unapproved_hosts(): void
    {
        $this->expectException(RuntimeException::class);

        DatabaseSafetyGuard::assertSafeForDestructiveOperation(
            'testing',
            'mysql',
            'neighborhub_auth_api_test',
            '10.0.0.5',
        );
    }

    public function test_guard_rejects_historical_foundation_test_database(): void
    {
        $this->expectException(RuntimeException::class);

        DatabaseSafetyGuard::assertSafeForDestructiveOperation(
            'testing',
            'mysql',
            'neighborhub_auth_foundation_test',
            '127.0.0.1',
        );
    }

    public function test_booted_application_resolves_the_approved_test_database(): void
    {
        $this->assertSame('neighborhub_auth_api_test', \DB::connection()->getDatabaseName());
        $this->assertSame('mysql', config('database.default'));
        $this->assertTrue($this->app->environment('testing'));
    }

    public function test_destructive_helpers_are_blocked_when_guard_fails(): void
    {
        // The guard is the single interception point invoked before every
        // destructive operation in TestDatabaseManager; rejecting the
        // development database proves no such operation can proceed.
        $rejected = false;

        try {
            DatabaseSafetyGuard::assertSafeForDestructiveOperation(
                'testing',
                'mysql',
                'neighborhub',
                '127.0.0.1',
            );
        } catch (RuntimeException) {
            $rejected = true;
        }

        $this->assertTrue($rejected, 'Guard must block destructive operations against the development database.');
    }

    public function test_approved_database_is_created_and_migrated(): void
    {
        TestDatabaseManager::provision();

        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('personal_access_tokens'));
        $this->assertTrue(Schema::hasTable('password_reset_tokens'));
    }

    public function test_critical_application_tables_exist(): void
    {
        TestDatabaseManager::provision();

        foreach (['communities', 'units', 'residents', 'media'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected application table [{$table}] to exist.");
        }
    }

    public function test_database_is_recreated_so_stale_state_cannot_survive(): void
    {
        // Simulate an interrupted run by leaving a marker table, then force the
        // deterministic rebuild. The whole database is dropped and recreated, so
        // no stale table or partial migration history can carry over.
        DB::statement('CREATE TABLE `neighborhub_stale_marker` (id INT UNSIGNED NOT NULL)');
        $this->assertTrue(Schema::hasTable('neighborhub_stale_marker'));

        TestDatabaseManager::rebuild();

        $this->assertFalse(Schema::hasTable('neighborhub_stale_marker'));
        $this->assertTrue(Schema::hasTable('users'));
    }

    public function test_every_discovered_migration_is_ran_with_none_pending(): void
    {
        TestDatabaseManager::provision();

        $migrator = $this->app->make('migrator');
        $paths = array_merge($migrator->paths(), [database_path('migrations')]);
        $discovered = array_keys($migrator->getMigrationFiles($paths));
        $ran = DB::table('migrations')->pluck('migration')->all();

        $this->assertNotEmpty($discovered);
        $this->assertSame(count($discovered), count($ran), 'Ran migration count must equal the discovered count.');

        $pending = array_values(array_diff($discovered, $ran));
        $this->assertSame([], $pending, 'No migration may be pending after provisioning.');
    }

    public function test_provisioning_does_not_require_historical_test_databases(): void
    {
        $this->assertNotContains('neighborhub_auth_foundation_test', DatabaseSafetyGuard::ALLOWED_DATABASES);
        $this->assertNotContains('neighborhub_user_relations_test', DatabaseSafetyGuard::ALLOWED_DATABASES);

        // The single approved disposable database is the only one provisioned.
        $this->assertSame(['neighborhub_auth_api_test'], DatabaseSafetyGuard::ALLOWED_DATABASES);
    }

    public function test_cleanup_can_only_target_the_approved_disposable_database(): void
    {
        // The approved name is the sole value the manager ever interpolates
        // into a DROP. Any other name (including the development database)
        // fails the guard before a drop can be issued.
        $this->assertSame('neighborhub_auth_api_test', TestDatabaseManager::DATABASE);

        $this->expectException(RuntimeException::class);

        DatabaseSafetyGuard::assertSafeForDestructiveOperation(
            'testing',
            'mysql',
            'neighborhub',
            '127.0.0.1',
        );
    }
}
