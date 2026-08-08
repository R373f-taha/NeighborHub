<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\app\Models\User;
use Modules\Media\app\Models\Media;

/**
 * MEDIA upload security invariants: server-generated filename, content-based
 * allowlist, SVG/executable rejection, and no-persist-on-reject.
 *
 * Each test asserts the security invariant rather than an exact random name.
 */
class MediaUploadSecurityTest extends MediaTestCase
{
    // ════════════ §12 SERVER-GENERATED FILENAME / DOUBLE EXTENSION ════════════

    public function test_double_extension_client_filename_is_not_persisted_as_storage_name(): void
    {
        $image = UploadedFile::fake()->image('my.photo.php.jpg', 80, 60);

        $id = $this->upload($this->ownerUser, $image)->assertStatus(201)->json('data.id');

        $media = Media::findOrFail($id);
        $path = $media->file_path;
        $basename = basename($path);

        // Storage lives under the fixed, server-controlled media/ directory.
        $this->assertStringStartsWith('media/', $path);

        // The attacker-chosen name (with double/dot extension) is never the
        // stored basename and carries no executable segment or traversal.
        $this->assertNotSame('my.photo.php.jpg', $basename);
        $this->assertStringNotContainsString('php', $basename);
        $this->assertStringNotContainsString('..', $path);
        $this->assertStringNotContainsString('/', $basename);

        // The stored file physically exists at the server-generated path.
        $this->assertTrue(Storage::disk('public')->exists($path));

        // The original client name survives only as non-authoritative metadata.
        $this->assertSame('my.photo.php.jpg', $media->file_name);
    }

    // ════════════ §14 VALID IMAGE, SUSPICIOUS NAME ════════════

    public function test_valid_image_with_suspicious_name_succeeds_and_name_does_not_control_storage(): void
    {
        $image = UploadedFile::fake()->image('avatar.jpg.php.png', 80, 60);

        $id = $this->upload($this->ownerUser, $image)->assertStatus(201)->json('data.id');

        $media = Media::findOrFail($id);
        $basename = basename($media->file_path);

        // Accepted because the content is a valid image; the client filename
        // never influences the persisted name.
        $this->assertNotSame('avatar.jpg.php.png', $basename);
        $this->assertStringNotContainsString('php', $basename);
        $this->assertSame('image/png', $media->mime_type);
    }

    // ════════════ §13 ALLOWED RASTER FORMATS ════════════

    public function test_png_webp_gif_are_accepted(): void
    {
        foreach (['ok.png', 'ok.webp', 'ok.gif'] as $name) {
            $this->upload($this->ownerUser, UploadedFile::fake()->image($name, 60, 60))
                ->assertStatus(201);
        }

        $this->assertSame(3, Media::where('mediable_type', 'post')->count());
        $this->assertCount(3, Storage::disk('public')->files('media'));
    }

    // ════════════ §7 / §13 SVG REJECTED ════════════

    public function test_svg_is_rejected_and_writes_no_file(): void
    {
        $svg = UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml');

        $this->upload($this->ownerUser, $svg)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);

        $this->assertCount(0, Storage::disk('public')->files('media'));
        $this->assertSame(0, Media::count());
    }

    // ════════════ §13 NON-IMAGE / TEXT REJECTED ════════════

    public function test_php_text_and_pdf_are_rejected_and_write_no_file(): void
    {
        $cases = [
            'shell.php' => $this->realUpload('shell.php', '<?php echo 1; ?>'),
            'notes.txt' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
            'doc.pdf' => UploadedFile::fake()->create('doc.pdf', 1, 'application/pdf'),
        ];

        foreach ($cases as $label => $file) {
            $this->upload($this->ownerUser, $file)
                ->assertStatus(422)
                ->assertJsonValidationErrors(['image']);

            $this->assertCount(0, Storage::disk('public')->files('media'), "$label wrote a file");
            $this->assertSame(0, Media::count(), "$label created a media row");
        }
    }

    // ════════════ §8 / §14 CONTENT-BASED DETECTION (DEFEATS RENAMING) ════════════

    public function test_renamed_php_content_is_rejected_by_content(): void
    {
        // Real PHP bytes renamed to .jpg with a spoofed image/jpeg client claim;
        // validation must inspect the content, not the extension/claim.
        $file = $this->realUpload('shell.jpg', '<?php echo "pwned"; ?>', 'image/jpeg');
        $this->assertSame('text/x-php', $file->getMimeType());

        $this->upload($this->ownerUser, $file)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);

        $this->assertCount(0, Storage::disk('public')->files('media'));
        $this->assertSame(0, Media::count());
    }

    public function test_renamed_non_image_binary_is_rejected_by_content(): void
    {
        $file = $this->realUpload('image.jpg', str_repeat("\x00\x01\x02\x03", 500), 'image/jpeg');

        $this->upload($this->ownerUser, $file)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);

        $this->assertCount(0, Storage::disk('public')->files('media'));
        $this->assertSame(0, Media::count());
    }

    // ════════════ §9 / §15 OVERSIZED WRITES NO FILE ════════════

    public function test_oversized_image_is_422_and_writes_no_file(): void
    {
        $big = UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg');

        $this->upload($this->ownerUser, $big)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);

        $this->assertCount(0, Storage::disk('public')->files('media'));
        $this->assertSame(0, Media::count());
    }

    // ════════════ helpers ════════════

    /**
     * Build a REAL UploadedFile backed by actual bytes on disk so validation's
     * getMimeType() performs finfo content inspection (mirrors a genuine upload),
     * not the testing fake's declared MIME.
     */
    private function realUpload(string $originalName, string $content, string $clientMime = 'application/octet-stream'): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'media_sec_');
        file_put_contents($tempPath, $content);

        register_shutdown_function(static function () use ($tempPath): void {
            @unlink($tempPath);
        });

        return new UploadedFile($tempPath, $originalName, $clientMime, null, true);
    }

    protected function upload(User $user, UploadedFile $image)
    {
        return $this->postJson(
            $this->postMediaUri($this->communityA, $this->post),
            ['image' => $image],
            $this->token($user),
        );
    }
}
