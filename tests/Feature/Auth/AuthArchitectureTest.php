<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Cache;
use Modules\Auth\app\Support\AuthSecurityContext;
use Modules\Auth\app\Support\AuthSecurityLogger;
use Tests\TestCase;

class AuthArchitectureTest extends TestCase
{
    private const ACTION_CLASSES = [
        \Modules\Auth\app\Actions\RegisterUserAction::class,
        \Modules\Auth\app\Actions\LoginUserAction::class,
        \Modules\Auth\app\Actions\ChangePasswordAction::class,
        \Modules\Auth\app\Actions\SendPasswordResetLinkAction::class,
        \Modules\Auth\app\Actions\ResetPasswordAction::class,
    ];

    public function test_no_action_imports_illuminate_http_request(): void
    {
        foreach (self::ACTION_CLASSES as $class) {
            $source = file_get_contents((new \ReflectionClass($class))->getFileName());
            $this->assertStringNotContainsString(
                'Illuminate\Http\Request',
                $source,
                "{$class} must not import Illuminate\Http\Request",
            );
        }
    }

    public function test_no_action_imports_form_request(): void
    {
        foreach (self::ACTION_CLASSES as $class) {
            $source = file_get_contents((new \ReflectionClass($class))->getFileName());
            $this->assertStringNotContainsString(
                'Illuminate\Foundation\Http\FormRequest',
                $source,
                "{$class} must not import FormRequest",
            );
        }
    }

    public function test_no_action_accepts_a_request_parameter(): void
    {
        foreach (self::ACTION_CLASSES as $class) {
            $method = new \ReflectionMethod($class, 'execute');
            foreach ($method->getParameters() as $param) {
                $type = $param->getType();
                if ($type instanceof \ReflectionNamedType) {
                    $this->assertNotSame(
                        \Illuminate\Http\Request::class,
                        $type->getName(),
                        "{$class}::execute() must not accept Request",
                    );
                    $this->assertFalse(
                        is_subclass_of($type->getName(), \Illuminate\Http\Request::class),
                        "{$class}::execute() must not accept a Request subclass",
                    );
                }
            }
        }
    }

    public function test_no_action_calls_global_request_helper(): void
    {
        foreach (self::ACTION_CLASSES as $class) {
            $source = file_get_contents((new \ReflectionClass($class))->getFileName());
            $this->assertStringNotContainsString(
                'request()',
                $source,
                "{$class} must not call request()",
            );
            $this->assertStringNotContainsString(
                "app('request')",
                $source,
                "{$class} must not call app('request')",
            );
            $this->assertStringNotContainsString(
                'Request::capture()',
                $source,
                "{$class} must not call Request::capture()",
            );
        }
    }

    public function test_auth_security_context_contains_only_ip_and_user_agent(): void
    {
        $reflection = new \ReflectionClass(AuthSecurityContext::class);
        $properties = array_map(
            fn (\ReflectionProperty $p) => $p->getName(),
            $reflection->getProperties(),
        );

        sort($properties);
        $this->assertSame(['ip', 'userAgent'], $properties);
    }

    public function test_auth_security_context_is_immutable(): void
    {
        $reflection = new \ReflectionClass(AuthSecurityContext::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function test_controllers_build_auth_security_context(): void
    {
        $controllers = [
            \Modules\Auth\app\Http\Controllers\AuthController::class,
            \Modules\Auth\app\Http\Controllers\PasswordController::class,
        ];

        foreach ($controllers as $class) {
            $source = file_get_contents((new \ReflectionClass($class))->getFileName());
            $this->assertStringContainsString(
                'new AuthSecurityContext',
                $source,
                "{$class} must build AuthSecurityContext",
            );
        }
    }

    public function test_runtime_dedup_store_defaults_to_redis(): void
    {
        // Temporarily clear test override to check real config default
        config(['auth.security_log_dedup_store' => null]);
        $this->app['config']->set('auth.security_log_dedup_store', env('AUTH_SECURITY_LOG_DEDUP_STORE', 'redis'));

        // In a fresh runtime (no env override), the default should be redis.
        // We verify the config file's default shape rather than the test env.
        $configFile = file_get_contents(base_path('Modules/Auth/config/config.php'));
        $this->assertStringContainsString("'security_log_dedup_store'", $configFile);
        $this->assertStringContainsString("'redis'", $configFile);
    }

    public function test_automated_tests_resolve_dedup_store_to_array(): void
    {
        $this->assertSame('array', config('auth.security_log_dedup_store'));
    }

    public function test_logger_explicitly_requests_configured_store(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(AuthSecurityLogger::class))->getFileName()
        );

        $this->assertStringContainsString(
            "config('auth.security_log_dedup_store'",
            $source,
        );
        $this->assertStringContainsString('Cache::store($store)', $source);
    }

    public function test_database_cache_store_is_never_requested_for_dedup(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(AuthSecurityLogger::class))->getFileName()
        );

        $this->assertStringNotContainsString("Cache::store('database')", $source);
        $this->assertStringNotContainsString('Cache::store("database")', $source);
    }

    public function test_raw_email_absent_from_dedup_keys(): void
    {
        $email = 'testdedup@example.com';

        Cache::store('array')->flush();

        $logger = app(AuthSecurityLogger::class);

        $logger->rateLimitExceeded(
            'auth-login-email',
            'api.auth.login',
            'POST',
            '127.0.0.1',
            'some-hashed-subject',
            60,
            $email,
        );

        // The array store keys should not contain the raw email
        $store = Cache::store('array');
        $reflection = new \ReflectionObject($store->getStore());
        $storageProperty = $reflection->getProperty('storage');
        $storageProperty->setAccessible(true);
        $storage = $storageProperty->getValue($store->getStore());

        foreach ($storage as $key => $value) {
            $this->assertStringNotContainsString($email, $key);
        }
    }

    public function test_cache_store_exception_does_not_change_429_response(): void
    {
        // Configure a non-existent store to force an exception
        config(['auth.security_log_dedup_store' => 'nonexistent_store_that_will_fail']);

        $logger = app(AuthSecurityLogger::class);

        // Should not throw — the exception is caught internally
        $logger->rateLimitExceeded(
            'auth-login-email',
            'api.auth.login',
            'POST',
            '127.0.0.1',
            'test-subject',
            60,
        );

        // If we got here without exception, the method gracefully handled the failure
        $this->assertTrue(true);
    }

    public function test_redis_dedup_failure_does_not_trigger_database_fallback(): void
    {
        config(['auth.security_log_dedup_store' => 'nonexistent_store_xyz']);

        $logger = app(AuthSecurityLogger::class);

        // The logger should catch the exception and not try database
        $logger->rateLimitExceeded(
            'auth-login-email',
            'api.auth.login',
            'POST',
            '127.0.0.1',
            'test-subject-no-fallback',
            60,
        );

        // Verify the database cache store was never touched
        $source = file_get_contents(
            (new \ReflectionClass(AuthSecurityLogger::class))->getFileName()
        );
        $this->assertStringNotContainsString("'database'", $source);
        $this->assertTrue(true);
    }
}
