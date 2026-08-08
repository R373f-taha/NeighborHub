<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Media\app\Models\Media;

class PostMediaApiTest extends MediaTestCase
{
    // ════════════ AUTH ════════════

    public function test_anonymous_upload_is_unauthenticated(): void
    {
        $this->postJson($this->postMediaUri($this->communityA, $this->post))->assertStatus(401);
    }

    public function test_post_owner_can_upload(): void
    {
        $this->upload($this->ownerUser)->assertStatus(201)->assertJsonPath('message', 'Media uploaded successfully.');
    }

    public function test_non_owner_cannot_upload(): void
    {
        $this->upload($this->secondUser)->assertStatus(403);
    }

    public function test_outsider_cannot_upload(): void
    {
        $this->upload($this->outsider)->assertStatus(403);
    }

    public function test_wrong_community_post_path_is_404(): void
    {
        $this->postJson($this->postMediaUri($this->communityB, $this->post), $this->multipart(), $this->token($this->ownerUser))
            ->assertStatus(404);
    }

    public function test_missing_post_is_404(): void
    {
        $this->postJson($this->postMediaUri($this->communityA, 999999), $this->multipart(), $this->token($this->ownerUser))
            ->assertStatus(404);
    }

    public function test_soft_deleted_post_is_404(): void
    {
        $this->post->delete();

        $this->upload($this->ownerUser)->assertStatus(404);
    }

    public function test_manager_does_not_become_post_owner(): void
    {
        $this->upload($this->managerUser)->assertStatus(403);
    }

    public function test_super_admin_does_not_become_post_owner(): void
    {
        $this->upload($this->superAdmin)->assertStatus(403);
    }

    // ════════════ VALIDATION ════════════

    public function test_valid_image_succeeds_and_stores_file(): void
    {
        $this->upload($this->ownerUser)->assertStatus(201);

        $this->assertSame(1, Media::where('mediable_type', 'post')->count());
        $this->assertCount(1, Storage::disk('public')->files('media'));
    }

    public function test_oversized_image_is_422_and_writes_no_file(): void
    {
        $big = UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg');

        $this->postJson($this->postMediaUri($this->communityA, $this->post), $this->multipart($big), $this->token($this->ownerUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);

        $this->assertCount(0, Storage::disk('public')->files('media'));
    }

    public function test_unsupported_mime_is_422(): void
    {
        $pdf = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->postJson($this->postMediaUri($this->communityA, $this->post), $this->multipart($pdf), $this->token($this->ownerUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_renamed_non_image_is_rejected(): void
    {
        $fake = UploadedFile::fake()->create('fake.jpg', 100, 'application/pdf');

        $this->postJson($this->postMediaUri($this->communityA, $this->post), $this->multipart($fake), $this->token($this->ownerUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_missing_image_is_422(): void
    {
        $this->postJson($this->postMediaUri($this->communityA, $this->post), [], $this->token($this->ownerUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_authority_fields_are_ignored(): void
    {
        $response = $this->postJson(
            $this->postMediaUri($this->communityA, $this->post),
            array_merge($this->multipart(), [
                'mediable_type' => 'service_listing',
                'mediable_id' => 999999,
                'community_id' => $this->communityB->id,
                'uploaded_by' => $this->outsider->id,
            ]),
            $this->token($this->ownerUser),
        )->assertStatus(201);

        $media = Media::find($response->json('data.id'));

        $this->assertSame('post', $media->mediable_type);
        $this->assertSame($this->post->id, (int) $media->mediable_id);
        $this->assertSame($this->communityA->id, (int) $this->post->fresh()->community_id);
        $this->assertSame($this->ownerUser->id, (int) $media->uploaded_by);
    }

    // ════════════ LIMIT ════════════

    public function test_first_through_fifth_allowed_sixth_rejected(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->upload($this->ownerUser)->assertStatus(201);
        }

        $this->assertSame(5, Media::where('mediable_type', 'post')->count());

        $this->upload($this->ownerUser)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['media']);

        $this->assertSame(5, Media::where('mediable_type', 'post')->count());
        $this->assertCount(5, Storage::disk('public')->files('media'));
    }

    public function test_explicit_position_collision_is_422(): void
    {
        $this->upload($this->ownerUser, ['position' => 1])->assertStatus(201);

        $this->upload($this->ownerUser, ['position' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['position']);
    }

    public function test_positions_allocated_sequentially_when_omitted(): void
    {
        $r1 = $this->upload($this->ownerUser)->assertStatus(201)->json('data.position');
        $r2 = $this->upload($this->ownerUser)->assertStatus(201)->json('data.position');

        $this->assertSame(1, $r1);
        $this->assertSame(2, $r2);
    }

    // ════════════ RESOURCE ════════════

    public function test_post_response_exposes_ordered_media_safely(): void
    {
        $this->upload($this->ownerUser, ['position' => 2])->assertStatus(201);
        $this->upload($this->ownerUser, ['position' => 1])->assertStatus(201);

        $json = $this->getJson("/api/v1/communities/{$this->communityA->id}/posts/{$this->post->id}", $this->token($this->ownerUser))
            ->assertStatus(200)
            ->getContent();

        $payload = json_decode($json, true);

        $this->assertCount(2, $payload['data']['media']);
        $this->assertSame([1, 2], array_column($payload['data']['media'], 'position'));
        $this->assertStringContainsString('url', $json);

        foreach (['file_path', 'disk', 'uploaded_by', 'mediable_type', 'password', 'email'] as $leak) {
            $this->assertStringNotContainsString('"'.$leak.'"', $json, "must not leak $leak");
        }
    }

    // ════════════ DELETE ════════════

    public function test_owner_can_delete_own_media(): void
    {
        $id = $this->upload($this->ownerUser)->assertStatus(201)->json('data.id');

        $this->deleteJson($this->deleteMediaUri($this->communityA, $id), [], $this->token($this->ownerUser))
            ->assertStatus(200)
            ->assertJson(['message' => 'Media deleted successfully.', 'data' => null]);

        $this->assertNull(Media::find($id));
        $this->assertCount(0, Storage::disk('public')->files('media'));
    }

    public function test_non_owner_cannot_delete(): void
    {
        $id = $this->upload($this->ownerUser)->assertStatus(201)->json('data.id');

        $this->deleteJson($this->deleteMediaUri($this->communityA, $id), [], $this->token($this->secondUser))
            ->assertStatus(403);
    }

    public function test_cross_community_delete_is_404(): void
    {
        $id = $this->upload($this->ownerUser)->assertStatus(201)->json('data.id');

        $this->deleteJson($this->deleteMediaUri($this->communityB, $id), [], $this->token($this->outsider))
            ->assertStatus(404);
    }

    public function test_delete_missing_media_is_404(): void
    {
        $this->deleteJson($this->deleteMediaUri($this->communityA, 999999), [], $this->token($this->ownerUser))
            ->assertStatus(404);
    }

    public function test_anonymous_delete_is_401(): void
    {
        $this->deleteJson($this->deleteMediaUri($this->communityA, 1))->assertStatus(401);
    }

    protected function upload($user, array $extra = [])
    {
        return $this->postJson($this->postMediaUri($this->communityA, $this->post), $this->multipart($this->validImage(), $extra), $this->token($user));
    }

    protected function multipart(?UploadedFile $image = null, array $extra = []): array
    {
        return array_merge(['image' => $image ?? $this->validImage()], $extra);
    }
}
