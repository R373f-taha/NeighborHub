<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Modules\Auth\app\Models\User;
use Tests\AuthApiTestCase;

class ChangePasswordTest extends AuthApiTestCase
{
    private const PASSWORD_URI = '/api/v1/auth/password';

    private const NEW_PASSWORD = 'a brand new secure passphrase';

    public function test_correct_current_password_succeeds_and_keeps_current_token_valid(): void
    {
        $user = User::factory()->create(['password' => self::VALID_PASSWORD]);
        $tokenA = $this->createTokenForUser($user, 'Device A');
        $tokenB = $this->createTokenForUser($user, 'Device B');

        $this->withHeaders($this->bearer($tokenA))
            ->putJson(self::PASSWORD_URI, [
                'current_password' => self::VALID_PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertNoContent();

        $this->withHeaders($this->bearer($tokenA))->getJson('/api/v1/auth/me')->assertOk();
        $this->withHeaders($this->bearer($tokenB))->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_incorrect_current_password_returns_422_without_exposing_internals(): void
    {
        $user = User::factory()->create(['password' => self::VALID_PASSWORD]);
        $token = $this->createTokenForUser($user);

        $this->withHeaders($this->bearer($token))
            ->putJson(self::PASSWORD_URI, [
                'current_password' => 'wrong current passphrase',
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_confirmation_mismatch_fails(): void
    {
        $user = User::factory()->create(['password' => self::VALID_PASSWORD]);
        $token = $this->createTokenForUser($user);

        $this->withHeaders($this->bearer($token))
            ->putJson(self::PASSWORD_URI, [
                'current_password' => self::VALID_PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => 'mismatched confirmation value',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_minimum_and_maximum_policies_apply(): void
    {
        $user = User::factory()->create(['password' => self::VALID_PASSWORD]);
        $token = $this->createTokenForUser($user);

        $short = str_repeat('b', 14);
        $this->withHeaders($this->bearer($token))
            ->putJson(self::PASSWORD_URI, [
                'current_password' => self::VALID_PASSWORD,
                'password' => $short,
                'password_confirmation' => $short,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $long = str_repeat('b', 65);
        $this->withHeaders($this->bearer($token))
            ->putJson(self::PASSWORD_URI, [
                'current_password' => self::VALID_PASSWORD,
                'password' => $long,
                'password_confirmation' => $long,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_new_password_cannot_equal_current_password(): void
    {
        $user = User::factory()->create(['password' => self::VALID_PASSWORD]);
        $token = $this->createTokenForUser($user);

        $this->withHeaders($this->bearer($token))
            ->putJson(self::PASSWORD_URI, [
                'current_password' => self::VALID_PASSWORD,
                'password' => self::VALID_PASSWORD,
                'password_confirmation' => self::VALID_PASSWORD,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_old_password_no_longer_works_and_new_password_works(): void
    {
        $user = User::factory()->create([
            'email' => 'cp@example.com',
            'password' => self::VALID_PASSWORD,
        ]);

        $this->withHeaders($this->bearer($this->createTokenForUser($user)))
            ->putJson(self::PASSWORD_URI, [
                'current_password' => self::VALID_PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertNoContent();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'cp@example.com',
            'password' => self::VALID_PASSWORD,
            'device_name' => 'Web',
        ])->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'cp@example.com',
            'password' => self::NEW_PASSWORD,
            'device_name' => 'Web',
        ])->assertOk();
    }

    public function test_inactive_user_is_blocked(): void
    {
        $user = User::factory()->create(['password' => self::VALID_PASSWORD, 'is_active' => false]);

        $this->withHeaders($this->bearer($this->createTokenForUser($user)))
            ->putJson(self::PASSWORD_URI, [
                'current_password' => self::VALID_PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertForbidden();
    }

    public function test_unauthenticated_request_receives_401(): void
    {
        $this->putJson(self::PASSWORD_URI, [
            'current_password' => self::VALID_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertUnauthorized();
    }
}
