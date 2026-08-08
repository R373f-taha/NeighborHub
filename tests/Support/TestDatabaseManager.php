<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Owns the full lifecycle of the single approved disposable Authentication
 * test database: drop -> create -> migrate -> (tests) -> drop.
 *
 * Every run recreates the database from scratch so a stale or partially
 * migrated database from an interrupted run can never be reused. Every
 * destructive step is guarded by {@see DatabaseSafetyGuard}; the database
 * name is the compile-time allow-list constant only and is never accepted
 * from input.
 */
final class TestDatabaseManager
{
    public const DATABASE = 'neighborhub_auth_api_test';

    private static bool $provisioned = false;

    private static bool $shutdownRegistered = false;

    /** @var array<string,string> */
    private static array $connectionConfig = [];

    /**
     * Recreate and fully migrate the approved disposable database exactly once
     * per process. Safe to call from every test.
     */
    public static function provision(): void
    {
        if (self::$provisioned) {
            return;
        }

        self::rebuild();

        self::$provisioned = true;
        self::registerShutdown();
    }

    /**
     * Deterministically drop and recreate the approved database, then run all
     * active migrations. Exposed so a stale database can be rebuilt on demand
     * and so the behaviour is directly testable.
     */
    public static function rebuild(): void
    {
        $config = self::connectionConfig();
        $environment = app()->environment();

        self::recreateDatabase($config, $environment);

        // Forget any resolved connection so the next query opens against the
        // freshly created database rather than a pre-drop handle.
        DB::purge('mysql');

        self::migrate();
    }

    public static function isProvisioned(): bool
    {
        return self::$provisioned;
    }

    /**
     * Drop the disposable database after the suite. Re-validates the target
     * and only ever drops the exact allow-list name. Best-effort; never throws
     * from a shutdown handler.
     */
    public static function cleanUp(): void
    {
        if (! self::$provisioned || self::$connectionConfig === []) {
            return;
        }

        $config = self::$connectionConfig;

        try {
            self::assertApprovedTarget($config, 'testing');
        } catch (\Throwable) {
            return;
        }

        $pdo = self::adminPdo($config);

        try {
            $pdo->exec('DROP DATABASE IF EXISTS `'.self::DATABASE.'`');
        } catch (\Throwable) {
            // Cleanup is best-effort; a failed drop is reported by the suite,
            // never fatal during shutdown.
        }

        $pdo = null;
        self::$provisioned = false;
    }

    private static function migrate(): void
    {
        // Re-validate the actually-resolved target before Artisan targets it.
        DatabaseSafetyGuard::assertBootedApplicationSafe(app());

        Artisan::call('migrate', ['--force' => true]);
    }

    /**
     * Drop and recreate the approved database. The guard runs immediately
     * before both statements; only the allow-list constant is interpolated.
     *
     * @param  array<string,string>  $config
     */
    private static function recreateDatabase(array $config, string $environment): void
    {
        $pdo = self::adminPdo($config);

        self::assertApprovedTarget($config, $environment);
        $pdo->exec('DROP DATABASE IF EXISTS `'.self::DATABASE.'`');

        self::assertApprovedTarget($config, $environment);
        $pdo->exec(
            'CREATE DATABASE `'.self::DATABASE.'` '
            .'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        $pdo = null;
    }

    /**
     * @param  array<string,string>  $config
     */
    private static function assertApprovedTarget(array $config, string $environment): void
    {
        DatabaseSafetyGuard::assertSafeForDestructiveOperation(
            $environment,
            (string) ($config['driver'] ?? ''),
            self::DATABASE,
            (string) ($config['host'] ?? ''),
        );
    }

    private static function registerShutdown(): void
    {
        if (self::$shutdownRegistered) {
            return;
        }

        self::$shutdownRegistered = true;

        register_shutdown_function(static function (): void {
            self::cleanUp();
        });
    }

    /**
     * @return array<string,string>
     */
    private static function connectionConfig(): array
    {
        if (self::$connectionConfig === []) {
            $raw = DB::connection('mysql')->getConfig();

            self::$connectionConfig = array_map(
                static fn ($value): string => $value === null ? '' : (string) $value,
                [
                    'driver' => $raw['driver'] ?? '',
                    'host' => $raw['host'] ?? '',
                    'port' => $raw['port'] ?? '',
                    'username' => $raw['username'] ?? '',
                    'password' => $raw['password'] ?? '',
                ]
            );
        }

        return self::$connectionConfig;
    }

    /**
     * @param  array<string,string>  $config
     */
    private static function adminPdo(array $config): PDO
    {
        return new PDO(
            'mysql:host='.($config['host'] ?? '').';port='.($config['port'] ?? ''),
            $config['username'] ?? '',
            $config['password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
