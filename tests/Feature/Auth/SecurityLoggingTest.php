<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Modules\Auth\app\Models\User;
use Tests\AuthApiTestCase;
use Tests\Support\CapturesSecurityLog;

class SecurityLoggingTest extends AuthApiTestCase
{
    use CapturesSecurityLog;

    private const PASSWORD = 'a sufficiently long test passphrase';

    public function test_security_channel_is_configured(): void
    {
        $this->assertSame('daily', config('logging.channels.security.driver'));
        $this->assertStringContainsString('security.log', (string) config('logging.channels.security.path'));
    }

    public function test_successful_registration_logs_one_event(): void
    {
        $this->captureSecurityLog();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Log User',
            'email' => 'log@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'device_name' => 'Ali iPhone',
        ]);

        $response->assertCreated();

        $this->assertCount(1, $this->securityRecords());
        $this->assertSame(['auth.registration.succeeded'], $this->securityEvents());

        $context = $this->securityContexts()[0];
        $this->assertSame('auth.registration.succeeded', $context['event']);
        $this->assertSame('success', $context['result']);
        $this->assertSame($response->json('data.user.id'), $context['user_id']);
        $this->assertSame('Ali iPhone', $context['token_name']);
        $this->assertSame($this->emailFingerprint('log@example.com'), $context['email_fingerprint']);
        $this->assertArrayNotHasKey('access_token', $context);
    }

    public function test_failed_registration_validation_does_not_log_a_success_event(): void
    {
        $this->captureSecurityLog();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Bad',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'short',
            'device_name' => 'Web',
        ])->assertStatus(422);

        $this->assertSame([], $this->securityEvents());
    }

    public function test_successful_login_logs_one_event(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => self::PASSWORD,
        ]);

        $this->captureSecurityLog();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => self::PASSWORD,
            'device_name' => 'Ali iPhone',
        ])->assertOk();

        $this->assertCount(1, $this->securityRecords());
        $this->assertSame(['auth.login.succeeded'], $this->securityEvents());

        $context = $this->securityContexts()[0];
        $this->assertSame($user->id, $context['user_id']);
        $this->assertSame($this->emailFingerprint('login@example.com'), $context['email_fingerprint']);
    }

    public function test_wrong_password_logs_generic_failed_event(): void
    {
        User::factory()->create(['email' => 'wp@example.com', 'password' => self::PASSWORD]);

        $this->captureSecurityLog();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'wp@example.com',
            'password' => 'totally wrong passphrase value',
            'device_name' => 'Web',
        ])->assertStatus(401);

        $this->assertSame(['auth.login.failed'], $this->securityEvents());

        $context = $this->securityContexts()[0];
        $this->assertSame('invalid_credentials', $context['result']);
        $this->assertArrayNotHasKey('user_id', $context);
        $this->assertSame($this->emailFingerprint('wp@example.com'), $context['email_fingerprint']);
    }

    public function test_missing_user_logs_the_same_generic_failed_event(): void
    {
        $this->captureSecurityLog();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ghost@example.com',
            'password' => self::PASSWORD,
            'device_name' => 'Web',
        ])->assertStatus(401);

        $this->assertSame(['auth.login.failed'], $this->securityEvents());
        $this->assertSame('invalid_credentials', $this->securityContexts()[0]['result']);
        $this->assertArrayNotHasKey('user_id', $this->securityContexts()[0]);
    }

    public function test_inactive_user_logs_blocked_inactive_event_but_returns_generic_401(): void
    {
        User::factory()->create(['email' => 'inactive@example.com', 'password' => self::PASSWORD, 'is_active' => false]);

        $this->captureSecurityLog();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@example.com',
            'password' => self::PASSWORD,
            'device_name' => 'Web',
        ])->assertStatus(401)
            ->assertJsonPath('message', 'The provided credentials are invalid.');

        $this->assertSame(['auth.login.blocked_inactive'], $this->securityEvents());

        $context = $this->securityContexts()[0];
        $this->assertSame('blocked', $context['result']);
        $this->assertArrayHasKey('user_id', $context);
    }

    public function test_logout_logs_after_token_deletion_and_preserves_other_tokens(): void
    {
        $user = User::factory()->create();
        $tokenA = $this->createTokenForUser($user, 'Device A');
        $this->createTokenForUser($user, 'Device B');

        $this->captureSecurityLog();

        $this->withHeaders($this->bearer($tokenA))->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertSame(['auth.logout.succeeded'], $this->securityEvents());

        $context = $this->securityContexts()[0];
        $this->assertSame($user->id, $context['user_id']);
        $this->assertSame(1, $context['revoked_token_count']);
        $this->assertSame('Device A', $context['token_name']);

        $this->withHeaders($this->bearer($tokenA))->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->assertSame(1, \DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());
    }

    public function test_password_change_logs_event_with_revoked_token_count(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $tokenA = $this->createTokenForUser($user, 'Device A');
        $this->createTokenForUser($user, 'Device B');
        $this->createTokenForUser($user, 'Device C');

        $this->captureSecurityLog();

        $this->withHeaders($this->bearer($tokenA))->putJson('/api/v1/auth/password', [
            'current_password' => self::PASSWORD,
            'password' => 'a brand new secure passphrase',
            'password_confirmation' => 'a brand new secure passphrase',
        ])->assertNoContent();

        $this->assertSame(['auth.password.changed'], $this->securityEvents());

        $context = $this->securityContexts()[0];
        $this->assertSame($user->id, $context['user_id']);
        $this->assertSame(2, $context['revoked_token_count']);
    }

    public function test_forgot_password_logs_same_event_for_existing_and_missing_accounts(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'exists@example.com']);

        $this->captureSecurityLog();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'exists@example.com'])->assertOk();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ghost@example.com'])->assertOk();

        $events = $this->securityEvents();
        $this->assertSame(['auth.password_reset.requested', 'auth.password_reset.requested'], $events);

        foreach ($this->securityContexts() as $context) {
            $this->assertSame('accepted', $context['result']);
            $this->assertArrayNotHasKey('user_exists', $context);
            $this->assertArrayNotHasKey('user_missing', $context);
            $this->assertArrayNotHasKey('user_id', $context);
        }
    }

    public function test_reset_password_logs_completion_event(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com', 'password' => self::PASSWORD]);
        $this->createTokenForUser($user, 'Old Device');

        $this->captureSecurityLog();

        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'a freshly reset secure passphrase',
            'password_confirmation' => 'a freshly reset secure passphrase',
        ])->assertOk();

        $this->assertSame(['auth.password_reset.completed'], $this->securityEvents());

        $context = $this->securityContexts()[0];
        $this->assertSame($user->id, $context['user_id']);
        $this->assertSame(1, $context['revoked_token_count']);
        $this->assertSame($this->emailFingerprint('reset@example.com'), $context['email_fingerprint']);
    }

    public function test_invalid_reset_token_does_not_log_completion(): void
    {
        $this->captureSecurityLog();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'invalid@example.com',
            'token' => 'this-is-not-a-valid-token',
            'password' => 'a freshly reset secure passphrase',
            'password_confirmation' => 'a freshly reset secure passphrase',
        ])->assertStatus(422);

        $this->assertNotContains('auth.password_reset.completed', $this->securityEvents());
    }

    public function test_email_fingerprint_uses_hmac_sha256_and_raw_email_is_absent(): void
    {
        $this->captureSecurityLog();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'Fingerprint@Example.com',
            'password' => 'wrong passphrase value',
            'device_name' => 'Web',
        ])->assertStatus(401);

        $context = $this->securityContexts()[0];

        $this->assertSame(
            hash_hmac('sha256', 'fingerprint@example.com', (string) config('app.key')),
            $context['email_fingerprint'],
        );

        $this->assertStringNotContainsString(
            'Fingerprint@Example.com',
            $this->serializedSecurityRecords(),
        );
    }

    public function test_user_agent_is_sanitized_and_truncated(): void
    {
        $this->captureSecurityLog();

        $userAgent = "Bot\x00\x1F/1.0 ".str_repeat('x', 300);

        $this->withHeaders(['User-Agent' => $userAgent])
            ->postJson('/api/v1/auth/login', [
                'email' => 'ua@example.com',
                'password' => 'wrong passphrase value',
                'device_name' => 'Web',
            ])->assertStatus(401);

        $loggedAgent = $this->securityContexts()[0]['user_agent'];

        $this->assertLessThanOrEqual(255, strlen((string) $loggedAgent));
        $this->assertStringNotContainsString("\x00", (string) $loggedAgent);
    }

    public function test_recognizable_secrets_never_appear_in_security_logs(): void
    {
        Notification::fake();

        $passwordSecret = 'TEST_PASSWORD_DO_NOT_LOG';
        $emailSecret = 'secret-user@example.test';
        $newPasswordSecret = 'TEST_NEW_PASSWORD_DO_NOT_LOG';

        $this->captureSecurityLog();

        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Secret User',
            'email' => $emailSecret,
            'password' => $passwordSecret,
            'password_confirmation' => $passwordSecret,
            'device_name' => 'Safe Device',
        ])->assertCreated();

        $accessToken = $registerResponse->json('data.access_token');

        $this->postJson('/api/v1/auth/login', [
            'email' => $emailSecret,
            'password' => $passwordSecret,
            'device_name' => 'Safe Device',
        ])->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$accessToken])
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $emailSecret])->assertOk();

        $user = User::where('email', $emailSecret)->firstOrFail();
        $resetToken = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $emailSecret,
            'token' => $resetToken,
            'password' => $newPasswordSecret,
            'password_confirmation' => $newPasswordSecret,
        ])->assertOk();

        $logText = $this->serializedSecurityRecords();

        $this->assertStringNotContainsString($passwordSecret, $logText);
        $this->assertStringNotContainsString($newPasswordSecret, $logText);
        $this->assertStringNotContainsString($accessToken, $logText);
        $this->assertStringNotContainsString($resetToken, $logText);
        $this->assertStringNotContainsString($emailSecret, $logText);
    }

    private function emailFingerprint(string $email): string
    {
        return hash_hmac('sha256', strtolower(trim($email)), (string) config('app.key'));
    }
}
