<?php

declare(strict_types=1);

namespace Modules\Interaction\app\Policies;

use Modules\Auth\app\Models\User;
use Modules\Interaction\app\Models\Comment;
use Modules\Post\app\Models\Post;
use Modules\Post\app\Services\CommunityContentAccessService;

class CommentPolicy
{
    public function __construct(private CommunityContentAccessService $access) {}

    public function viewAny(User $user, Post $post): bool
    {
        return $this->access->canRead($user, $post->community);
    }

    public function view(User $user, Comment $comment): bool
    {
        $post = $this->resolvePost($comment);

        return $post !== null && $this->access->canRead($user, $post->community);
    }

    public function create(User $user, Post $post): bool
    {
        return $this->access->activeResidentFor($user, $post->community) !== null;
    }

    public function update(User $user, Comment $comment): bool
    {
        $post = $this->resolvePost($comment);

        if ($post === null) {
            return false;
        }

        if ((int) $comment->author_id !== (int) $user->id) {
            return false;
        }

        return $this->access->activeResidentFor($user, $post->community) !== null;
    }

    public function delete(User $user, Comment $comment): bool
    {
        $post = $this->resolvePost($comment);

        if ($post === null) {
            return false;
        }

        $community = $post->community;

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($this->access->manages($user, $community)) {
            return true;
        }

        if ((int) $comment->author_id !== (int) $user->id) {
            return false;
        }

        return $this->access->activeResidentFor($user, $community) !== null;
    }

    private function resolvePost(Comment $comment): ?Post
    {
        if ($comment->commentable instanceof Post) {
            return $comment->commentable;
        }

        if ($comment->commentable_type === (new Post)->getMorphClass()) {
            return Post::find($comment->commentable_id);
        }

        return null;
    }
}
