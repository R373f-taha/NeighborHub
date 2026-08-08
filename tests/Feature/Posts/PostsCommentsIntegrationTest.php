<?php

declare(strict_types=1);

namespace Tests\Feature\Posts;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Auth\app\Enums\UserRole;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\CommunityManager;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Modules\Interaction\app\Models\Comment;
use Modules\Post\app\Models\Post;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\TestCase;

class PostsCommentsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if ($this->app && $this->app->bound('auth')) {
            $this->app['auth']->forgetGuards();
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    private Community $community;
    private Community $otherCommunity;
    private User $activeResidentUser;
    private User $suspendedUser;
    private User $pendingUser;
    private User $rejectedUser;
    private User $nonMemberUser;
    private User $managerUser;
    private User $unrelatedManagerUser;
    private User $superAdminUser;
    private Resident $activeResident;
    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $securityLogger = Log::channel('security')->getLogger();
        if ($securityLogger instanceof Logger) {
            $securityLogger->setHandlers([new \Monolog\Handler\NullHandler()]);
        }

        DB::table('personal_access_tokens')->delete();
        DB::table('comments')->delete();
        DB::table('posts')->delete();
        DB::table('community_mangers')->delete();
        DB::table('residents')->delete();
        DB::table('units')->delete();
        DB::table('communities')->delete();
        DB::table('users')->delete();

        $this->community = Community::create([
            'name' => 'Test Community',
            'city' => 'Test City',
            'address' => '123 Test St',
            'total_units' => 10,
            'is_active' => true,
        ]);

        $this->otherCommunity = Community::create([
            'name' => 'Other Community',
            'city' => 'Other City',
            'address' => '456 Other St',
            'total_units' => 5,
            'is_active' => true,
        ]);

        $unit = Unit::create([
            'community_id' => $this->community->id,
            'unit_number' => 'A101',
            'building_name' => 'Block A',
            'rooms' => 3,
            'unit_type' => 'apartment',
            'is_active' => true,
        ]);

        $this->activeResidentUser = User::factory()->resident()->create(['is_active' => true]);
        $this->activeResident = Resident::create([
            'user_id' => $this->activeResidentUser->id,
            'unit_id' => $unit->id,
            'community_id' => $this->community->id,
            'residence_type' => 'owner',
            'status' => 'active',
            'current_marker' => true,
        ]);

        $unit2 = Unit::create([
            'community_id' => $this->community->id,
            'unit_number' => 'A102',
            'building_name' => 'Block A',
            'rooms' => 2,
            'unit_type' => 'apartment',
            'is_active' => true,
        ]);
        $this->suspendedUser = User::factory()->resident()->create(['is_active' => true]);
        Resident::create([
            'user_id' => $this->suspendedUser->id,
            'unit_id' => $unit2->id,
            'community_id' => $this->community->id,
            'residence_type' => 'tenant',
            'status' => 'suspended',
        ]);

        $unit3 = Unit::create([
            'community_id' => $this->community->id,
            'unit_number' => 'A103',
            'building_name' => 'Block A',
            'rooms' => 1,
            'unit_type' => 'apartment',
            'is_active' => true,
        ]);
        $this->pendingUser = User::factory()->resident()->create(['is_active' => true]);
        Resident::create([
            'user_id' => $this->pendingUser->id,
            'unit_id' => $unit3->id,
            'community_id' => $this->community->id,
            'residence_type' => 'tenant',
            'status' => 'pending',
        ]);

        $unit4 = Unit::create([
            'community_id' => $this->community->id,
            'unit_number' => 'A104',
            'building_name' => 'Block A',
            'rooms' => 1,
            'unit_type' => 'apartment',
            'is_active' => true,
        ]);
        $this->rejectedUser = User::factory()->resident()->create(['is_active' => true]);
        Resident::create([
            'user_id' => $this->rejectedUser->id,
            'unit_id' => $unit4->id,
            'community_id' => $this->community->id,
            'residence_type' => 'owner',
            'status' => 'rejected',
        ]);

