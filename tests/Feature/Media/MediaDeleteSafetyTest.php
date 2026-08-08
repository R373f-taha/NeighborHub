<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Issue\app\Models\Issue;
use Modules\Media\app\Jobs\CleanupMediaFile;
use Modules\Media\app\Models\Media;
use Modules\Media\app\Services\MediaStorage;
use Modules\Post\app\Models\Post;

/**
 * MEDIA-1 hardening: proves the delete path is DB-authoritative, serializes
 * parent-first, and never lets a DB rollback follow a filesystem deletion.
 */
class MediaDeleteSafetyTest extends MediaTestCase
{
    /**
     * A. Unauthorized delete: no DB mutation and no Storage delete attempt.
     */
    public function test_unauthorized_delete_makes_no_storage_attempt(): void
    {
        $id = $this->upload($this->ownerUser)->assertStatus(201)->json('data.id');

        $storage = $this->spy(MediaStorage::class);

        $this->deleteJson($this->deleteMediaUri($this->communityA, $id), [], $this->token($this->secondUser))
            ->assertStatus(403);

        $storage->shouldNotHaveReceived('delete');
        $this->assertNotNull(Media::find($id));
    }

    /**
     * B. Wrong Community: 404, no DB mutation, no Storage delete attempt.
     */
    public function test_cross_community_delete_makes_no_storage_attempt(): void
    {
        $id = $this->upload($this->ownerUser)->assertStatus(201)->json('data.id');

        $storage = $this->spy(MediaStorage::class);

        $this->deleteJson($this->deleteMediaUri($this->communityB, $id), [], $this->token($this->outsider))
            ->assertStatus(404);

        $storage->shouldNotHaveReceived('delete');
        $this->assertNotNull(Media::find($id));
    }

    /**
     * C. Missing Media: 404.
     */
    public function test_missing_media_is_404(): void
    {
        $this->deleteJson($this->deleteMediaUri($this->communityA, 999999), [], $this->token($this->ownerUser))
            ->assertStatus(404);
    }

    /**
     * D. DB transaction failure BEFORE COMMIT: the Media row and its file
     * both survive, and the storage deletion was never attempted (a
     * rollback cannot follow a file deletion because the file is only
     * touched post-commit).
     */
    public function test_db_failure_before_commit_preserves_file_and_row(): void
    {
        $id = $this->upload($this->ownerUser)->assertStatus(201)->json('data.id');
        $path = Media::find($id)->file_path;

        $storage = $this->spy(MediaStorage::class);

        Media::deleting(function (): void {
            throw new \RuntimeException('simulated DB failure');
        });

        try {
            $this->withoutExceptionHandling();
            $this->deleteJson($this->deleteMediaUri($this->communityA, $id), [], $this->token($this->ownerUser));
            $this->fail('Expected the delete to throw during the DB phase.');
        } catch (\RuntimeException) {
            // expected: the DB phase failed before commit
        } finally {
            Media::getEventDispatcher()->forget('eloquent.deleting: ' . Media::class);
        }

        $storage->shouldNotHaveReceived('delete');
        $this->assertNotNull(Media::find($id));
        $this->assertTrue(Storage::disk('public')->exists($path));
    }

    /**
     * E. Successful DB delete + successful storage cleanup: row and file gone.
     */
    public function test_successful_delete_removes_row_and_file(): void
    {
        $id = $this->upload($this->ownerUser)->assertStatus(201)->json('data.id');
        $path = Media::find($id)->file_path;
        $this->assertTrue(Storage::disk('public')->exists($path));

        $this->deleteJson($this->deleteMediaUri($this->communityA, $id), [], $this->token($this->ownerUser))
            ->assertStatus(200)
            ->assertJson(['message' => 'Media deleted successfully.', 'data' => null]);

        $this->assertNull(Media::find($id));
        $this->assertFalse(Storage::disk('public')->exists($path));
    }

    /**
     * F. Successful DB delete + storage cleanup failure: the row is still gone
     * (DB authoritative), the recovery job is invoked, and no internals leak.
     */
    public function test_db_delete_succeeds_but_storage_failure_schedules_cleanup(): void
    {
        Queue::fake();

        $id = $this->upload($this->ownerUser)->assertStatus(201)->json('data.id');
        $path = Media::find($id)->file_path;

        // Bind a storage whose delete fails post-commit.
        $storage = \Mockery::mock(MediaStorage::class);
        $storage->shouldReceive('delete')->with($path, 'public')->andReturnFalse();
        $this->app->instance(MediaStorage::class, $storage);

        $response = $this->deleteJson($this->deleteMediaUri($this->communityA, $id), [], $this->token($this->ownerUser))
            ->assertStatus(200);

        $this->assertNull(Media::find($id)); // DB row gone; cleanup is eventual
        $this->assertStringNotContainsString($path, $response->getContent());
        Queue::assertPushed(CleanupMediaFile::class, fn (CleanupMediaFile $job) => $job->path === $path && $job->disk === 'public');
    }

    /**
     * G. Missing storage file: the DB deletion still completes idempotently
     * and no recovery job is scheduled (absent file is success).
     */
    public function test_missing_storage_file_is_handled_safely(): void
    {
        Queue::fake();

        $id = $this->upload($this->ownerUser)->assertStatus(201)->json('data.id');
        $path = Media::find($id)->file_path;

        Storage::disk('public')->delete($path);
        $this->assertFalse(Storage::disk('public')->exists($path));

        $this->deleteJson($this->deleteMediaUri($this->communityA, $id), [], $this->token($this->ownerUser))
            ->assertStatus(200);

        $this->assertNull(Media::find($id));
        Queue::assertNotPushed(CleanupMediaFile::class);
    }

    /**
     * Morph tamper: an unknown / PHP-class-name / issue / malformed
     * mediable_type is rejected with a privacy-safe 404, with no arbitrary
     * class instantiation, no storage attempt, and no DB mutation.
     *
     * @dataProvider tamperedTypes
     */
    public function test_tampered_mediable_type_is_privacy_safe_404(string $type): void
    {
        $storage = $this->spy(MediaStorage::class);

        $media = $this->makeMedia($type);

        $this->deleteJson($this->deleteMediaUri($this->communityA, $media->id), [], $this->token($this->ownerUser))
            ->assertStatus(404);

        $storage->shouldNotHaveReceived('delete');
        $this->assertNotNull(Media::find($media->id));
    }

    /** @return array<string, array{string}> */
    public static function tamperedTypes(): array
    {
        return [
            'unknown alias' => ['some_other_type'],
            'php class name' => [Post::class],
            'issue alias' => ['issue'],
            'issue class name' => [Issue::class],
            'issue_update alias' => ['issue_update'],
            'malformed' => ['../etc/passwd'],
        ];
    }

    private function makeMedia(string $type): Media
    {
        $media = (new Media())->forceFill([
            'mediable_type' => $type,
            'mediable_id' => $this->post->id,
            'uploaded_by' => $this->ownerUser->id,
            'file_path' => 'media/' . uniqid('tamper_', true) . '.jpg',
            'file_name' => 'tamper.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1000,
            'disk' => 'public',
            'position' => 99,
        ]);
        $media->save();

        return $media->refresh();
    }

    protected function upload($user, array $extra = [])
    {
        return $this->postJson($this->postMediaUri($this->communityA, $this->post), array_merge(['image' => $this->validImage()], $extra), $this->token($user));
    }
}
