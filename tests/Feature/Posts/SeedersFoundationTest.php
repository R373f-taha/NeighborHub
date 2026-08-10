<?php

declare(strict_types=1);

namespace Tests\Feature\Posts;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Modules\Interaction\Database\Seeders\CommentSeeder;
use Modules\Post\app\Models\Post;
use Modules\Post\Database\Seeders\PostSeeder;
use Tests\TestCase;

class SeedersFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_seeder_seeds_active_residents_of_same_community(): void
    {
        $community = Community::factory()->create();
        $unit = Unit::factory()->for($community)->create();
        Resident::factory()->for($unit)->active()->count(3)->create();
        Resident::factory()->for($unit)->pending()->create();

        (new PostSeeder())->run();

        $community = $community->fresh();
        // PER_COMMUNITY is 20 (see PostSeeder::PER_COMMUNITY), raised from 10 by
        // the "Increase seeders and factories data volume" commit (dcbd9db).
        $this->assertSame(20, $community->posts()->count());

        $community->posts->each(function (Post $post) use ($community): void {
            $this->assertSame($community->id, $post->community_id);
            $this->assertSame('active', $post->author->status);
            $this->assertSame($community->id, $post->author->unit->community_id);
        });
    }

    public function test_post_seeder_is_idempotent(): void
    {
        $community = Community::factory()->create();
        $unit = Unit::factory()->for($community)->create();
        Resident::factory()->for($unit)->active()->create();

        (new PostSeeder())->run();
        $first = $community->fresh()->posts()->count();

        (new PostSeeder())->run();
        $second = $community->fresh()->posts()->count();

        $this->assertSame($first, $second);
    }

    public function test_post_seeder_skips_when_no_active_residents(): void
    {
        $community = Community::factory()->create();

        (new PostSeeder())->run();

        $this->assertSame(0, $community->fresh()->posts()->count());
    }

    public function test_comment_seeder_uses_same_community_authors_for_posts(): void
    {
        $community = Community::factory()->create();
        $unit = Unit::factory()->for($community)->create();
        $residents = Resident::factory()->for($unit)->active()->count(3)->create();
        $posts = Post::factory()
            ->forCommunity($community)
            ->forResident($residents->first())
            ->count(2)
            ->create();

        (new CommentSeeder())->run();

        $memberUserIds = $residents->pluck('user_id')->all();

        $posts->each(function (Post $post) use ($memberUserIds): void {
            $comments = $post->fresh()->comments;
            $this->assertGreaterThan(0, $comments->count(), 'Post received seeded comments');

            $comments->each(function ($comment) use ($memberUserIds, $post): void {
                $this->assertContains($comment->author_id, $memberUserIds, 'Comment author is a member of the post community');
                $this->assertSame($post->getMorphClass(), $comment->commentable_type);
            });
        });
    }

    public function test_comment_seeder_skips_when_no_members(): void
    {
        $community = Community::factory()->create();
        $unit = Unit::factory()->for($community)->create();
        $resident = Resident::factory()->for($unit)->active()->create();
        Post::factory()->forCommunity($community)->forResident($resident)->create();
        $community->residents()->delete();

        (new CommentSeeder())->run();

        $this->expectNotToPerformAssertions();
    }
}
