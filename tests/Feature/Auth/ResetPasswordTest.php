<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Password;
use Modules\Auth\app\Models\User;
use Tests\AuthApiTestCase;

class ResetPasswordTest extends AuthApiTestCase
{
    private const RESET_URI = '/api/v1/auth/reset-password';

    private const NEW_PASSWORD = 'a freshly reset secure passphrase';

    public function test_valid_token_resets_password_and_returns_200(): void
    {
        Event::fake([PasswordResetEvent::class]);

        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => self::VALID_PASSWORD,
        ]);

        $token = Password::createToken($user);

        $this->postJson(self::RESET_URI, [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Password reset successfully.');

        Event::assertDispatched(PasswordResetEvent::class);
    }

    public function test_old_password_no_longer_works_and_new_password_works_after_reset(): void
    {
        $user = User::factory()->create([
            'email' => 'after@example.com',
            'password' => self::VALID_PASSWORD,
        ]);

        $token = Password::createToken($user);

        $this->postJson(self::RESET_URI, [
            'email' => 'after@example.com',
            'token' => $token,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'after@example.com',
            'password' => self::VALID_PASSWORD,
            'device_name' => 'Web',
        ])->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'after@example.com',
            'password' => self::NEW_PASSWORD,
            'device_name' => 'Web',
        ])->assertOk();
    }

    public function test_invalid_token_returns_generic_422(): void
    {
        $user = User::factory()->create(['email' => 'invalid@example.com']);

        $this->postJson(self::RESET_URI, [
            'email' => 'invalid@example.com',
            'token' => 'this-is-not-a-valid-token',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_expired_token_fails(): void
    {
        $user = User::factory()->create(['email' => 'expired@example.com']);

        $token = Password::createToken($user);

        DB::table('password_reset_tokens')
            ->where('email', 'expired@example.com')
            ->update(['created_at' => now()->subHours(3)]);

        $this->postJson(self::RESET_URI, [
            'email' => 'expired@example.com',
            'token' => $token,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertStatus(422);
    }

    public function test_used_token_cannot_be_reused(): void
    {
        $user = User::factory()->create(['email' => 'reuse@example.com']);

        $token = Password::createToken($user);

        $this->postJson(self::RESET_URI, [
            'email' => 'reuse@example.com',
            'token' => $token,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();

        $this->postJson(self::RESET_URI, [
            'email' => 'reuse@example.com',
            'token' => $token,
            'password' => 'another fresh secure passphrase',
            'password_confirmation' => 'another fresh secure passphrase',
        ])->assertStatus(422);
    }

    public function test_new_password_policy_and_confirmation_apply(): void
    {
        $user = User::factory()->create(['email' => 'policy@example.com']);

        $token = Password::createToken($user);

        $short = str_repeat('c', 14);
        $this->postJson(self::RESET_URI, [
            'email' => 'policy@example.com',
            'token' => $token,
            'password' => $short,
            'password_confirmation' => $short,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->postJson(self::RESET_URI, [
            'email' => 'policy@example.com',
            'token' => $token,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => 'mismatched confirmation value',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_all_tokens_are_revoked_no_new_token_issued_and_user_not_authenticated(): void
    {
        $user = User::factory()->create([
            'email' => 'revoke@example.com',
            'password' => self::VALID_PASSWORD,
        ]);

        $existingToken = $this->createTokenForUser($user, 'Old Device');

        $resetToken = Password::createToken($user);

        $response = $this->postJson(self::RESET_URI, [
            'email' => 'revoke@example.com',
            'token' => $resetToken,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();

        $this->assertArrayNotHasKey('access_token', $response->json());
        $this->assertArrayNotHasKey('data', $response->json());

        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());

        $this->withHeaders($this->bearer($existingToken))->getJson('/api/v1/auth/me')->assertUnauthorized();

        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
