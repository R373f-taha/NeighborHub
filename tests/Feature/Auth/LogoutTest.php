<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\DB;
use Modules\Auth\app\Models\User;
use Tests\AuthApiTestCase;

class LogoutTest extends AuthApiTestCase
{
    private const LOGOUT_URI = '/api/v1/auth/logout';

    public function test_authenticated_logout_returns_204_and_deletes_current_token(): void
    {
        $user = User::factory()->create();
        $tokenA = $this->createTokenForUser($user, 'Device A');
        $tokenB = $this->createTokenForUser($user, 'Device B');

        $this->withHeaders($this->bearer($tokenA))
            ->postJson(self::LOGOUT_URI)
            ->assertNoContent();

        $this->assertSame(1, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());

        $this->withHeaders($this->bearer($tokenA))
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->withHeaders($this->bearer($tokenB))
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_unauthenticated_logout_receives_401(): void
    {
        $this->postJson(self::LOGOUT_URI)->assertUnauthorized();
    }

    public function test_inactive_authenticated_user_can_still_revoke_the_current_token(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $token = $this->createTokenForUser($user, 'Inactive Device');

        $this->withHeaders($this->bearer($token))
            ->postJson(self::LOGOUT_URI)
            ->assertNoContent();

        $this->withHeaders($this->bearer($token))
            ->postJson(self::LOGOUT_URI)
            ->assertUnauthorized();
    }
}
