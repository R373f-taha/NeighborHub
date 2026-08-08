<?php

declare(strict_types=1);

namespace Tests\Feature\Posts;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\CommunityManager;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Modules\Interaction\app\Models\Reaction;
use Modules\Post\app\Models\Post;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\TestCase;

class PostReactionsTest extends TestCase
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
    private User $managerWithResidency;
    private User $superAdminWithResidency;
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
        DB::table('reactions')->delete();
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
            'total_units' => 20,
            'is_active' => true,
        ]);

        $this->otherCommunity = Community::create([
            'name' => 'Other Community',
            'city' => 'Other City',
            'address' => '456 Other St',
            'total_units' => 5,
            'is_active' => true,
        ]);

        $unitIndex = 0;
        $makeUnit = function () use (&$unitIndex) {
            $unitIndex++;
            return Unit::create([
                'community_id' => $this->community->id,
                'unit_number' => "U{$unitIndex}",
                'building_name' => 'Block A',
                'rooms' => 2,
                'unit_type' => 'apartment',
                'is_active' => true,
            ]);
        };

        $this->activeResidentUser = User::factory()->resident()->create(['is_active' => true]);
        $this->activeResident = Resident::create([
            'user_id' => $this->activeResidentUser->id,
            'unit_id' => $makeUnit()->id,
            'community_id' => $this->community->id,
            'residence_type' => 'owner',
            'status' => 'active',
            'current_marker' => true,
        ]);

        $this->suspendedUser = User::factory()->resident()->create(['is_active' => true]);
        Resident::create([
            'user_id' => $this->suspendedUser->id,
            'unit_id' => $makeUnit()->id,
            'community_id' => $this->community->id,
            'residence_type' => 'tenant',
            'status' => 'suspended',
        ]);

        $this->pendingUser = User::factory()->resident()->create(['is_active' => true]);
        Resident::create([
            'user_id' => $this->pendingUser->id,
            'unit_id' => $makeUnit()->id,
            'community_id' => $this->community->id,
            'residence_type' => 'tenant',
            'status' => 'pending',
        ]);

        $this->rejectedUser = User::factory()->resident()->create(['is_active' => true]);
        Resident::create([
            'user_id' => $this->rejectedUser->id,
            'unit_id' => $makeUnit()->id,
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

        $this->managerWithResidency = User::factory()->manager()->create(['is_active' => true]);
        CommunityManager::create([
            'community_id' => $this->community->id,
            'manager_id' => $this->managerWithResidency->id,
        ]);
        Resident::create([
            'user_id' => $this->managerWithResidency->id,
            'unit_id' => $makeUnit()->id,
            'community_id' => $this->community->id,
            'residence_type' => 'owner',
            'status' => 'active',
            'current_marker' => true,
        ]);

        $this->superAdminWithResidency = User::factory()->superAdmin()->create(['is_active' => true]);
        Resident::create([
            'user_id' => $this->superAdminWithResidency->id,
            'unit_id' => $makeUnit()->id,
            'community_id' => $this->community->id,
            'residence_type' => 'owner',
            'status' => 'active',
            'current_marker' => true,
        ]);

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

    private function reactUrl(?Post $post = null, ?Community $community = null): string
    {
        $c = $community ?? $this->community;
        $p = $post ?? $this->post;
        return "/api/v1/communities/{$c->id}/posts/{$p->id}/react";
    }

    // ── Route & Authentication ──

    public function test_react_route_is_registered(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        $reactRoutes = $routes->filter(fn ($r) => str_contains($r->getName() ?? '', '.react'));
        $this->assertCount(1, $reactRoutes);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->postJson($this->reactUrl(), ['type' => 'like'])
            ->assertStatus(401);
    }

    public function test_inactive_user_is_denied(): void
    {
        $inactive = User::factory()->resident()->create(['is_active' => false]);
        $this->postJson($this->reactUrl(), ['type' => 'like'], $this->token($inactive))
            ->assertStatus(403);
    }

    public function test_post_from_another_community_returns_404(): void
    {
        $otherPost = Post::create([
            'community_id' => $this->otherCommunity->id,
            'resident_id' => $this->activeResident->id,
            'category' => 'general',
            'content' => 'Other community post.',
        ]);

        $this->postJson(
            "/api/v1/communities/{$this->community->id}/posts/{$otherPost->id}/react",
            ['type' => 'like'],
            $this->token($this->activeResidentUser)
        )->assertStatus(404);
    }

    // ── Authorization ──

    public function test_active_resident_may_react(): void
    {
        $this->postJson($this->reactUrl(), ['type' => 'like'], $this->token($this->activeResidentUser))
            ->assertStatus(201);
    }

    public function test_suspended_resident_denied(): void
    {
        $this->postJson($this->reactUrl(), ['type' => 'like'], $this->token($this->suspendedUser))
            ->assertStatus(403);
    }

    public function test_pending_resident_denied(): void
    {
        $this->postJson($this->reactUrl(), ['type' => 'like'], $this->token($this->pendingUser))
            ->assertStatus(403);
    }

    public function test_rejected_resident_denied(): void
    {
        $this->postJson($this->reactUrl(), ['type' => 'like'], $this->token($this->rejectedUser))
            ->assertStatus(403);
    }

    public function test_non_member_denied(): void
    {
        $this->postJson($this->reactUrl(), ['type' => 'like'], $this->token($this->nonMemberUser))
            ->assertStatus(403);
    }

    public function test_manager_without_active_residency_denied(): void
    {
        $this->postJson($this->reactUrl(), ['type' => 'like'], $this->token($this->managerUser))
            ->assertStatus(403);
    }

    public function test_unrelated_manager_denied(): void
    {
        $this->postJson($this->reactUrl(), ['type' => 'like'], $this->token($this->unrelatedManagerUser))
            ->assertStatus(403);
    }

    public function test_super_admin_without_active_residency_denied(): void
    {
        $this->postJson($this->reactUrl(), ['type' => 'like'], $this->token($this->superAdminUser))
            ->assertStatus(403);
    }

    public function test_manager_with_active_residency_may_react(): void
    {
        $this->postJson($this->reactUrl(), ['type' => 'like'], $this->token($this->managerWithResidency))
            ->assertStatus(201);
    }

    public function test_super_admin_with_active_residency_may_react(): void
    {
        $this->postJson($this->reactUrl(), ['type' => 'like'], $this->token($this->superAdminWithResidency))
            ->assertStatus(201);
    }

    // ── Toggle Behavior ──

    public function test_no_reaction_creates_one(): void
    {
        $response = $this->postJson($this->reactUrl(), ['type' => 'like'], $this->token($this->activeResidentUser))
            ->assertStatus(201)
            ->assertJsonPath('data.action', 'created')
            ->assertJsonPath('data.reaction.type', 'like')
            ->assertJsonPath('data.reactions_count', 1);

        $this->assertDatabaseHas('reactions', [
            'user_id' => $this->activeResidentUser->id,
            'reactionable_id' => $this->post->id,
            'type' => 'like',
        ]);
    }

    public function test_different_type_updates_same_row(): void
    {
        $this->postJson($this->reactUrl(), ['type' => 'like'], $this->token($this->activeResidentUser))
            ->assertStatus(201);

        $reactionId = Reaction::where('user_id', $this->activeResidentUser->id)->first()->id;

        $this->postJson($this->reactUrl(), ['type' => 'love'], $this->token($this->activeResidentUser))
            ->assertStatus(200)
            ->assertJsonPath('data.action', 'updated')
            ->assertJsonPath('data.reaction.type', 'love');

        $updated = Reaction::where('user_id', $this->activeResidentUser->id)->first();
        $this->assertEquals($reactionId, $updated->id);
        $this->assertEquals('love', $updated->type->value);
    }

    public function test_same_type_removes_reaction(): void
    {
        $this->postJson($this->reactUrl(), ['type' => 'like'], $this->token($this->activeResidentUser))
            ->assertStatus(201);

        $this->postJson($this->reactUrl(), ['type' => 'like'], $this->token($this->activeResidentUser))
            ->assertStatus(200)
            ->assertJsonPath('data.action', 'removed')
            ->assertJsonPath('data.reaction', null)
            ->assertJsonPath('data.reactions_count', 0);

        $this->assertDatabaseMissing('reactions', [
            'user_id' => $this->activeResidentUser->id,
            'reactionable_id' => $this->post->id,
        ]);
    }

    public function test_two_users_react_independently(): void
    {
        $this->postJson($this->reactUrl(), ['type' => 'like'], $this->token($this->activeResidentUser))
            ->assertStatus(201);

        $this->postJson($this->reactUrl(), ['type' => 'love'], $this->token($this->managerWithResidency))
            ->assertStatus(201)
            ->assertJsonPath('data.reactions_count', 2);

        $this->assertCount(2, Reaction::where('reactionable_id', $this->post->id)->get());
    }

    public function test_no_duplicate_reactions_per_user(): void
    {
        $this->postJson($this->reactUrl(), ['type' => 'like'], $this->token($this->activeResidentUser))
            ->assertStatus(201);

        $this->postJson($this->reactUrl(), ['type' => 'love'], $this->token($this->activeResidentUser))
            ->assertStatus(200);

        $count = Reaction::where('user_id', $this->activeResidentUser->id)
            ->where('reactionable_id', $this->post->id)
            ->count();
        $this->assertEquals(1, $count);
    }

    // ── Security ──

    public function test_user_id_from_body_cannot_impersonate(): void
    {
        $this->postJson($this->reactUrl(), [
            'type' => 'like',
            'user_id' => $this->managerWithResidency->id,
        ], $this->token($this->activeResidentUser))
            ->assertStatus(422);
    }

    public function test_reactionable_type_cannot_be_injected(): void
    {
        $this->postJson($this->reactUrl(), [
            'type' => 'like',
            'reactionable_type' => 'App\\Models\\Announcement',
        ], $this->token($this->activeResidentUser))
            ->assertStatus(422);
    }

    public function test_reactionable_id_cannot_be_injected(): void
    {
        $this->postJson($this->reactUrl(), [
            'type' => 'like',
            'reactionable_id' => 9999,
        ], $this->token($this->activeResidentUser))
            ->assertStatus(422);
    }

    public function test_community_id_body_cannot_override_route(): void
    {
        $this->postJson($this->reactUrl(), [
            'type' => 'like',
            'community_id' => 9999,
        ], $this->token($this->activeResidentUser))
            ->assertStatus(422);
    }

    public function test_response_does_not_expose_morph_type_or_user_id(): void
    {
        $response = $this->postJson($this->reactUrl(), ['type' => 'like'], $this->token($this->activeResidentUser))
            ->assertStatus(201);

        $data = $response->json('data');
        $this->assertArrayNotHasKey('reactionable_type', $data);
        $this->assertArrayNotHasKey('reactionable_id', $data);
        $this->assertArrayNotHasKey('user_id', $data);

        if ($data['reaction'] !== null) {
            $this->assertArrayNotHasKey('reactionable_type', $data['reaction']);
            $this->assertArrayNotHasKey('reactionable_id', $data['reaction']);
            $this->assertArrayNotHasKey('user_id', $data['reaction']);
        }
    }

    // ── Constraint ──

    public function test_unique_index_exists(): void
    {
        $indexes = collect(Schema::getIndexes('reactions'))->pluck('name');
        $this->assertTrue($indexes->contains('reactions_target_user_unique'));
    }

    public function test_duplicate_database_insertion_rejected(): void
    {
        DB::table('reactions')->insert([
            'reactionable_type' => $this->post->getMorphClass(),
            'reactionable_id' => $this->post->id,
            'user_id' => $this->activeResidentUser->id,
            'type' => 'like',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('reactions')->insert([
            'reactionable_type' => $this->post->getMorphClass(),
            'reactionable_id' => $this->post->id,
            'user_id' => $this->activeResidentUser->id,
            'type' => 'love',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── Validation ──

    public function test_type_is_required(): void
    {
        $this->postJson($this->reactUrl(), [], $this->token($this->activeResidentUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_invalid_type_rejected(): void
    {
        $this->postJson($this->reactUrl(), ['type' => 'angry'], $this->token($this->activeResidentUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_all_valid_types_accepted(): void
    {
        foreach (['like', 'love', 'support', 'helpful', 'celebrate'] as $type) {
            DB::table('reactions')->where('user_id', $this->activeResidentUser->id)->delete();

            $this->postJson($this->reactUrl(), ['type' => $type], $this->token($this->activeResidentUser))
                ->assertStatus(201);
        }
    }

    // ── Regression ──

    public function test_post_crud_endpoints_remain(): void
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

    public function test_comment_endpoints_remain(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        $commentRoutes = $routes->filter(fn ($r) => str_contains($r->getName() ?? '', 'v1.communities.posts.comments.'));
        $this->assertCount(4, $commentRoutes);
    }

    public function test_total_content_endpoints(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        $contentRoutes = $routes->filter(function ($r) {
            $name = $r->getName() ?? '';
            return str_contains($name, 'v1.communities.posts.');
        });
        $this->assertCount(12, $contentRoutes);
    }

    public function test_no_duplicate_prefix(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        $double = $routes->filter(fn ($r) => str_starts_with($r->uri(), 'api/api/'));
        $this->assertCount(0, $double);
    }

    public function test_post_policy_existing_abilities_unchanged(): void
    {
        $this->getJson(
            "/api/v1/communities/{$this->community->id}/posts",
            $this->token($this->activeResidentUser)
        )->assertOk();

        $this->postJson(
            "/api/v1/communities/{$this->community->id}/posts",
            ['category' => 'general', 'content' => 'Regression test.'],
            $this->token($this->activeResidentUser)
        )->assertStatus(201);

        $this->getJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}",
            $this->token($this->activeResidentUser)
        )->assertOk();

        $this->patchJson(
            "/api/v1/communities/{$this->community->id}/posts/{$this->post->id}",
            ['content' => 'Updated for regression test.'],
            $this->token($this->activeResidentUser)
        )->assertOk();
    }

    // ── Concurrency & Migration Guard ──

    public function test_migration_throws_when_duplicates_exist(): void
    {
        $migration = require base_path('Modules/Interaction/database/migrations/2026_07_30_134800_add_unique_user_reaction_constraint_to_reactions_table.php');

        try {
            $migration->down();
        } catch (\Exception $e) {
            // Ignore if already down
        }

        DB::table('reactions')->insert([
            ['reactionable_type' => $this->post->getMorphClass(), 'reactionable_id' => $this->post->id, 'user_id' => $this->activeResidentUser->id, 'type' => 'like', 'created_at' => now(), 'updated_at' => now()],
            ['reactionable_type' => $this->post->getMorphClass(), 'reactionable_id' => $this->post->id, 'user_id' => $this->activeResidentUser->id, 'type' => 'love', 'created_at' => now(), 'updated_at' => now()],
        ]);

        try {
            $migration->up();
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Cannot add reactions_target_user_unique: duplicate user reactions exist in 1 target groups.', $e->getMessage());
        }

        $this->assertCount(2, DB::table('reactions')->where('user_id', $this->activeResidentUser->id)->get());

        DB::table('reactions')->where('type', 'love')->delete();
        $migration->up();
    }

    public function test_service_retries_on_unique_constraint_violation(): void
    {
        $service = app(\Modules\Interaction\app\Services\ReactionService::class);

        \Modules\Interaction\app\Models\Reaction::saving(function ($model) {
            static $thrown = false;
            if (!$thrown && $model->type->value === 'support') {
                $thrown = true;

                // Simulate competing transaction inserted it
                DB::table('reactions')->insert([
                    'reactionable_type' => $model->reactionable_type,
                    'reactionable_id' => $model->reactionable_id,
                    'user_id' => $model->user_id,
                    'type' => 'love',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $e = new \Illuminate\Database\QueryException(
                    'mysql',
                    'insert into reactions...',
                    [],
                    new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'reactions_target_user_unique\'')
                );
                $e->errorInfo = ['23000', 1062, "Duplicate entry '...' for key 'reactions_target_user_unique'"];
                throw $e;
            }
        });

        $result = $service->toggle($this->post, $this->activeResidentUser, \Modules\Interaction\app\Enums\ReactionType::Support);

        // Attempt 2 sees no reaction (competing insert rolled back with the transaction) and creates it
        $this->assertEquals('created', $result['action']);

        $this->assertDatabaseHas('reactions', [
            'reactionable_id' => $this->post->id,
            'user_id' => $this->activeResidentUser->id,
            'type' => 'support',
        ]);

        // Ensure no duplicates exist
        $this->assertEquals(1, DB::table('reactions')->where('user_id', $this->activeResidentUser->id)->count());
    }

    public function test_service_rethrows_unrelated_query_exceptions(): void
    {
        $service = app(\Modules\Interaction\app\Services\ReactionService::class);

        \Modules\Interaction\app\Models\Reaction::saving(function ($model) {
            if ($model->type->value === 'helpful') {
                $e = new \Illuminate\Database\QueryException(
                    'mysql',
                    'insert into reactions...',
                    [],
                    new \PDOException('SQLSTATE[42S22]: Column not found: 1054 Unknown column')
                );
                $e->errorInfo = ['42S22', 1054, "Unknown column"];
                throw $e;
            }
        });

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->expectExceptionMessage('Column not found');

        $service->toggle($this->post, $this->activeResidentUser, \Modules\Interaction\app\Enums\ReactionType::Helpful);
    }
}
