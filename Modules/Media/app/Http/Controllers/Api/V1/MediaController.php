<?php

declare(strict_types=1);

namespace Modules\Media\app\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Media\app\Exceptions\MediaLimitExceededException;
use Modules\Media\app\Exceptions\MediaPositionConflictException;
use Modules\Media\app\Http\Requests\Api\V1\ReorderMediaRequest;
use Modules\Media\app\Http\Requests\Api\V1\StoreMediaRequest;
use Modules\Media\app\Http\Resources\Api\V1\MediaResource;
use Modules\Media\app\Models\Media;
use Modules\Media\app\Services\MediaLogger;
use Modules\Media\app\Services\MediaService;
use Modules\Post\app\Models\Post;
use Modules\ServiceListing\app\Models\ServiceListing;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaService $media,
        private readonly MediaLogger $logger,
    ) {}

    public function uploadPost(StoreMediaRequest $request, Community $community, Post $post): JsonResponse
    {
        Gate::authorize('update', $post);

        $media = $this->attach(
            $post,
            'post',
            $request->user(),
            $request->file('image'),
            $this->positionFrom($request),
            fn () => Gate::forUser($request->user())->check('update', $post),
        );

        return $this->created($media);
    }

    public function uploadServiceListing(StoreMediaRequest $request, Community $community, ServiceListing $serviceListing): JsonResponse
    {
        Gate::authorize('update', $serviceListing);

        $media = $this->attach(
            $serviceListing,
            'service_listing',
            $request->user(),
            $request->file('image'),
            $this->positionFrom($request),
            fn () => Gate::forUser($request->user())->check('update', $serviceListing),
        );

        return $this->created($media);
    }

    public function delete(Request $request, Community $community, Media $media): JsonResponse
    {
        $context = $this->media->resolveContext($media);

        if ($context->communityId !== (int) $community->id) {
            // Cross-community: privacy-safe 404, no existence leak.
            abort(404);
        }

        Gate::authorize('update', $context->parent);

        $this->media->delete($media, $context->parent, $context->alias);

        $this->logger->deleted(
            $request->user(),
            $context->communityId,
            (int) $media->id,
            $context->alias,
            (int) $context->parent->getKey(),
        );

        return response()->json([
            'message' => 'Media deleted successfully.',
            'data' => null,
        ]);
    }

    public function reorderPost(ReorderMediaRequest $request, Community $community, Post $post): JsonResponse
    {
        Gate::authorize('update', $post);

        $items = $request->validated('items');
        $this->media->reorder($post, 'post', $items);

        $this->logger->reordered($request->user(), (int) $community->id, 'post', (int) $post->id, count($items));

        return $this->reorderResponse($post);
    }

    public function reorderServiceListing(ReorderMediaRequest $request, Community $community, ServiceListing $serviceListing): JsonResponse
    {
        Gate::authorize('update', $serviceListing);

        $items = $request->validated('items');
        $this->media->reorder($serviceListing, 'service_listing', $items);

        $this->logger->reordered($request->user(), (int) $community->id, 'service_listing', (int) $serviceListing->id, count($items));

        return $this->reorderResponse($serviceListing);
    }

    private function attach($parent, string $alias, User $user, $image, ?int $position, callable $authorizeUnderLock): Media
    {
        try {
            return $this->media->attach($parent, $alias, $user, $image, $position, $authorizeUnderLock);
        } catch (MediaLimitExceededException $e) {
            throw ValidationException::withMessages(['media' => $e->getMessage()]);
        } catch (MediaPositionConflictException $e) {
            throw ValidationException::withMessages(['position' => $e->getMessage()]);
        }
    }

    private function positionFrom(StoreMediaRequest $request): ?int
    {
        $position = $request->validated('position');

        return $position === null ? null : (int) $position;
    }

    private function created(Media $media): JsonResponse
    {
        return response()->json([
            'message' => 'Media uploaded successfully.',
            'data' => new MediaResource($media),
        ], 201);
    }

    private function reorderResponse($parent): JsonResponse
    {
        return response()->json([
            'message' => 'Media reordered successfully.',
            'data' => MediaResource::collection($parent->loadMissing('media')->media),
        ]);
    }
}
