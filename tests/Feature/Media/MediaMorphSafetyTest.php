<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Community\app\Models\Announcement;
use Modules\Interaction\app\Models\Comment;
use Modules\Issue\app\Models\Issue;
use Modules\Media\app\Models\Media;
use Modules\Media\app\Support\MediaParentType;
use Modules\Post\app\Models\Post;
use Modules\ServiceListing\app\Models\ServiceListing;

/**
 * MEDIA-1 hardening: morph safety and team-owned polymorphic regression.
 *
 * Proves (1) the Media API only resolves explicitly owned aliases, (2) upload
 * cannot influence the polymorphic owner, (3) foreign/tampered media cannot
 * participate in reorder, and (4) adopting a non-enforcing morph map for the
 * two owned parents does not break Issue / Announcement / other team-owned
 * polymorphic relations.
 */
class MediaMorphSafetyTest extends MediaTestCase
{
    public function test_morph_map_only_contains_owned_aliases(): void
    {
        $this->assertSame(MediaParentType::map(), Relation::morphMap());
    }

    public function test_owned_parents_use_stable_aliases(): void
    {
        $this->assertSame('post', $this->post->getMorphClass());
        $this->assertSame('service_listing', $this->listing->getMorphClass());
    }

    public function test_team_owned_parents_keep_full_class_names(): void
    {
        $announcement = Announcement::factory()->create([
            'community_id' => $this->communityA->id,
            'created_by_manager' => $this->managerUser->id,
        ]);

        $this->assertSame(Announcement::class, $announcement->getMorphClass());
        $this->assertSame(Issue::class, (new Issue())->getMorphClass());
    }

    public function test_post_comment_round_trips_through_alias(): void
    {
        $comment = $this->post->comments()->create([
            'author_id' => $this->ownerUser->id,
            'content' => 'hi',
        ]);

        $this->assertSame('post', $comment->getRawOriginal('commentable_type'));
        $this->assertInstanceOf(Post::class, $comment->commentable);
        $this->assertCount(1, $this->post->fresh()->comments);
    }

    public function test_announcement_comment_round_trips_through_full_class_name(): void
    {
        $announcement = Announcement::factory()->create([
            'community_id' => $this->communityA->id,
            'created_by_manager' => $this->managerUser->id,
        ]);

        $comment = $announcement->comments()->create([
            'author_id' => $this->ownerUser->id,
            'content' => 'hi',
        ]);

        $this->assertSame(Announcement::class, $comment->getRawOriginal('commentable_type'));
        $this->assertInstanceOf(Announcement::class, $comment->commentable);
        $this->assertCount(1, $announcement->fresh()->comments);
    }

    public function test_service_listing_media_resolves_back_to_listing(): void
    {
        $this->assertSame('service_listing', $this->listing->getMorphClass());

        $media = $this->makeOwnedMedia('service_listing', (int) $this->listing->id);

        $this->assertCount(1, $this->listing->fresh()->media);
        $this->assertSame((int) $media->id, (int) $this->listing->fresh()->media->first()->id);
    }

    public function test_upload_ignores_client_supplied_mediable_type(): void
    {
        $response = $this->postJson(
            $this->postMediaUri($this->communityA, $this->post),
            array_merge(['image' => $this->validImage()], [
                'mediable_type' => 'service_listing',
                'mediable_id' => 999999,
            ]),
            $this->token($this->ownerUser),
        )->assertStatus(201);

        $media = Media::find($response->json('data.id'));

        $this->assertSame('post', $media->mediable_type);
        $this->assertSame($this->post->id, (int) $media->mediable_id);
    }

    public function test_reorder_cannot_include_media_with_tampered_parent_type(): void
    {
        $real = $this->upload($this->ownerUser)->assertStatus(201)->json('data.id');

        // A row whose stored type is NOT 'post' cannot be reached through the
        // post reorder route (it is scoped to the route's trusted alias/id).
        $tampered = $this->makeOwnedMedia('service_listing', (int) $this->listing->id);

        $this->patchJson($this->postReorderUri($this->communityA, $this->post), [
            'items' => [
                ['id' => $real, 'position' => 1],
                ['id' => $tampered->id, 'position' => 2],
            ],
        ], $this->token($this->ownerUser))
            ->assertStatus(422);

        $this->assertSame(1, (int) Media::find($real)->position);
    }

    private function makeOwnedMedia(string $type, int $parentId): Media
    {
        $media = (new Media())->forceFill([
            'mediable_type' => $type,
            'mediable_id' => $parentId,
            'uploaded_by' => $this->ownerUser->id,
            'file_path' => 'media/' . uniqid('owned_', true) . '.jpg',
            'file_name' => 'owned.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1000,
            'disk' => 'public',
            'position' => 1,
        ]);
        $media->save();

        return $media->refresh();
    }

    protected function upload($user, array $extra = [])
    {
        return $this->postJson($this->postMediaUri($this->communityA, $this->post), array_merge(['image' => $this->validImage()], $extra), $this->token($user));
    }
}
