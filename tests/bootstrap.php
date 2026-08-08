<?php

declare(strict_types=1);
use Tests\Support\TestDatabaseManager;

// PHPUnit bootstrap, loaded before Laravel boots.
//
// The disposable Authentication test database is selected here, at the
// process level, so database-dependent services (db, cache, session, rate
// limiter) resolve against it on first use and can never target the
// development database. These values agree with phpunit.xml.

$testingEnvironment = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'mysql',
    'DB_DATABASE' => TestDatabaseManager::DATABASE,
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'BROADCAST_CONNECTION' => 'null',
    'BCRYPT_ROUNDS' => '4',
    'TELESCOPE_ENABLED' => 'false',
    'PULSE_ENABLED' => 'false',
    'NIGHTWATCH_ENABLED' => 'false',
    'AUTH_SECURITY_LOG_DEDUP_STORE' => 'array',
];

foreach ($testingEnvironment as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

require __DIR__.'/../vendor/autoload.php';
