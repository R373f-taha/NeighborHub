<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\app\Enums\UserRole;
use Modules\Auth\app\Models\User;
use Tests\AuthApiTestCase;

class RegisterTest extends AuthApiTestCase
{
    private const REGISTER_URI = '/api/v1/auth/register';

    public function test_successful_registration_returns_201_and_persists_user(): void
    {
        Event::fake([Registered::class]);

        $response = $this->postJson(self::REGISTER_URI, [
            'name' => 'Ali',
            'email' => 'Ali@Example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
            'device_name' => 'Ali iPhone',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('message', 'Account registered successfully.');
        $response->assertJsonPath('data.user.name', 'Ali');
        $response->assertJsonPath('data.user.email', 'ali@example.com');
        $response->assertJsonPath('data.token_type', 'Bearer');
        $response->assertJsonStructure([
            'data' => ['user', 'access_token', 'token_type', 'expires_at'],
        ]);

        $this->assertDatabaseHas('users', ['email' => 'ali@example.com']);

        Event::assertDispatched(Registered::class);
    }

    public function test_email_is_normalized_before_persistence(): void
    {
        $this->postJson(self::REGISTER_URI, [
            'name' => 'Norm User',
            'email' => '  Norm.User@Example.COM  ',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
            'device_name' => 'Web',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'norm.user@example.com']);
    }

    public function test_role_is_always_resident_and_is_active_is_server_controlled(): void
    {
        $this->postJson(self::REGISTER_URI, [
            'name' => 'Resident User',
            'email' => 'resident@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
            'device_name' => 'Web',
        ])->assertCreated();

        $user = User::where('email', 'resident@example.com')->firstOrFail();

        $this->assertSame(UserRole::Resident, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_submitted_privileged_fields_are_ignored_safely(): void
    {
        $this->postJson(self::REGISTER_URI, [
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
            'device_name' => 'Web',
            'role' => 'super_admin',
            'is_active' => false,
        ])->assertCreated();

        $user = User::where('email', 'attacker@example.com')->firstOrFail();

        $this->assertSame(UserRole::Resident, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_duplicate_normalized_email_fails(): void
    {
        $payload = [
            'name' => 'Dup User',
            'email' => 'dup@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
            'device_name' => 'Web',
        ];

        $this->postJson(self::REGISTER_URI, $payload)->assertCreated();
        $this->postJson(self::REGISTER_URI, $payload)->assertStatus(422);
        $this->postJson(self::REGISTER_URI, [
            'name' => 'Dup Case',
            'email' => 'DUP@Example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
            'device_name' => 'Web',
        ])->assertStatus(422);
    }

    public function test_password_confirmation_is_required(): void
    {
        $this->postJson(self::REGISTER_URI, [
            'name' => 'No Confirm',
            'email' => 'noconfirm@example.com',
            'password' => self::VALID_PASSWORD,
            'device_name' => 'Web',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_password_minimum_length_is_enforced(): void
    {
        $short = str_repeat('a', 14);

        $this->postJson(self::REGISTER_URI, [
            'name' => 'Short Pw',
            'email' => 'shortpw@example.com',
            'password' => $short,
            'password_confirmation' => $short,
            'device_name' => 'Web',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_password_maximum_length_is_enforced(): void
    {
        $long = str_repeat('a', 65);

        $this->postJson(self::REGISTER_URI, [
            'name' => 'Long Pw',
            'email' => 'longpw@example.com',
            'password' => $long,
            'password_confirmation' => $long,
            'device_name' => 'Web',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_password_is_hashed_exactly_once(): void
    {
        $this->postJson(self::REGISTER_URI, [
            'name' => 'Hash Check',
            'email' => 'hash@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
            'device_name' => 'Web',
        ])->assertCreated();

        $stored = DB::table('users')->where('email', 'hash@example.com')->value('password');

        $this->assertNotSame(self::VALID_PASSWORD, $stored);
        $this->assertTrue(Hash::check(self::VALID_PASSWORD, $stored));
    }

    public function test_sanctum_token_is_returned_and_stored_hashed_with_expiration_and_device_name(): void
    {
        $response = $this->postJson(self::REGISTER_URI, [
            'name' => 'Token User',
            'email' => 'token@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
            'device_name' => 'Ali iPhone',
        ])->assertCreated();

        $plainToken = $response->json('data.access_token');
        $this->assertNotEmpty($plainToken);

        $tokenRow = DB::table('personal_access_tokens')->where('name', 'Ali iPhone')->first();

        $this->assertNotNull($tokenRow);
        $this->assertNotSame($plainToken, $tokenRow->token);

        [, $randomPart] = explode('|', $plainToken, 2);
        $this->assertTrue(hash_equals($tokenRow->token, hash('sha256', $randomPart)));
        $this->assertNotNull($tokenRow->expires_at);
        $this->assertSame('["*"]', $tokenRow->abilities);
        $this->assertSame('Ali iPhone', $tokenRow->name);
    }

    public function test_response_does_not_expose_password(): void
    {
        $response = $this->postJson(self::REGISTER_URI, [
            'name' => 'Hidden Pw',
            'email' => 'hidden@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
            'device_name' => 'Web',
        ])->assertCreated();

        $this->assertArrayNotHasKey('password', $response->json('data.user'));
    }

    public function test_no_resident_or_membership_rows_are_created(): void
    {
        $this->postJson(self::REGISTER_URI, [
            'name' => 'No Side Effects',
            'email' => 'noside@example.com',
            'password' => self::VALID_PASSWORD,
            'password_confirmation' => self::VALID_PASSWORD,
            'device_name' => 'Web',
        ])->assertCreated();

        $user = User::where('email', 'noside@example.com')->firstOrFail();

        $this->assertDatabaseMissing('residents', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('community_mangers', ['manager_id' => $user->id]);
    }
}
