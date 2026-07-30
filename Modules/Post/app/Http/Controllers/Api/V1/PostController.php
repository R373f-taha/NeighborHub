<?php

declare(strict_types=1);

namespace Modules\Post\app\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Modules\Community\app\Models\Community;
use Modules\Post\app\Http\Requests\Api\V1\StorePostRequest;
use Modules\Post\app\Http\Requests\Api\V1\UpdatePostRequest;
use Modules\Post\app\Http\Resources\Api\V1\PostResource;
use Modules\Post\app\Models\Post;
use Modules\Post\app\Services\CommunityContentAccessService;
use Modules\Post\app\Services\ModerationLogger;
use Modules\Post\app\Services\PostService;

class PostController extends Controller
{
    public function __construct(
        private PostService $posts,
        private CommunityContentAccessService $access,
        private ModerationLogger $logger,
    ) {}

    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Post::class, $community]);

        $category = $request->query('category');
        $perPage = (int) $request->query('per_page', 15);

        return PostResource::collection(
            $this->posts->index($community, $category, $perPage)
        );
    }

    public function store(StorePostRequest $request, Community $community): JsonResponse
    {
        Gate::authorize('create', [Post::class, $community]);

        $post = $this->posts->store($community, $request->user(), $request->validated());

        return (new PostResource($post))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Community $community, Post $post): PostResource
    {
        Gate::authorize('view', $post);

        return new PostResource($this->posts->show($post));
    }

    public function update(UpdatePostRequest $request, Community $community, Post $post): PostResource
    {
        Gate::authorize('update', $post);

        return new PostResource($this->posts->update($post, $request->validated()));
    }

    public function destroy(Request $request, Community $community, Post $post): JsonResponse
    {
        Gate::authorize('delete', $post);

        $user = $request->user();
        $resident = $this->access->activeResidentFor($user, $community);
        $isOwner = $resident !== null && (int) $resident->id === (int) $post->resident_id;

        if (! $isOwner) {
            $this->logger->postDeletedByModerator(
                $user,
                (int) $community->id,
                (int) $post->id,
                (int) $post->resident_id,
            );
        }

        $this->posts->delete($post);

        return response()->json(null, 204);
    }
}
