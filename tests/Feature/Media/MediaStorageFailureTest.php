<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Media\app\Models\Media;

class MediaStorageFailureTest extends MediaTestCase
{
    public function test_validation_failure_writes_no_file(): void
    {
        $pdf = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $this->postJson($this->postMediaUri($this->communityA, $this->post), ['image' => $pdf], $this->token($this->ownerUser))
            ->assertStatus(422);

        $this->assertCount(0, Storage::disk('public')->files('media'));
    }

    public function test_unauthorized_upload_writes_no_file(): void
    {
        $this->upload($this->secondUser)->assertStatus(403);

        $this->assertCount(0, Storage::disk('public')->files('media'));
        $this->assertSame(0, Media::count());
    }

    public function test_sixth_image_rejection_writes_no_file(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->upload($this->ownerUser)->assertStatus(201);
        }

        $this->upload($this->ownerUser)->assertStatus(422);

        // Five real files, no orphan from the rejected sixth.
        $this->assertCount(5, Storage::disk('public')->files('media'));
    }

    public function test_db_failure_after_store_removes_orphan_file(): void
    {
        // The file is stored before the DB phase. If the DB phase then fails,
        // the stored file must be removed (compensation) and no row left.
        Storage::fake('public');

        $service = app(\Modules\Media\app\Services\MediaService::class);

        try {
            $service->attach(
                $this->post,
                'post',
                $this->ownerUser,
                $this->validImage(),
                null,
                fn () => throw new \RuntimeException('simulated DB failure'),
            );
            $this->fail('Expected the attach to throw.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertCount(0, Storage::disk('public')->files('media'));
        $this->assertSame(0, Media::count());
    }

    public function test_delete_removes_file_and_row(): void
    {
        $id = $this->upload($this->ownerUser)->assertStatus(201)->json('data.id');

        $this->deleteJson($this->deleteMediaUri($this->communityA, $id), [], $this->token($this->ownerUser))
            ->assertStatus(200);

        $this->assertNull(Media::find($id));
        $this->assertCount(0, Storage::disk('public')->files('media'));
    }

    public function test_issue_media_delete_is_privacy_safe_404(): void
    {
        // A media row attached to an Issue parent is not owned by this
        // integration (Issue media is team-owned/deferred). It must fail with a
        // privacy-safe 404 (never 403) so the endpoint does not disclose that
        // the guessed Media id exists or that it belongs to an unsupported
        // parent. No DB/file change.
        $media = (new Media())->forceFill([
            'mediable_type' => 'issue',
            'mediable_id' => 999999,
            'uploaded_by' => $this->ownerUser->id,
            'file_path' => 'media/issue.jpg',
            'file_name' => 'issue.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1000,
            'disk' => 'public',
            'position' => 1,
        ]);
        $media->save();

        $this->deleteJson($this->deleteMediaUri($this->communityA, $media->id), [], $this->token($this->ownerUser))
            ->assertStatus(404);

        $this->assertNotNull(Media::find($media->id));
    }

    protected function upload($user, array $extra = [])
    {
        return $this->postJson($this->postMediaUri($this->communityA, $this->post), array_merge(['image' => $this->validImage()], $extra), $this->token($user));
    }
}
