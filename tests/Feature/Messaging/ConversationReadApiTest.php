<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Modules\Auth\app\Enums\UserRole;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Messaging\app\Models\Conversation;
use Tests\TestCase;

/**
 * Core Conversation READ (LIST) API: routing, participant-based privacy,
 * IDOR, community scoping, deterministic ordering, pagination and resource
 * contract. No global role bypass; non-participants get a privacy-safe 404.
 *
 * LOCAL ONLY / NOT staged.
 */
class ConversationReadApiTest extends TestCase
{
    use RefreshDatabase;
    use ConversationTestHelpers;

    private Community $communityA;
    private Community $communityB;
    private User $participantX;
    private User $nonParticipant;
    private User $leftParticipant;
    private User $superAdmin;
    private User $manager;
    private User $roleDrift;
    private Conversation $conversationInA;
    private Conversation $conversationInB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->communityA = $this->makeCommunity('A');
        $this->communityB = $this->makeCommunity('B');

        $base = Carbon::create(2026, 1, 1, 12, 0, 0);

        // Conversation in A with an active + a left participant.
        $this->conversationInA = $this->makeConversation($this->communityA, $base);

        $this->participantX = User::factory()->resident()->create(['is_active' => true]);
        $this->makeParticipant($this->conversationInA, $this->participantX, $base);

        $this->leftParticipant = User::factory()->resident()->create(['is_active' => true]);
        $this->makeParticipant($this->conversationInA, $this->leftParticipant, $base, $base->copy()->addSecond());

        // Conversation living in B (cross-community probe).
        $this->conversationInB = $this->makeConversation($this->communityB, $base);

        // A distinct participant of B so B's conversation is real.
        $this->nonParticipant = User::factory()->resident()->create(['is_active' => true]);
        $this->makeParticipant($this->conversationInB, $this->nonParticipant, $base);

        // Global-role users who are NOT participants of any conversation.
        $this->superAdmin = User::factory()->superAdmin()->create(['is_active' => true]);
        $this->manager = User::factory()->manager()->create(['is_active' => true]);

        // Legacy users.role drift: super_admin in the enum but no Spatie role
        // and no participant row. Created directly (bypasses factory role sync).
        $this->roleDrift = User::create([
            'name' => 'Role Drift',
            'email' => 'role.drift@example.test',
            'password' => 'password',
            'role' => UserRole::SuperAdmin,
            'phone' => '123',
            'avatar' => 'x',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    // ── Routing ──

    public function test_legacy_messagings_api_scaffold_is_no_longer_registered(): void
    {
        $this->assertFalse(Route::has('api.messaging.index'));
        $this->assertFalse(Route::has('api.messaging.show'));
    }

    public function test_core_conversation_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('api.v1.communities.conversations.index'));
        $this->assertTrue(Route::has('api.v1.communities.conversations.messages.index'));
        $this->assertTrue(Route::has('api.v1.communities.conversations.messages.store'));
        $this->assertTrue(Route::has('api.v1.communities.conversations.read.update'));
        // Removed-scope routes must NOT be registered.
        $this->assertFalse(Route::has('api.v1.communities.conversations.show'));
        $this->assertFalse(Route::has('api.v1.communities.conversations.direct.store'));
        $this->assertFalse(Route::has('api.v1.communities.conversations.group.store'));
        $this->assertFalse(Route::has('api.v1.communities.conversations.participants.store'));
        $this->assertFalse(Route::has('api.v1.communities.conversations.leave'));
    }

    // ── Auth ──

    public function test_anonymous_index_is_unauthenticated(): void
    {
        $this->getJson($this->indexUri($this->communityA))->assertStatus(401);
    }

    public function test_anonymous_messages_is_unauthenticated(): void
    {
        $this->getJson($this->messagesUri($this->communityA, $this->conversationInA))->assertStatus(401);
    }

    // ── LIST ──

    public function test_active_participant_sees_their_conversation_in_list(): void
    {
        $this->getJson($this->indexUri($this->communityA), $this->token($this->participantX))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Conversations retrieved successfully.')
            ->assertJsonStructure(['message', 'data', 'links', 'meta'])
            ->assertJsonPath('data.0.id', $this->conversationInA->id);
    }

    public function test_non_participant_list_does_not_include_foreign_conversation(): void
    {
        // nonParticipant is only in conversationInB; listing A yields nothing.
        $this->getJson($this->indexUri($this->communityA), $this->token($this->nonParticipant))
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_left_participant_conversation_is_omitted_from_list(): void
    {
        $this->getJson($this->indexUri($this->communityA), $this->token($this->leftParticipant))
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_conversation_from_another_community_is_omitted_from_list(): void
    {
        // participantX is in conversationInA (community A). Listing B yields nothing.
        $this->getJson($this->indexUri($this->communityB), $this->token($this->participantX))
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_manager_non_participant_gains_nothing_in_list(): void
    {
        $this->getJson($this->indexUri($this->communityA), $this->token($this->manager))
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_super_admin_non_participant_gains_nothing_in_list(): void
    {
        $this->getJson($this->indexUri($this->communityA), $this->token($this->superAdmin))
            ->assertStatus(200)
            ->assertJsonPath('data', []);
    }

    public function test_list_is_deterministically_ordered_newest_first(): void
    {
        $base = Carbon::create(2026, 1, 1, 12, 0, 0);
        $older = $this->makeConversation($this->communityA, $base);
        $newer = $this->makeConversation($this->communityA, $base->copy()->addMinutes(5));

        $this->makeParticipant($older, $this->participantX, $base);
        $this->makeParticipant($newer, $this->participantX, $base);

        $this->getJson($this->indexUri($this->communityA), $this->token($this->participantX))
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_list_pagination_caps_per_page(): void
    {
        $base = Carbon::create(2026, 1, 1, 12, 0, 0);
        for ($i = 0; $i < 120; $i++) {
            $conv = $this->makeConversation($this->communityA, $base->copy()->addSeconds($i));
            $this->makeParticipant($conv, $this->participantX, $base);
        }

        // Requesting 500 must be capped at 100.
        $this->getJson($this->indexUri($this->communityA).'?per_page=500', $this->token($this->participantX))
            ->assertStatus(200)
            ->assertJsonPath('meta.per_page', 100);
    }

    // ── Resource contract (via LIST) ──

    public function test_conversation_resource_exposes_only_safe_fields(): void
    {
        $response = $this->getJson($this->indexUri($this->communityA), $this->token($this->participantX))
            ->assertStatus(200);

        $data = $response->json('data.0');

        // Allowed scalar fields.
        foreach (['id', 'community_id', 'type', 'status', 'created_at', 'updated_at', 'unread_count'] as $field) {
            $this->assertArrayHasKey($field, $data, "Expected field {$field}.");
        }

        // Sensitive / internal fields must NOT be present at the conversation level.
        foreach (['is_read', 'read_at', 'last_read_message_id', 'created_by', 'creator'] as $field) {
            $this->assertArrayNotHasKey($field, $data);
        }

        // Participants expose only id + name.
        $this->assertArrayHasKey('participants', $data);
        // Active participant only (the left one must not appear).
        $participantIds = array_column($data['participants'], 'id');
        $this->assertContains($this->participantX->id, $participantIds);
        $this->assertNotContains($this->leftParticipant->id, $participantIds);

        foreach ($data['participants'] as $p) {
            $this->assertSame(['id', 'name'], array_keys($p));
            $this->assertArrayNotHasKey('email', $p);
            $this->assertArrayNotHasKey('phone', $p);
        }
    }
}
