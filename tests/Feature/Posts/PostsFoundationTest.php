<?php

declare(strict_types=1);

namespace Tests\Feature\Posts;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Modules\Interaction\app\Models\Comment;
use Modules\Interaction\Database\Factories\CommentFactory;
use Modules\Post\app\Http\Resources\Api\V1\PostResource;
use Modules\Post\app\Models\Post;
use Modules\Post\Database\Factories\PostFactory;
use Tests\TestCase;

class PostsFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_moderation_fields_are_not_mass_assignable(): void
    {
        $fillable = (new Post())->getFillable();

        $this->assertNotContains('is_pinned', $fillable);
        $this->assertNotContains('pinned_by', $fillable);
        $this->assertContains('community_id', $fillable);
        $this->assertContains('resident_id', $fillable);
        $this->assertContains('category', $fillable);
        $this->assertContains('content', $fillable);
    }

    public function test_factories_resolve(): void
    {
        $this->assertInstanceOf(PostFactory::class, Post::factory());
        $this->assertInstanceOf(CommentFactory::class, Comment::factory());
    }

    public function test_post_factory_links_resident_to_same_community(): void
    {
        $community = Community::factory()->create();
        $unit = Unit::factory()->for($community)->create();
        $resident = Resident::factory()->for($unit)->active()->create();

        $post = Post::factory()
            ->forCommunity($community)
            ->forResident($resident)
            ->create();

        $this->assertSame($community->id, $post->community_id);
        $this->assertSame($resident->id, $post->resident_id);
        $this->assertSame($community->id, $resident->unit->community_id);
        $this->assertNull($post->getRawOriginal('is_pinned'));
        $this->assertNull($post->getRawOriginal('pinned_by'));
    }

    public function test_comment_factory_attaches_to_commentable_with_author(): void
    {
        $community = Community::factory()->create();
        $unit = Unit::factory()->for($community)->create();
        $resident = Resident::factory()->for($unit)->active()->create();
        $post = Post::factory()->forCommunity($community)->forResident($resident)->create();
        $author = $resident->user;

        $comment = Comment::factory()
            ->forCommentable($post)
            ->create(['author_id' => $author->id]);

        $this->assertSame($post->getMorphClass(), $comment->commentable_type);
        $this->assertSame($post->id, $comment->commentable_id);
        $this->assertSame($author->id, $comment->author_id);
        $this->assertNull($comment->parent_id);
    }

    public function test_comment_factory_reply_sets_parent(): void
    {
        $community = Community::factory()->create();
        $unit = Unit::factory()->for($community)->create();
        $resident = Resident::factory()->for($unit)->active()->create();
        $post = Post::factory()->forCommunity($community)->forResident($resident)->create();
        $author = $resident->user;

        $parent = Comment::factory()->forCommentable($post)->create(['author_id' => $author->id]);
        $reply = Comment::factory()->forCommentable($post)->reply($parent)->create(['author_id' => $author->id]);

        $this->assertSame($parent->id, $reply->parent_id);
    }

    public function test_post_resource_avoids_lazy_loading(): void
    {
        $community = Community::factory()->create();
        $unit = Unit::factory()->for($community)->create();
        $resident = Resident::factory()->for($unit)->active()->create();
        $post = Post::factory()->forCommunity($community)->forResident($resident)->create();

        $loaded = Post::with(['community', 'author.user'])
            ->withCount('comments')
            ->find($post->id);

        $payload = (new PostResource($loaded))->resolve(request());

        $this->assertSame($community->id, $payload['community_id']);
        $this->assertSame($resident->id, $payload['author']['id']);
        $this->assertSame($resident->user->name, $payload['author']['name']);
        $this->assertSame(0, $payload['comments_count']);
    }
}
