<?php

declare(strict_types=1);

namespace Modules\Media\app\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaStorage
{
    public const string DISK = 'public';

    private const string DIRECTORY = 'media';

    /**
     * Store the uploaded image under a server-generated unique path. The
     * original client filename is never used as the storage name (no path
     * traversal / overwrite-by-name), but is retained sanitized as metadata.
     *
     * @return array{path: string, file_name: string, mime_type: string, file_size: int, disk: string}
     */
    public function store(UploadedFile $file): array
    {
        $disk = Storage::disk(self::DISK);

        // hashName() yields a unique 40-char name carrying the file's real
        // extension, which we already validated via MIME inspection.
        $path = $disk->putFile(self::DIRECTORY, $file);

        return [
            'path' => $path,
            'file_name' => Str::ascii($file->getClientOriginalName()) ?? basename($path),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'disk' => self::DISK,
        ];
    }

    /**
     * Idempotent file removal: returns false when the file is already absent,
     * which is treated as success by callers (a prior partial delete may have
     * removed the bytes already).
     */
    public function delete(string $path, string $disk): bool
    {
        return Storage::disk($disk)->delete($path);
    }

    public function url(string $path, string $disk): string
    {
        return Storage::disk($disk)->url($path);
    }
}
