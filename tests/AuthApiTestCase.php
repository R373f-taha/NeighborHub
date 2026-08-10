<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Auth\app\Models\User;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

/**
 * Base test case for the Authentication API feature tests.
 *
 * The disposable MySQL database is selected before Laravel boots
 * (tests/bootstrap.php) and provisioned by Tests\Support\TestDatabaseManager.
 * RefreshDatabase wraps each test in a transaction for isolation.
 */
abstract class AuthApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected const VALID_PASSWORD = 'correct horse battery staple';

    /**
     * Each HTTP request within a test reuses the same in-memory application, and
     * Sanctum's request guard caches the resolved user for that lifetime. Drop
     * cached guards before every request so token revocation is enforced across
     * sequential requests (as it is in production, where each request has its
     * own process and application).
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if ($this->app && $this->app->bound('auth')) {
            $this->app['auth']->forgetGuards();
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /**
     * Prepare a clean, isolated state for each test. The array cache is flushed
     * and the auth-owned tables are emptied so rows never leak between tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        // Never persist Authentication security events to disk during tests.
        // Logging-specific tests override this with an in-memory capture.
        $securityLogger = Log::channel('security');
        if (method_exists($securityLogger, 'getLogger')) {
            $underlyingLogger = $securityLogger->getLogger();
            if ($underlyingLogger instanceof Logger) {
                $underlyingLogger->setHandlers([new NullHandler()]);
            }
        }

        DB::table('personal_access_tokens')->delete();
        DB::table('password_reset_tokens')->delete();
        DB::table('users')->delete();
    }

    protected function createTokenForUser(User $user, string $device = 'test-device'): string
    {
        return $user->createToken($device, ['*'], now()->addDays(30))->plainTextToken;
    }

    /**
     * @return array<string, string>
     */
    protected function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }
}
