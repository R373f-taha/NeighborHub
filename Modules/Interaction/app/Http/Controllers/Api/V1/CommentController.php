<?php

declare(strict_types=1);

namespace Modules\Interaction\app\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Community\app\Models\Community;
use Modules\Interaction\app\Http\Requests\Api\V1\StoreCommentRequest;
use Modules\Interaction\app\Http\Requests\Api\V1\UpdateCommentRequest;
use Modules\Interaction\app\Http\Resources\Api\V1\CommentResource;
use Modules\Interaction\app\Models\Comment;
use Modules\Interaction\app\Services\CommentService;
use Modules\Post\app\Models\Post;
use Modules\Post\app\Services\CommunityContentAccessService;
use Modules\Post\app\Services\ModerationLogger;

class CommentController extends Controller
{
    public function __construct(
        private CommentService $comments,
        private CommunityContentAccessService $access,
        private ModerationLogger $logger,
    ) {}

    public function index(Request $request, Community $community, Post $post): JsonResponse
    {
        abort_unless((int) $post->community_id === (int) $community->id, 404);

        Gate::authorize('viewAny', [Comment::class, $post]);

        $perPage = (int) $request->query('per_page', 15);

        return CommentResource::collection(
            $this->comments->index($post, $perPage)
        )->additional(['message' => 'Comments retrieved successfully.'])->response();
    }

    public function store(StoreCommentRequest $request, Community $community, Post $post): JsonResponse
    {
        abort_unless((int) $post->community_id === (int) $community->id, 404);

        Gate::authorize('create', [Comment::class, $post]);

        $comment = $this->comments->store($post, $request->user(), $request->validated());

        return response()->json([
            'message' => 'Comment created successfully.',
            'data' => new CommentResource($comment),
        ], 201);
    }

    public function update(UpdateCommentRequest $request, Community $community, Post $post, Comment $comment): JsonResponse
    {
        abort_unless((int) $post->community_id === (int) $community->id, 404);
        $this->abortUnlessCommentBelongsToPost($comment, $post);

        Gate::authorize('update', $comment);

        return response()->json([
            'message' => 'Comment updated successfully.',
            'data' => new CommentResource($this->comments->update($comment, $request->validated())),
        ]);
    }

    public function destroy(Request $request, Community $community, Post $post, Comment $comment): JsonResponse
    {
        abort_unless((int) $post->community_id === (int) $community->id, 404);
        $this->abortUnlessCommentBelongsToPost($comment, $post);

        Gate::authorize('delete', $comment);

        $user = $request->user();
        $isOwner = (int) $comment->author_id === (int) $user->id;

        if (! $isOwner) {
            $this->logger->commentDeletedByModerator(
                $user,
                (int) $community->id,
                (int) $post->id,
                (int) $comment->id,
                (int) $comment->author_id,
            );
        }

        $this->comments->delete($comment);

        return response()->json([
            'message' => 'Comment deleted successfully.',
            'data' => null,
        ], 200);
    }

    private function abortUnlessCommentBelongsToPost(Comment $comment, Post $post): void
    {
        $belongsToPost = $comment->commentable_type === $post->getMorphClass()
            && (int) $comment->commentable_id === (int) $post->id;

        abort_unless($belongsToPost, 404);
    }
}
