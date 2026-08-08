<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * Fail-closed guard for every destructive test database operation.
 *
 * Validated immediately before any migrate:fresh / migrate:reset / db:wipe /
 * DROP DATABASE / table cleanup, and asserted again on the booted application.
 * Any failed check throws; the caller never continues.
 */
final class DatabaseSafetyGuard
{
    public const ALLOWED_DATABASES = [
        'neighborhub_auth_api_test',
    ];

    public const FORBIDDEN_DATABASES = [
        'neighborhub',
    ];

    public const ALLOWED_LOCAL_HOSTS = [
        '127.0.0.1',
        'localhost',
        'mysql',
    ];

    /**
     * Validate an explicit target snapshot before a destructive operation.
     */
    public static function assertSafeForDestructiveOperation(
        string $environment,
        string $driver,
        string $database,
        string $host,
    ): void {
        $failures = [];

        if ($environment !== 'testing') {
            $failures[] = 'environment must be "testing"';
        }

        if ($driver !== 'mysql') {
            $failures[] = 'database driver must be mysql';
        }

        // Layer 4: the development database is always rejected, even if some
        // other check were to pass. Exact allow-list only; never a prefix.
        if (in_array($database, self::FORBIDDEN_DATABASES, true)) {
            $failures[] = 'target database is on the forbidden list';
        }

        if (! in_array($database, self::ALLOWED_DATABASES, true)) {
            $failures[] = 'target database is not on the approved allow-list';
        }

        if (! in_array($host, self::ALLOWED_LOCAL_HOSTS, true)) {
            $failures[] = 'database host is not a verified local host';
        }

        if ($failures !== []) {
            throw new RuntimeException(
                'Refusing destructive operation against an unsafe test target: '
                .implode('; ', $failures)
            );
        }
    }

    /**
     * Resolve the live target from the booted application and assert it.
     */
    public static function assertBootedApplicationSafe(object $app): void
    {
        $config = $app->make('config');
        $defaultConnection = (string) $config->get('database.default');

        $connection = $app->make('db')->connection($defaultConnection);

        self::assertSafeForDestructiveOperation(
            (string) $app->environment(),
            (string) $connection->getConfig('driver'),
            (string) $connection->getDatabaseName(),
            (string) $connection->getConfig('host'),
        );
    }
}
