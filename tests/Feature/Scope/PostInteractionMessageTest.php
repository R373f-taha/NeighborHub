<?php

declare(strict_types=1);

namespace Tests\Feature\Scope;

use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\CommunityManager;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Modules\Interaction\app\Enums\ReactionType;
use Modules\Interaction\app\Models\Comment;
use Modules\Post\app\Models\Post;

/**
 * Validates Posts / Comments / Reactions: message contract (incl. 200 delete),
 * Spatie-based moderator identity, and preserved residency/ownership/IDOR rules.
 *
 * NOT committed / NOT staged — local validation only.
 */
class PostInteractionMessageTest extends ScopeTestCase
{
    private Community $community;
    private Community $otherCommunity;
    private Resident $activeResident;
    private User $residentUser;
    private User $managerUser;
    private User $superAdminUser;
    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->community = Community::create([
            'name' => 'Scope Community', 'city' => 'City', 'address' => 'Addr',
            'total_units' => 10, 'is_active' => true,
        ]);
        $this->otherCommunity = Community::create([
            'name' => 'Other', 'city' => 'C2', 'address' => 'A2',
            'total_units' => 5, 'is_active' => true,
        ]);
        $unit = Unit::create([
            'community_id' => $this->community->id, 'unit_number' => 'U1',
            'building_name' => 'B1', 'rooms' => 2, 'unit_type' => 'apartment', 'is_active' => true,
        ]);

        $this->residentUser = User::factory()->resident()->create(['is_active' => true]);
        $this->activeResident = Resident::create([
            'user_id' => $this->residentUser->id, 'unit_id' => $unit->id,
            'community_id' => $this->community->id, 'residence_type' => 'owner',
            'status' => 'active', 'current_marker' => true,
        ]);

        $this->managerUser = User::factory()->manager()->create(['is_active' => true]);
        CommunityManager::create(['community_id' => $this->community->id, 'manager_id' => $this->managerUser->id]);

        $this->superAdminUser = User::factory()->superAdmin()->create(['is_active' => true]);

