<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Modules\Auth\app\Models\User;
use Tests\AuthApiTestCase;

class ForgotPasswordTest extends AuthApiTestCase
{
    private const FORGOT_URI = '/api/v1/auth/forgot-password';

    private const GENERIC_MESSAGE = 'If an account exists for this email, a password reset link has been sent.';

    public function test_existing_email_returns_generic_200_and_sends_reset_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'exists@example.com']);

        $response = $this->postJson(self::FORGOT_URI, ['email' => 'exists@example.com']);

        $response->assertOk();
        $response->assertJsonPath('message', self::GENERIC_MESSAGE);
        $this->assertArrayNotHasKey('token', $response->json());
        $this->assertArrayNotHasKey('data', $response->json());

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_missing_email_returns_the_identical_generic_response_and_sends_nothing(): void
    {
        Notification::fake();

        $response = $this->postJson(self::FORGOT_URI, ['email' => 'ghost@example.com']);

        $response->assertOk();
        $response->assertJsonPath('message', self::GENERIC_MESSAGE);
        $this->assertArrayNotHasKey('token', $response->json());

        Notification::assertNothingSent();
    }

    public function test_email_is_normalized_before_lookup(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'norm@example.com']);

        $this->postJson(self::FORGOT_URI, ['email' => '  NORM@Example.COM  '])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_rate_limiting_blocks_repeated_attempts_for_the_same_email(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'ratelimited@example.com']);

        $payload = ['email' => 'ratelimited@example.com'];

        for ($i = 0; $i < 3; $i++) {
            $this->postJson(self::FORGOT_URI, $payload)->assertOk();
        }

        $this->postJson(self::FORGOT_URI, $payload)->assertStatus(429);
    }
}
