<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Notification;
use Modules\Auth\app\Models\User;
use Tests\AuthApiTestCase;
use Tests\Support\CapturesSecurityLog;

class SecurityLogRateLimitTest extends AuthApiTestCase
{
    use CapturesSecurityLog;

    private const PASSWORD = 'a sufficiently long test passphrase';

    public function test_rate_limited_login_logs_event_without_raw_email(): void
    {
        User::factory()->create(['email' => 'limited@example.com', 'password' => self::PASSWORD]);

        $payload = [
            'email' => 'limited@example.com',
            'password' => 'wrong passphrase value',
            'device_name' => 'Web',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', $payload)->assertStatus(401);
        }

        $this->captureSecurityLog();

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $this->assertSame(429, $response->status());
        $this->assertSame('Too Many Attempts.', $response->json('message'));

        $this->assertContains('auth.rate_limit.exceeded', $this->securityEvents());

        $context = collect($this->securityContexts())
            ->firstWhere('event', 'auth.rate_limit.exceeded');

        $this->assertNotNull($context);
        $this->assertSame('blocked', $context['result']);
        $this->assertSame('auth-login-email', $context['limiter']);
        $this->assertSame('POST', $context['http_method']);
        $this->assertSame('api.auth.login', $context['route']);
        $this->assertArrayHasKey('email_fingerprint', $context);

        $this->assertStringNotContainsString(
            'limited@example.com',
            $this->serializedSecurityRecords(),
        );
    }

    public function test_repeated_blocked_attempts_within_a_window_log_once(): void
    {
        User::factory()->create(['email' => 'dedup@example.com', 'password' => self::PASSWORD]);

        $payload = [
            'email' => 'dedup@example.com',
            'password' => 'wrong passphrase value',
            'device_name' => 'Web',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', $payload)->assertStatus(401);
        }

        $this->captureSecurityLog();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/auth/login', $payload)->assertStatus(429);
        }

        $rateLimitEvents = array_values(array_filter(
            $this->securityEvents(),
            static fn (string $event): bool => $event === 'auth.rate_limit.exceeded',
        ));

        $this->assertCount(1, $rateLimitEvents);
    }

    public function test_rate_limited_forgot_password_logs_event(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'rl@example.com']);

        $payload = ['email' => 'rl@example.com'];

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', $payload)->assertOk();
        }

        $this->captureSecurityLog();

        $this->postJson('/api/v1/auth/forgot-password', $payload)->assertStatus(429);

        $context = collect($this->securityContexts())
            ->firstWhere('event', 'auth.rate_limit.exceeded');

        $this->assertNotNull($context);
        $this->assertSame('auth-forgot-email', $context['limiter']);

        $this->assertStringNotContainsString(
            'rl@example.com',
            $this->serializedSecurityRecords(),
        );
    }
}