        $this->post = Post::create([
            'community_id' => $this->community->id, 'resident_id' => $this->activeResident->id,
            'category' => 'general', 'content' => 'A post.',
        ]);
    }

    private function postUri(): string
    {
        return "/api/v1/communities/{$this->community->id}";
    }

    // ── Posts message contract ──

    public function test_list_posts_message(): void
    {
        $this->getJson("{$this->postUri()}/posts", $this->token($this->residentUser))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Posts retrieved successfully.')
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_create_post_message_and_data(): void
    {
        $this->postJson("{$this->postUri()}/posts", ['category' => 'general', 'content' => 'Hi.'], $this->token($this->residentUser))
            ->assertStatus(201)
            ->assertJsonPath('message', 'Post created successfully.')
            ->assertJsonPath('data.content', 'Hi.');
    }

    public function test_show_post_message(): void
    {
        $this->getJson("{$this->postUri()}/posts/{$this->post->id}", $this->token($this->residentUser))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Post retrieved successfully.');
    }

    public function test_update_post_message(): void
    {
        $this->patchJson("{$this->postUri()}/posts/{$this->post->id}", ['content' => 'Edited.'], $this->token($this->residentUser))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Post updated successfully.')
            ->assertJsonPath('data.content', 'Edited.');
    }

    public function test_owner_delete_post_returns_200_with_message(): void
    {
        $this->deleteJson("{$this->postUri()}/posts/{$this->post->id}", [], $this->token($this->residentUser))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Post deleted successfully.')
            ->assertJsonPath('data', null);
    }

    // ── Residency / ownership / moderation preserved ──

    public function test_manager_without_residency_cannot_create_post(): void
    {
        $this->postJson("{$this->postUri()}/posts", ['category' => 'general', 'content' => 'No.'], $this->token($this->managerUser))
            ->assertStatus(403);
    }

    public function test_super_admin_without_residency_cannot_create_post(): void
    {
        $this->postJson("{$this->postUri()}/posts", ['category' => 'general', 'content' => 'No.'], $this->token($this->superAdminUser))
            ->assertStatus(403);
    }

    public function test_manager_cannot_edit_others_post(): void
    {
        $this->patchJson("{$this->postUri()}/posts/{$this->post->id}", ['content' => 'Hijack.'], $this->token($this->managerUser))
            ->assertStatus(403);
    }

    public function test_manager_moderation_delete_uses_spatie_role(): void
    {
        $this->deleteJson("{$this->postUri()}/posts/{$this->post->id}", [], $this->token($this->managerUser))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Post deleted successfully.');
    }

    public function test_super_admin_moderation_delete_uses_spatie_role(): void
    {
        $this->deleteJson("{$this->postUri()}/posts/{$this->post->id}", [], $this->token($this->superAdminUser))
            ->assertStatus(200);
    }

    public function test_cross_community_post_returns_404(): void
    {
        $other = Post::create([
            'community_id' => $this->otherCommunity->id, 'resident_id' => $this->activeResident->id,
            'category' => 'general', 'content' => 'Other.',
        ]);
        $this->getJson("{$this->postUri()}/posts/{$other->id}", $this->token($this->residentUser))
            ->assertStatus(404);
    }

    // ── Comments message contract + rules ──

    public function test_create_comment_message(): void
    {
        $this->postJson("{$this->postUri()}/posts/{$this->post->id}/comments", ['content' => 'Nice.'], $this->token($this->residentUser))
            ->assertStatus(201)
            ->assertJsonPath('message', 'Comment created successfully.');
    }

    public function test_list_comments_message(): void
    {
        $this->post->comments()->create(['author_id' => $this->residentUser->id, 'content' => 'C.']);
        $this->getJson("{$this->postUri()}/posts/{$this->post->id}/comments", $this->token($this->residentUser))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Comments retrieved successfully.');
    }

    public function test_update_comment_message_and_ownership(): void
    {
        $comment = $this->post->comments()->create(['author_id' => $this->residentUser->id, 'content' => 'Orig.']);
        $this->patchJson("{$this->postUri()}/posts/{$this->post->id}/comments/{$comment->id}", ['content' => 'New.'], $this->token($this->residentUser))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Comment updated successfully.')
            ->assertJsonPath('data.content', 'New.');
    }

    public function test_owner_delete_comment_returns_200_with_message(): void
    {
        $comment = $this->post->comments()->create(['author_id' => $this->residentUser->id, 'content' => 'Bye.']);
        $this->deleteJson("{$this->postUri()}/posts/{$this->post->id}/comments/{$comment->id}", [], $this->token($this->residentUser))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Comment deleted successfully.')
            ->assertJsonPath('data', null);
    }

    public function test_manager_without_residency_cannot_comment(): void
    {
        $this->postJson("{$this->postUri()}/posts/{$this->post->id}/comments", ['content' => 'X.'], $this->token($this->managerUser))
            ->assertStatus(403);
    }

    // ── Reactions message contract + residency ──

    public function test_reaction_created_message(): void
    {
        $this->postJson("{$this->postUri()}/posts/{$this->post->id}/react", ['type' => 'like'], $this->token($this->residentUser))
            ->assertStatus(201)
            ->assertJsonPath('message', 'Reaction added successfully.')
            ->assertJsonPath('data.action', 'created');
    }

    public function test_reaction_updated_message(): void
    {
        $reaction = new \Modules\Interaction\app\Models\Reaction(['type' => 'like']);
        $reaction->user()->associate($this->residentUser);
        $this->post->reactions()->save($reaction);

        $this->postJson("{$this->postUri()}/posts/{$this->post->id}/react", ['type' => 'love'], $this->token($this->residentUser))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Reaction updated successfully.')
            ->assertJsonPath('data.action', 'updated');
    }

    public function test_reaction_removed_message(): void
    {
        $reaction = new \Modules\Interaction\app\Models\Reaction(['type' => 'like']);
        $reaction->user()->associate($this->residentUser);
        $this->post->reactions()->save($reaction);

        $this->postJson("{$this->postUri()}/posts/{$this->post->id}/react", ['type' => 'like'], $this->token($this->residentUser))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Reaction removed successfully.')
            ->assertJsonPath('data.action', 'removed');
    }

    public function test_manager_without_residency_cannot_react(): void
    {
        $this->postJson("{$this->postUri()}/posts/{$this->post->id}/react", ['type' => 'like'], $this->token($this->managerUser))
            ->assertStatus(403);
    }

    public function test_super_admin_without_residency_cannot_react(): void
    {
        $this->postJson("{$this->postUri()}/posts/{$this->post->id}/react", ['type' => 'like'], $this->token($this->superAdminUser))
            ->assertStatus(403);
    }
}
