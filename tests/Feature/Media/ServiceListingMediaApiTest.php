<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Media\app\Models\Media;

class ServiceListingMediaApiTest extends MediaTestCase
{
    // ════════════ AUTH ════════════

    public function test_anonymous_upload_is_unauthenticated(): void
    {
        $this->upload($this->ownerUser, authenticated: false)->assertStatus(401);
    }

    public function test_listing_owner_can_upload(): void
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

    public function test_wrong_community_listing_path_is_404(): void
    {
        $this->postJson($this->listingMediaUri($this->communityB, $this->listing), $this->multipart(), $this->token($this->ownerUser))
            ->assertStatus(404);
    }

    public function test_missing_listing_is_404(): void
    {
        $this->postJson($this->listingMediaUri($this->communityA, 999999), $this->multipart(), $this->token($this->ownerUser))
            ->assertStatus(404);
    }

    public function test_soft_deleted_listing_is_404(): void
    {
        $this->listing->delete();

        $this->upload($this->ownerUser)->assertStatus(404);
    }

    public function test_manager_does_not_become_listing_owner(): void
    {
        $this->upload($this->managerUser)->assertStatus(403);
    }

    public function test_provider_does_not_become_listing_owner(): void
    {
        $this->upload($this->providerUser)->assertStatus(403);
    }

    public function test_super_admin_does_not_become_listing_owner(): void
    {
        $this->upload($this->superAdmin)->assertStatus(403);
    }

    public function test_suspended_owner_cannot_upload(): void
    {
        $this->ownerResident->update(['status' => 'suspended']);

        $this->upload($this->ownerUser)->assertStatus(403);
    }

    // ════════════ VALIDATION ════════════

    public function test_valid_image_succeeds_and_stores_file(): void
    {
        $this->upload($this->ownerUser)->assertStatus(201);

        $this->assertSame(1, Media::where('mediable_type', 'service_listing')->count());
        $this->assertCount(1, Storage::disk('public')->files('media'));
    }

    public function test_oversized_image_is_422_and_writes_no_file(): void
    {
        $big = UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg');

        $this->postJson($this->listingMediaUri($this->communityA, $this->listing), $this->multipart($big), $this->token($this->ownerUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);

        $this->assertCount(0, Storage::disk('public')->files('media'));
    }

    public function test_unsupported_mime_is_422(): void
    {
        $pdf = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->postJson($this->listingMediaUri($this->communityA, $this->listing), $this->multipart($pdf), $this->token($this->ownerUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_missing_image_is_422(): void
    {
        $this->postJson($this->listingMediaUri($this->communityA, $this->listing), [], $this->token($this->ownerUser))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_authority_fields_are_ignored(): void
    {
        $response = $this->postJson(
            $this->listingMediaUri($this->communityA, $this->listing),
            array_merge($this->multipart(), [
                'mediable_type' => 'post',
                'mediable_id' => 999999,
                'community_id' => $this->communityB->id,
                'uploaded_by' => $this->outsider->id,
            ]),
            $this->token($this->ownerUser),
        )->assertStatus(201);

        $media = Media::find($response->json('data.id'));

        $this->assertSame('service_listing', $media->mediable_type);
        $this->assertSame($this->listing->id, (int) $media->mediable_id);
        $this->assertSame($this->ownerUser->id, (int) $media->uploaded_by);
    }

    // ════════════ LIMIT ════════════

    public function test_sixth_image_rejected(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->upload($this->ownerUser)->assertStatus(201);
        }

        $this->upload($this->ownerUser)->assertStatus(422)->assertJsonValidationErrors(['media']);

        $this->assertSame(5, Media::where('mediable_type', 'service_listing')->count());
    }

    // ════════════ READ ════════════

    public function test_show_includes_ordered_media(): void
    {
        $this->upload($this->ownerUser, ['position' => 2])->assertStatus(201);
        $this->upload($this->ownerUser, ['position' => 1])->assertStatus(201);

        $payload = $this->getJson("/api/v1/communities/{$this->communityA->id}/service-listings/{$this->listing->id}", $this->token($this->ownerUser))
            ->assertStatus(200)
            ->json('data.media');

        $this->assertSame([1, 2], array_column($payload, 'position'));
    }

    // ════════════ DELETE ════════════

    public function test_owner_can_delete_media(): void
    {
        $id = $this->upload($this->ownerUser)->assertStatus(201)->json('data.id');

        $this->deleteJson($this->deleteMediaUri($this->communityA, $id), [], $this->token($this->ownerUser))
            ->assertStatus(200);

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

    // ════════════ REGRESSION: Media must not alter listing state ════════════

    public function test_upload_does_not_change_status_or_expiry(): void
    {
        $status = $this->listing->status;
        $expires = $this->listing->getRawOriginal('expires_at');
        $closed = $this->listing->closed_at;

        $this->upload($this->ownerUser)->assertStatus(201);

        $fresh = $this->listing->fresh();

        $this->assertSame($status, $fresh->status);
        $this->assertSame($expires, $fresh->getRawOriginal('expires_at'));
        $this->assertSame($closed ? $closed->toJson() : null, $fresh->closed_at ? $fresh->closed_at->toJson() : null);
    }

    public function test_delete_does_not_change_status(): void
    {
        $id = $this->upload($this->ownerUser)->assertStatus(201)->json('data.id');
        $status = $this->listing->fresh()->status;

        $this->deleteJson($this->deleteMediaUri($this->communityA, $id), [], $this->token($this->ownerUser))->assertStatus(200);

        $this->assertSame($status, $this->listing->fresh()->status);
    }

    protected function upload($user, array $extra = [], bool $authenticated = true)
    {
        $headers = $authenticated ? $this->token($user) : [];

        return $this->postJson($this->listingMediaUri($this->communityA, $this->listing), $this->multipart($this->validImage(), $extra), $headers);
    }

    protected function multipart(?UploadedFile $image = null, array $extra = []): array
    {
        return array_merge(['image' => $image ?? $this->validImage()], $extra);
    }
}
