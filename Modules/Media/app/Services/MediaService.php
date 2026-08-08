<?php

declare(strict_types=1);

namespace Modules\Media\app\Services;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Auth\app\Models\User;
use Modules\Media\app\Exceptions\MediaLimitExceededException;
use Modules\Media\app\Exceptions\MediaPositionConflictException;
use Modules\Media\app\Jobs\CleanupMediaFile;
use Modules\Media\app\Models\Media;
use Modules\Media\app\Support\MediaParentType;

class MediaService
{
    public function __construct(
        private readonly MediaStorage $storage,
        private readonly MediaLogger $logger,
    ) {}

    /**
     * Attach an image to a parent.
     *
     * Two-phase to avoid holding the DB parent lock during filesystem I/O:
     *   1. Store the file (slow) outside the transaction.
     *   2. Serialize on the parent row FOR UPDATE: re-authorize under the
     *      lock, enforce the five-image ceiling, allocate a position, and
     *      insert the Media row. The parent lock is the serialization point
     *      that makes concurrent uploads observe a consistent count.
     *
     * Compensation: if the DB phase fails after the file was stored, the
     * stored file is removed so no orphan remains.
     *
     * @param Closure(): bool $authorizeUnderLock re-evaluation of parent
     *        ownership against freshly locked state.
     */
    public function attach(
        Model $parent,
        string $alias,
        User $uploader,
        UploadedFile $file,
        ?int $position,
        Closure $authorizeUnderLock,
    ): Media {
        $stored = $this->storage->store($file);

        try {
            $media = DB::transaction(function () use ($parent, $alias, $uploader, $position, $stored, $authorizeUnderLock): Media {
                $this->lockParent($parent);

                // Re-check authorization after acquiring the parent lock.
                if (! $authorizeUnderLock()) {
                    throw new AuthorizationException();
                }

                $existing = $this->parentPositions($alias, $parent);//get all the positions of the existing media for this parent

                if (count($existing) >= Media::MAX_PER_PARENT) {
                    throw MediaLimitExceededException::forParent(Media::MAX_PER_PARENT);
                }

                $assigned = $this->allocatePosition($existing, $position);

                // Create through the morph relation so the polymorphic owner
                // columns are set server-side from the trusted parent and are
                // never mass-assignable from request data.
                return $parent->media()->create([
                    'file_path' => $stored['path'],
                    'file_name' => $stored['file_name'],
                    'mime_type' => $stored['mime_type'],
                    'file_size' => $stored['file_size'],
                    'disk' => $stored['disk'],
                    'position' => $assigned,
                    'uploaded_by' => $uploader->id,
                ]);
            });
        } catch (\Throwable $e) {
            // Compensation: the DB phase failed after the file was stored,
            // so remove the orphaned file and surface the original failure.
            $this->storage->delete($stored['path'], $stored['disk']);
            throw $e;
        }

        $this->logger->uploaded($uploader, (int) $parent->community_id, (int) $media->id, $alias, (int) $parent->getKey());

        return $media->fresh();
    }

    /**
     * Delete a Media row keeping the database as the authoritative lifecycle.
     *
     * Lock order is parent-first to match upload/reorder: the parent row is
     * locked FOR UPDATE, then the Media row is re-locked and re-verified to
     * still belong to that parent. The Media DB row is deleted and committed
     * BEFORE the file is touched, because a filesystem deletion cannot be
     * rolled back by the DB transaction. Filesystem cleanup runs post-commit;
     * if it fails, an orphan-only recovery job is scheduled.
     */
    public function delete(Media $media, Model $parent, string $alias): void
    {
        $descriptor = DB::transaction(function () use ($media, $parent, $alias): object {
            $locked = $this->lockParent($parent);

            $row = Media::whereKey($media->id)->lockForUpdate()->first();

            if ($row === null) {
                abort(404);
            }

            if ($row->mediable_type !== $alias || (int) $row->mediable_id !== (int) $locked->getKey()) {
                abort(404);
            }

            // Immutable cleanup descriptor captured under the lock; the file is
            // NOT deleted here so a later rollback leaves the row valid.
            $descriptor = (object) [
                'path' => $row->file_path,
                'disk' => $row->disk,
                'media_id' => (int) $row->id,
                'alias' => $alias,
                'parent_id' => (int) $locked->getKey(),
                'community_id' => (int) $locked->community_id,
            ];

            $row->delete();

            return $descriptor;
        });

        $this->cleanupAfterCommit($descriptor);
    }

