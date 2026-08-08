<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Modules\Auth\app\Enums\UserRole;
use Modules\Auth\app\Models\User;
use Tests\AuthApiTestCase;

class MeTest extends AuthApiTestCase
{
    private const ME_URI = '/api/v1/auth/me';

    public function test_authenticated_active_user_receives_correct_identity_fields(): void
    {
        $user = User::factory()->create([
            'email' => 'me@example.com',
            'phone' => '+123456789',
            'role' => UserRole::Manager,
        ]);

        $token = $this->createTokenForUser($user);

        $response = $this->withHeaders($this->bearer($token))->getJson(self::ME_URI);

        $response->assertOk();
        $response->assertJsonPath('data.id', $user->id);
        $response->assertJsonPath('data.name', $user->name);
        $response->assertJsonPath('data.email', 'me@example.com');
        $response->assertJsonPath('data.phone', '+123456789');
        $response->assertJsonPath('data.role', 'manager');
        $response->assertJsonPath('data.is_active', true);
    }

    public function test_role_is_serialized_as_a_string_value(): void
    {
        $user = User::factory()->create(['role' => UserRole::Resident]);

        $response = $this->withHeaders($this->bearer($this->createTokenForUser($user)))
            ->getJson(self::ME_URI)
            ->assertOk();

        $this->assertSame('resident', $response->json('data.role'));
    }

    public function test_password_tokens_and_domain_relationships_are_absent(): void
    {
        $user = User::factory()->create();

        $response = $this->withHeaders($this->bearer($this->createTokenForUser($user)))
            ->getJson(self::ME_URI)
            ->assertOk();

        $data = $response->json('data');

        $allowed = ['id', 'name', 'email', 'phone', 'avatar', 'role', 'is_active', 'email_verified_at', 'created_at'];

        foreach ($allowed as $field) {
            $this->assertArrayHasKey($field, $data);
        }

        $forbidden = ['password', 'remember_token', 'tokens', 'current_access_token', 'personal_access_tokens'];
        $forbidden = array_merge($forbidden, [
            'resident', 'current_resident', 'currentResident', 'managed_communities', 'managedCommunities',
            'reported_issues', 'reportedIssues', 'assigned_issues', 'assignedIssues', 'messages', 'sent_messages',
            'notifications', 'user_notifications', 'userNotifications', 'media', 'uploaded_media', 'uploadedMedia',
            'comments', 'authored_comments', 'authoredComments', 'reactions', 'polls', 'created_polls', 'createdPolls',
            'announcements', 'unit_id', 'community_id',
        ]);

        foreach ($forbidden as $field) {
            $this->assertArrayNotHasKey($field, $data, "Unexpected field [{$field}] exposed by /me.");
        }

        $this->assertSame($allowed, array_keys($data));
    }

    public function test_unauthenticated_request_receives_401(): void
    {
        $this->getJson(self::ME_URI)->assertUnauthorized();
    }

    public function test_inactive_authenticated_user_receives_403(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->withHeaders($this->bearer($this->createTokenForUser($user)))
            ->getJson(self::ME_URI)
            ->assertForbidden()
            ->assertJsonPath('message', 'This account is inactive.');
    }
}