        $this->nonMemberUser = User::factory()->resident()->create(['is_active' => true]);

        $this->managerUser = User::factory()->manager()->create(['is_active' => true]);
        CommunityManager::create([
            'community_id' => $this->community->id,
            'manager_id' => $this->managerUser->id,
        ]);

        $this->unrelatedManagerUser = User::factory()->manager()->create(['is_active' => true]);
        CommunityManager::create([
            'community_id' => $this->otherCommunity->id,
            'manager_id' => $this->unrelatedManagerUser->id,
        ]);

        $this->superAdminUser = User::factory()->superAdmin()->create(['is_active' => true]);

        $this->post = Post::create([
            'community_id' => $this->community->id,
            'resident_id' => $this->activeResident->id,
            'category' => 'general',
            'content' => 'Test post content.',
        ]);
    }

    private function token(User $user): array
    {
        $token = $user->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    // ── Boot & Case Remediation ──

    public function test_laravel_boots_without_case_errors(): void
    {
        $this->assertTrue(app()->bound('router'));
    }

    public function test_post_routes_are_registered(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        $postRoutes = $routes->filter(function ($r) {
            $name = $r->getName() ?? '';
            return str_contains($name, 'v1.communities.posts.')
                && ! str_contains($name, 'comments.')
                && ! str_contains($name, 'media.')
                && ! str_contains($name, '.react');
        });
        $this->assertCount(5, $postRoutes);
    }

    public function test_comment_routes_are_registered(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        $commentRoutes = $routes->filter(fn ($r) => str_contains($r->getName() ?? '', 'v1.communities.posts.comments.'));
        $this->assertCount(4, $commentRoutes);
    }

    public function test_no_api_api_v1_prefix(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        $double = $routes->filter(fn ($r) => str_starts_with($r->uri(), 'api/api/'));
        $this->assertCount(0, $double);
    }

    // ── Authentication ──

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson("/api/v1/communities/{$this->community->id}/posts")
            ->assertStatus(401);
    }

    public function test_inactive_user_is_blocked(): void
    {
        $inactive = User::factory()->resident()->create(['is_active' => false]);
        $this->getJson(
            "/api/v1/communities/{$this->community->id}/posts",
            $this->token($inactive)
        )->assertStatus(403);
    }

    // ── Read Permissions ──

    public function test_active_resident_can_list_posts(): void
    {
        $this->getJson(
            "/api/v1/communities/{$this->community->id}/posts",
            $this->token($this->activeResidentUser)
        )->assertOk();
    }

    public function test_suspended_resident_can_list_posts(): void
    {
        $this->getJson(
            "/api/v1/communities/{$this->community->id}/posts",
            $this->token($this->suspendedUser)
        )->assertOk();
    }

    public function test_pending_resident_cannot_list_posts(): void
    {
        $this->getJson(
            "/api/v1/communities/{$this->community->id}/posts",
            $this->token($this->pendingUser)
        )->assertStatus(403);
    }

    public function test_rejected_resident_cannot_list_posts(): void
    {
        $this->getJson(
            "/api/v1/communities/{$this->community->id}/posts",
            $this->token($this->rejectedUser)
        )->assertStatus(403);
    }

    public function test_non_member_cannot_list_posts(): void
    {
        $this->getJson(
            "/api/v1/communities/{$this->community->id}/posts",
            $this->token($this->nonMemberUser)
        )->assertStatus(403);
    }

    public function test_manager_can_list_managed_community_posts(): void
    {
        $this->getJson(
            "/api/v1/communities/{$this->community->id}/posts",
            $this->token($this->managerUser)
        )->assertOk();
    }

    public function test_unrelated_manager_cannot_list_posts(): void
    {
        $this->getJson(
            "/api/v1/communities/{$this->community->id}/posts",
            $this->token($this->unrelatedManagerUser)
        )->assertStatus(403);
    }

    public function test_super_admin_can_list_posts(): void
    {
        $this->getJson(
            "/api/v1/communities/{$this->community->id}/posts",
            $this->token($this->superAdminUser)
        )->assertOk();
    }

    // ── Write Permissions (Posts) ──

    public function test_active_resident_can_create_post(): void
    {
        $this->postJson(
            "/api/v1/communities/{$this->community->id}/posts",
            ['category' => 'general', 'content' => 'My new post.'],
            $this->token($this->activeResidentUser)
        )->assertStatus(201)
         ->assertJsonPath('data.category', 'general');
    }

    public function test_suspended_resident_cannot_create_post(): void
    {
        $this->postJson(
            "/api/v1/communities/{$this->community->id}/posts",
            ['category' => 'general', 'content' => 'Blocked post.'],
            $this->token($this->suspendedUser)
        )->assertStatus(403);
    }

    public function test_pending_resident_cannot_create_post(): void
    {
        $this->postJson(
            "/api/v1/communities/{$this->community->id}/posts",
            ['category' => 'general', 'content' => 'Blocked post.'],
            $this->token($this->pendingUser)
        )->assertStatus(403);
    }

    public function test_non_member_cannot_create_post(): void
    {
        $this->postJson(
            "/api/v1/communities/{$this->community->id}/posts",
            ['category' => 'general', 'content' => 'Blocked post.'],
            $this->token($this->nonMemberUser)
        )->assertStatus(403);
    }

    public function test_manager_without_residency_cannot_create_post(): void
    {
        $this->postJson(
            "/api/v1/communities/{$this->community->id}/posts",
            ['category' => 'general', 'content' => 'Blocked post.'],
            $this->token($this->managerUser)
        )->assertStatus(403);
    }

    // ── Ownership (Posts) ──

    public function test_active_owner_can_update_post(): void
    {
        $this->patchJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}",
            ['content' => 'Updated content.'],
            $this->token($this->activeResidentUser)
        )->assertOk()
         ->assertJsonPath('data.content', 'Updated content.');
    }

    public function test_active_owner_can_delete_post(): void
    {
        $this->deleteJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}",
            [],
            $this->token($this->activeResidentUser)
        )->assertStatus(200);

        $this->assertSoftDeleted('posts', ['id' => $this->post->id]);
    }

    public function test_suspended_owner_cannot_update_post(): void
    {
        $suspendedPost = Post::create([
            'community_id' => $this->community->id,
            'resident_id' => Resident::where('user_id', $this->suspendedUser->id)->first()->id,
            'category' => 'general',
            'content' => 'Suspended user post.',
        ]);

        $this->patchJson(
            "/api/v1/communities/{$this->community->id}/posts/{$suspendedPost->id}",
            ['content' => 'Update attempt.'],
            $this->token($this->suspendedUser)
        )->assertStatus(403);
    }

    public function test_suspended_owner_cannot_delete_post(): void
    {
        $suspendedPost = Post::create([
            'community_id' => $this->community->id,
            'resident_id' => Resident::where('user_id', $this->suspendedUser->id)->first()->id,
            'category' => 'general',
            'content' => 'Suspended user post.',
        ]);

        $this->deleteJson(
            "/api/v1/communities/{$this->community->id}/posts/{$suspendedPost->id}",
            [],
            $this->token($this->suspendedUser)
        )->assertStatus(403);
    }

    public function test_other_resident_cannot_update_post(): void
    {
        $unit5 = Unit::create([
            'community_id' => $this->community->id,
            'unit_number' => 'A105',
            'building_name' => 'Block A',
            'rooms' => 2,
            'unit_type' => 'apartment',
            'is_active' => true,
        ]);
        $otherUser = User::factory()->resident()->create(['is_active' => true]);
        Resident::create([
            'user_id' => $otherUser->id,
            'unit_id' => $unit5->id,
            'community_id' => $this->community->id,
            'residence_type' => 'tenant',
            'status' => 'active',
        ]);

        $this->patchJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}",
            ['content' => 'Hijack attempt.'],
            $this->token($otherUser)
        )->assertStatus(403);
    }

    public function test_manager_cannot_edit_others_post(): void
    {
        $this->patchJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}",
            ['content' => 'Manager edit attempt.'],
            $this->token($this->managerUser)
        )->assertStatus(403);
    }

    public function test_manager_can_delete_others_post(): void
    {
        $this->deleteJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}",
            [],
            $this->token($this->managerUser)
        )->assertStatus(200);
    }

    public function test_super_admin_can_delete_others_post(): void
    {
        $this->deleteJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}",
            [],
            $this->token($this->superAdminUser)
        )->assertStatus(200);
    }

    // ── IDOR ──

    public function test_cross_community_post_returns_404(): void
    {
        $otherPost = Post::create([
            'community_id' => $this->otherCommunity->id,
            'resident_id' => $this->activeResident->id,
            'category' => 'general',
            'content' => 'Other community post.',
        ]);

        $this->getJson(
            "/api/v1/communities/{$this->community->id}/posts/{$otherPost->id}",
            $this->token($this->activeResidentUser)
        )->assertStatus(404);
    }

    public function test_cross_post_comment_returns_404(): void
    {
        $otherPost = Post::create([
            'community_id' => $this->community->id,
            'resident_id' => $this->activeResident->id,
            'category' => 'general',
            'content' => 'Other post.',
        ]);

        $comment = $this->post->comments()->create([
            'author_id' => $this->activeResidentUser->id,
            'content' => 'A comment.',
        ]);

        $this->patchJson(
            "/api/v1/communities/{$this->community->id}/posts/{$otherPost->id}/comments/{$comment->id}",
            ['content' => 'Hijack.'],
            $this->token($this->activeResidentUser)
        )->assertStatus(404);
    }

    // ── Validation ──

    public function test_valid_category_accepted(): void
    {
        $this->postJson(
            "/api/v1/communities/{$this->community->id}/posts",
            ['category' => 'lost_found', 'content' => 'Lost my keys.'],
            $this->token($this->activeResidentUser)
        )->assertStatus(201);
    }

    public function test_invalid_category_rejected(): void
    {
        $this->postJson(
            "/api/v1/communities/{$this->community->id}/posts",
            ['category' => 'invalid_cat', 'content' => 'Bad category.'],
            $this->token($this->activeResidentUser)
        )->assertStatus(422);
    }

    public function test_content_required(): void
    {
        $this->postJson(
            "/api/v1/communities/{$this->community->id}/posts",
            ['category' => 'general'],
            $this->token($this->activeResidentUser)
        )->assertStatus(422);
    }

    public function test_moderation_fields_ignored(): void
    {
        $response = $this->postJson(
            "/api/v1/communities/{$this->community->id}/posts",
            [
                'category' => 'general',
                'content' => 'A post.',
                'is_pinned' => now()->toDateTimeString(),
                'pinned_by' => $this->activeResidentUser->id,
                'community_id' => 9999,
                'resident_id' => 9999,
            ],
            $this->token($this->activeResidentUser)
        )->assertStatus(201);

        $postId = $response->json('data.id');
        $created = Post::find($postId);
        $this->assertNull($created->is_pinned);
        $this->assertNull($created->pinned_by);
        $this->assertEquals($this->community->id, $created->community_id);
        $this->assertEquals($this->activeResident->id, $created->resident_id);
    }

    // ── Pagination & Resources ──

    public function test_posts_use_stable_ordering_and_pagination(): void
    {
        for ($i = 0; $i < 3; $i++) {
            Post::create([
                'community_id' => $this->community->id,
                'resident_id' => $this->activeResident->id,
                'category' => 'general',
                'content' => "Post {$i}",
            ]);
        }

        $response = $this->getJson(
            "/api/v1/communities/{$this->community->id}/posts?per_page=2",
            $this->token($this->activeResidentUser)
        )->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
        // Newest first
        $this->assertGreaterThanOrEqual($data[1]['id'], $data[0]['id']);
        // Has pagination meta
        $this->assertArrayHasKey('meta', $response->json());
    }

    public function test_post_resource_includes_comments_count(): void
    {
        $this->post->comments()->create([
            'author_id' => $this->activeResidentUser->id,
            'content' => 'Comment 1.',
        ]);

        $response = $this->getJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}",
            $this->token($this->activeResidentUser)
        )->assertOk();

        $this->assertArrayHasKey('comments_count', $response->json('data'));
        $this->assertEquals(1, $response->json('data.comments_count'));
    }

    public function test_post_resource_does_not_serialize_full_user(): void
    {
        $response = $this->getJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}",
            $this->token($this->activeResidentUser)
        )->assertOk();

        $author = $response->json('data.author');
        $this->assertArrayHasKey('id', $author);
        $this->assertArrayNotHasKey('email', $author);
        $this->assertArrayNotHasKey('password', $author);
    }

    // ── Comments ──

    public function test_active_resident_can_create_comment(): void
    {
        $this->postJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}/comments",
            ['content' => 'A new comment.'],
            $this->token($this->activeResidentUser)
        )->assertStatus(201);
    }

    public function test_suspended_resident_cannot_create_comment(): void
    {
        $this->postJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}/comments",
            ['content' => 'Blocked comment.'],
            $this->token($this->suspendedUser)
        )->assertStatus(403);
    }

    public function test_active_owner_can_update_comment(): void
    {
        $comment = $this->post->comments()->create([
            'author_id' => $this->activeResidentUser->id,
            'content' => 'Original comment.',
        ]);

        $this->patchJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}/comments/{$comment->id}",
            ['content' => 'Updated comment.'],
            $this->token($this->activeResidentUser)
        )->assertOk()
         ->assertJsonPath('data.content', 'Updated comment.');
    }

    public function test_active_owner_can_delete_comment(): void
    {
        $comment = $this->post->comments()->create([
            'author_id' => $this->activeResidentUser->id,
            'content' => 'To be deleted.',
        ]);

        $this->deleteJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}/comments/{$comment->id}",
            [],
            $this->token($this->activeResidentUser)
        )->assertStatus(200);
    }

    public function test_manager_cannot_edit_others_comment(): void
    {
        $comment = $this->post->comments()->create([
            'author_id' => $this->activeResidentUser->id,
            'content' => 'Resident comment.',
        ]);

        $this->patchJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}/comments/{$comment->id}",
            ['content' => 'Manager edit.'],
            $this->token($this->managerUser)
        )->assertStatus(403);
    }

    public function test_manager_can_delete_others_comment(): void
    {
        $comment = $this->post->comments()->create([
            'author_id' => $this->activeResidentUser->id,
            'content' => 'Resident comment.',
        ]);

        $this->deleteJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}/comments/{$comment->id}",
            [],
            $this->token($this->managerUser)
        )->assertStatus(200);
    }

    public function test_super_admin_can_delete_others_comment(): void
    {
        $comment = $this->post->comments()->create([
            'author_id' => $this->activeResidentUser->id,
            'content' => 'Resident comment.',
        ]);

        $this->deleteJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}/comments/{$comment->id}",
            [],
            $this->token($this->superAdminUser)
        )->assertStatus(200);
    }

    public function test_comment_resource_does_not_expose_morph_class(): void
    {
        $comment = $this->post->comments()->create([
            'author_id' => $this->activeResidentUser->id,
            'content' => 'Resource test.',
        ]);

        $response = $this->getJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}/comments",
            $this->token($this->activeResidentUser)
        )->assertOk();

        $firstComment = $response->json('data.0');
        $this->assertArrayNotHasKey('commentable_type', $firstComment);
        $this->assertArrayNotHasKey('commentable_id', $firstComment);
    }

    public function test_parent_id_not_accepted_in_create_comment(): void
    {
        $parent = $this->post->comments()->create([
            'author_id' => $this->activeResidentUser->id,
            'content' => 'Parent.',
        ]);

        $response = $this->postJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}/comments",
            ['content' => 'Reply.', 'parent_id' => $parent->id],
            $this->token($this->activeResidentUser)
        )->assertStatus(201);

        $commentId = $response->json('data.id');
        $this->assertNull(Comment::find($commentId)->parent_id);
    }

    // ── Moderation Logging ──

    public function test_moderator_delete_logs_event(): void
    {
        $handler = new TestHandler();
        $securityLogger = Log::channel('security')->getLogger();
        if ($securityLogger instanceof Logger) {
            $securityLogger->setHandlers([$handler]);
        }

        $this->deleteJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}",
            [],
            $this->token($this->managerUser)
        )->assertStatus(200);

        $hasLog = false;
        foreach ($handler->getRecords() as $record) {
            if (str_contains($record['message'] ?? '', 'content.post.deleted_by_moderator')) {
                $hasLog = true;
                $this->assertArrayHasKey('actor_user_id', $record['context']);
                $this->assertArrayHasKey('community_id', $record['context']);
                $this->assertArrayHasKey('post_id', $record['context']);
                $this->assertArrayNotHasKey('content', $record['context']);
            }
        }
        $this->assertTrue($hasLog, 'Moderation log event not found');
    }

    public function test_owner_delete_does_not_log_moderation_event(): void
    {
        $handler = new TestHandler();
        $securityLogger = Log::channel('security')->getLogger();
        if ($securityLogger instanceof Logger) {
            $securityLogger->setHandlers([$handler]);
        }

        $this->deleteJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}",
            [],
            $this->token($this->activeResidentUser)
        )->assertStatus(200);

        foreach ($handler->getRecords() as $record) {
            $this->assertStringNotContainsString(
                'deleted_by_moderator',
                $record['message'] ?? ''
            );
        }
    }

    // ── Regression ──

    public function test_auth_routes_unchanged(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        $authRoutes = $routes->filter(fn ($r) => str_starts_with($r->getName() ?? '', 'api.auth.'));

        $expectedNames = [
            'api.auth.register',
            'api.auth.login',
            'api.auth.forgot-password',
            'api.auth.reset-password',
            'api.auth.logout',
            'api.auth.me',
            'api.auth.password',
        ];

        foreach ($expectedNames as $name) {
            $this->assertTrue(
                $authRoutes->contains(fn ($r) => $r->getName() === $name),
                "Auth route '{$name}' is missing"
            );
        }
    }

    public function test_community_routes_still_present(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        $communityRoutes = $routes->filter(fn ($r) => str_contains($r->uri(), 'communities/{communityId}'));
        $this->assertGreaterThanOrEqual(5, $communityRoutes->count());
    }

    // ── Category filter ──

    public function test_category_filter_works(): void
    {
        Post::create([
            'community_id' => $this->community->id,
            'resident_id' => $this->activeResident->id,
            'category' => 'event',
            'content' => 'Event post.',
        ]);

        $response = $this->getJson(
            "/api/v1/communities/{$this->community->id}/posts?category=event",
            $this->token($this->activeResidentUser)
        )->assertOk();

        foreach ($response->json('data') as $post) {
            $this->assertEquals('event', $post['category']);
        }
    }

    // ── Suspended read comments ──

    public function test_suspended_resident_can_list_comments(): void
    {
        $this->post->comments()->create([
            'author_id' => $this->activeResidentUser->id,
            'content' => 'Comment.',
        ]);

        $this->getJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}/comments",
            $this->token($this->suspendedUser)
        )->assertOk();
    }

    public function test_suspended_owner_cannot_delete_comment(): void
    {
        $comment = $this->post->comments()->create([
            'author_id' => $this->suspendedUser->id,
            'content' => 'Suspended owner comment.',
        ]);

        $this->deleteJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}/comments/{$comment->id}",
            [],
            $this->token($this->suspendedUser)
        )->assertStatus(403);
    }
}
