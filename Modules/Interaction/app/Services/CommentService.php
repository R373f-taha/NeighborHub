<?php

declare(strict_types=1);

namespace Modules\Interaction\app\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Modules\Auth\app\Models\User;
use Modules\Interaction\app\Models\Comment;
use Modules\Post\app\Models\Post;

class CommentService
{
    public function index(
        Post $post,
        int $perPage = 15
    ): LengthAwarePaginator {

        return $post->comments()
            ->with('author:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min($perPage, 100));
    }


    public function store(
        Model $model,
        User $user,
        array $validated
    ): Comment {

        $comment = $model->comments()->create([
            'author_id' => $user->id,
            'content' => $validated['content'],
        ]);

        return $comment->load('author:id,name');
    }


    public function update(
        Comment $comment,
        array $validated
    ): Comment {

        $comment->update($validated);

        return $comment->load('author:id,name');
    }


    public function delete(
        Comment $comment
    ): void {

        $comment->delete();
    }
}