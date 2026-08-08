<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use Modules\Media\app\Services\MediaStorage;

/**
 * MEDIA-1 hardening: proves the FIRST authorization runs before any
 * filesystem write, so unauthorized actors cannot force MediaStorage::store.
 *
 * A Mockery spy on MediaStorage is used (not final filesystem state) so the
 * proof is the absence of the store call itself, not merely a clean disk.
 */
class MediaUploadAuthorizationTest extends MediaTestCase
{
    public function test_non_owner_post_upload_does_not_store(): void
    {
        $storage = $this->spy(MediaStorage::class);

        $this->uploadPost($this->secondUser)->assertStatus(403);

        $storage->shouldNotHaveReceived('store');
    }

    public function test_non_owner_service_listing_upload_does_not_store(): void
    {
        $storage = $this->spy(MediaStorage::class);

        $this->uploadListing($this->secondUser)->assertStatus(403);

        $storage->shouldNotHaveReceived('store');
    }

    public function test_cross_community_upload_does_not_store(): void
    {
        $storage = $this->spy(MediaStorage::class);

        $this->postJson($this->postMediaUri($this->communityB, $this->post), $this->postMultipart(), $this->token($this->ownerUser))
            ->assertStatus(404);

        $storage->shouldNotHaveReceived('store');
    }

    public function test_soft_deleted_post_upload_does_not_store(): void
    {
        $this->post->delete();

        $storage = $this->spy(MediaStorage::class);

        $this->uploadPost($this->ownerUser)->assertStatus(404);

        $storage->shouldNotHaveReceived('store');
    }

    public function test_missing_parent_upload_does_not_store(): void
    {
        $storage = $this->spy(MediaStorage::class);

        $this->postJson($this->postMediaUri($this->communityA, 999999), $this->postMultipart(), $this->token($this->ownerUser))
            ->assertStatus(404);

        $storage->shouldNotHaveReceived('store');
    }

    public function test_suspended_service_listing_owner_does_not_store(): void
    {
        $this->ownerResident->update(['status' => 'suspended']);

        $storage = $this->spy(MediaStorage::class);

        $this->uploadListing($this->ownerUser)->assertStatus(403);

        $storage->shouldNotHaveReceived('store');
    }

    public function test_outsider_post_upload_does_not_store(): void
    {
        $storage = $this->spy(MediaStorage::class);

        $this->uploadPost($this->outsider)->assertStatus(403);

        $storage->shouldNotHaveReceived('store');
    }

    protected function uploadPost($user)
    {
        return $this->postJson($this->postMediaUri($this->communityA, $this->post), $this->postMultipart(), $this->token($user));
    }

    protected function uploadListing($user)
    {
        return $this->postJson($this->listingMediaUri($this->communityA, $this->listing), $this->postMultipart(), $this->token($user));
    }

    protected function postMultipart(): array
    {
        return ['image' => $this->validImage()];
    }
}