    /**
     * Reorder all images of one parent to the requested positions.
     *
     * The items must fully describe the new ordering of every current image
     * (a permutation of 1..N). Two phases avoid transient unique-constraint
     * collisions: positions are first moved to a scratch range above the
     * allowed maximum, then set to their final targets. The parent row is
     * locked so no concurrent attach/delete/reorder can interleave.
     *
     * @param array<int, array{id: int, position: int}> $items
     */
    public function reorder(Model $parent, string $alias, array $items): void
    {
        DB::transaction(function () use ($parent, $alias, $items): void {
            $this->lockParent($parent);

            $rows = Media::query()
                ->where('mediable_type', $alias)
                ->where('mediable_id', $parent->getKey())
                ->lockForUpdate()
                ->get();

            $byId = $rows->keyBy('id');

            if (count($items) !== $rows->count()) {
                abort(422, 'The reorder items must describe every image of this parent.');
            }

            $positions = [];
            $ids = [];

            foreach ($items as $item) {
                if (! isset($byId[$item['id']])) {
                    abort(422, 'Every media id must belong to this parent.');
                }
                $ids[(int) $item['id']] = true;
                $positions[] = (int) $item['position'];
            }

            if (count($ids) !== count($items)) {
                abort(422, 'Media ids must be distinct.');
            }

            $sorted = array_values($positions);
            sort($sorted);

            if ($sorted !== range(1, $rows->count())) {
                abort(422, 'Positions must be a distinct permutation of 1..N.');
            }

            // Phase A: move every row into a scratch range above MAX_POSITION
            // so no two rows share a value while we reshape the ordering.
            foreach ($rows as $row) {
                $row->position = $row->position + Media::MAX_POSITION;
                $row->save();
            }

            // Phase B: assign final targets.
            foreach ($items as $item) {
                $row = $byId[$item['id']];
                $row->position = $item['position'];
                $row->save();
            }
        });
    }

    /**
     * Derive the parent model and its community from stored Media state, to
     * authorize deletes without trusting any client-supplied parent type/id.
     *
     * Only the owned aliases are resolvable; everything else (unknown alias,
     * full PHP class name, Issue/IssueUpdate, malformed value) fails with a
     * privacy-safe 404 so the endpoint never instantiates or authorizes an
     * arbitrary model class and never discloses that an unsupported parent
     * exists.
     */
    public function resolveContext(Media $media): MediaContext //it takes a media object and returns the context of the parent model and its community
    {
        $class = MediaParentType::map()[$media->mediable_type] ?? null;//convert alias to class name

        if ($class === null) {
            abort(404);
        }

        return $this->contextFor($class, (int) $media->mediable_id);
    }

    /**
     * Filesystem cleanup runs after commit because filesystem operations
     * cannot participate in the DB transaction. The row is already deleted,
     * so a failure here cannot leave a row pointing at a file this request
     * deleted; it can only leave an orphan file, which the recovery job
     * removes idempotently.
     */
    private function cleanupAfterCommit(object $descriptor): void
    {
        if ($this->storage->delete($descriptor->path, $descriptor->disk)) {
            return;
        }

        $this->logger->cleanupFailed($descriptor);

        CleanupMediaFile::dispatch($descriptor->path, $descriptor->disk);//try later
    }

    /**
     * @return list<int>
     */
    private function parentPositions(string $alias, Model $parent): array
    {
        return Media::query()
            ->where('mediable_type', $alias)
            ->where('mediable_id', $parent->getKey())
            ->pluck('position')
            ->all();
    }

    /**
     * @param list<int> $taken
     */
    private function allocatePosition(array $taken, ?int $requested): int
    {
        if ($requested !== null) {
            if ($requested < 1 || $requested > Media::MAX_POSITION) {
                abort(422, 'Position must be between 1 and ' . Media::MAX_POSITION . '.');
            }
            if (in_array($requested, $taken, true)) {
                throw MediaPositionConflictException::forPosition($requested);
            }

            return $requested;
        }

        for ($p = 1; $p <= Media::MAX_POSITION; $p++) {
            if (! in_array($p, $taken, true)) {
                return $p;
            }
        }

        throw MediaLimitExceededException::forParent(Media::MAX_PER_PARENT);
    }

    private function lockParent(Model $parent): Model
    {
        $locked = $parent->newQuery()->whereKey($parent->getKey())->lockForUpdate()->first();

        if ($locked === null) {
            // Concurrently soft-deleted / missing: privacy-safe 404.
            abort(404);
        }

        return $locked;
    }

    private function contextFor(string $class, int $id): MediaContext
    {
        $parent = $class::query()->find($id);

        if ($parent === null) {
            abort(404);
        }

        /** @var string $alias */
        $alias = $parent->getMorphClass();

        return new MediaContext(
            alias: $alias,
            communityId: (int) $parent->community_id,
            parent: $parent,
        );
    }
}
