<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\app\Models\User;
use Tests\AuthApiTestCase;

class LoginTest extends AuthApiTestCase
{
    private const LOGIN_URI = '/api/v1/auth/login';

    public function test_valid_login_returns_200_and_issues_token_with_device_name(): void
    {
        User::factory()->create([
            'email' => 'ali@example.com',
            'password' => self::VALID_PASSWORD,
        ]);

        $response = $this->postJson(self::LOGIN_URI, [
            'email' => 'ali@example.com',
            'password' => self::VALID_PASSWORD,
            'device_name' => 'Ali iPhone',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Login successful.');
        $response->assertJsonStructure(['data' => ['user', 'access_token', 'token_type', 'expires_at']]);

        $this->assertNotEmpty($response->json('data.access_token'));
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'Ali iPhone']);
    }

    public function test_login_normalizes_email_before_lookup(): void
    {
        User::factory()->create([
            'email' => 'norm@example.com',
            'password' => self::VALID_PASSWORD,
        ]);

        $this->postJson(self::LOGIN_URI, [
            'email' => '  NORM@Example.COM  ',
            'password' => self::VALID_PASSWORD,
            'device_name' => 'Web',
        ])->assertOk();
    }

    public function test_wrong_password_returns_generic_401(): void
    {
        User::factory()->create([
            'email' => 'wp@example.com',
            'password' => self::VALID_PASSWORD,
        ]);

        $response = $this->postJson(self::LOGIN_URI, [
            'email' => 'wp@example.com',
            'password' => 'totally wrong passphrase',
            'device_name' => 'Web',
        ]);

        $this->assertSameGenericFailure($response);
    }

    public function test_missing_user_returns_the_same_generic_401(): void
    {
        $response = $this->postJson(self::LOGIN_URI, [
            'email' => 'ghost@example.com',
            'password' => self::VALID_PASSWORD,
            'device_name' => 'Web',
        ]);

        $this->assertSameGenericFailure($response);
    }

    public function test_inactive_user_returns_the_same_generic_401(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => self::VALID_PASSWORD,
            'is_active' => false,
        ]);

        $response = $this->postJson(self::LOGIN_URI, [
            'email' => 'inactive@example.com',
            'password' => self::VALID_PASSWORD,
            'device_name' => 'Web',
        ]);

        $this->assertSameGenericFailure($response);
    }

    public function test_password_is_never_returned(): void
    {
        User::factory()->create([
            'email' => 'np@example.com',
            'password' => self::VALID_PASSWORD,
        ]);

        $response = $this->postJson(self::LOGIN_URI, [
            'email' => 'np@example.com',
            'password' => self::VALID_PASSWORD,
            'device_name' => 'Web',
        ])->assertOk();

        $this->assertArrayNotHasKey('password', $response->json('data.user'));
    }

    public function test_password_is_rehashed_when_it_needs_rehashing(): void
    {
        $user = User::factory()->create([
            'email' => 'rehash@example.com',
            'password' => self::VALID_PASSWORD,
        ]);

        // Store a hash at a different bcrypt cost so needsRehash() is true.
        $costFive = password_hash(self::VALID_PASSWORD, PASSWORD_BCRYPT, ['cost' => 5]);
        DB::table('users')->where('id', $user->id)->update(['password' => $costFive]);

        $this->postJson(self::LOGIN_URI, [
            'email' => 'rehash@example.com',
            'password' => self::VALID_PASSWORD,
            'device_name' => 'Web',
        ])->assertOk();

        $rehashed = DB::table('users')->where('id', $user->id)->value('password');

        $this->assertTrue(Hash::check(self::VALID_PASSWORD, $rehashed));
        $this->assertTrue(str_starts_with((string) $rehashed, '$2y$04$'));
    }

    public function test_rate_limiter_blocks_repeated_attempts(): void
    {
        User::factory()->create([
            'email' => 'limited@example.com',
            'password' => self::VALID_PASSWORD,
        ]);

        $payload = [
            'email' => 'limited@example.com',
            'password' => 'wrong passphrase value',
            'device_name' => 'Web',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(self::LOGIN_URI, $payload)->assertStatus(401);
        }

        $this->postJson(self::LOGIN_URI, $payload)->assertStatus(429);
    }

    private function assertSameGenericFailure($response): void
    {
        $response->assertStatus(401);
        $this->assertSame('The provided credentials are invalid.', $response->json('message'));
        $this->assertArrayNotHasKey('errors', $response->json());
    }
}
