<?php

declare(strict_types=1);

namespace Modules\Interaction\app\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Auth\app\Models\User;
use Modules\Interaction\app\Models\Comment;
use Modules\Post\app\Models\Post;

class CommentService
{
    public function index(Post $post, int $perPage = 15): LengthAwarePaginator
    {
        return $post->comments()
            ->with('author:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min($perPage, 100));
    }

    public function store(Post $post, User $user, array $validated): Comment
    {
        $comment = $post->comments()->create([
            'author_id' => $user->id,
            'content' => $validated['content'],
        ]);

        return $comment->load('author:id,name');
    }

    public function update(Comment $comment, array $validated): Comment
    {
        $comment->update($validated);

        return $comment->load('author:id,name');
    }

    public function delete(Comment $comment): void
    {
        $comment->delete();
    }
}
