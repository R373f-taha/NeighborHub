<?php

declare(strict_types=1);

namespace Modules\Media\app\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Media\app\Services\MediaStorage;

/**
 * Bounded, idempotent cleanup of a single orphaned Media file after the
 * owning Media row has already been deleted from the database. The payload
 * carries only the minimum storage descriptor (path + disk): no tokens, user
 * identity, request data, or parent content.
 *
 * Idempotent: a file that is already absent is treated as success. A genuine
 * deletion failure throws so the queue retries per normal backoff behavior.
 */
class CleanupMediaFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $backoff = 60;

    public function __construct(
        public readonly string $path,
        public readonly string $disk,
    ) {}

    public function handle(MediaStorage $storage): void
    {
        // Idempotent: already-absent is success, nothing to clean.
        if (! Storage::disk($this->disk)->exists($this->path)) {
            return;
        }

        if (! $storage->delete($this->path, $this->disk)) {
            throw new \RuntimeException('Media file cleanup failed; will retry.');
        }
    }

    /**
     * Terminal failure after exhausting retries. Logged once (not per retry)
     * with safe fields only; the storage path is never logged.
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel('security')->warning('media.cleanup_failed', [
            'disk' => $this->disk,
            'action' => 'cleanup',
            'result' => 'abandoned',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
