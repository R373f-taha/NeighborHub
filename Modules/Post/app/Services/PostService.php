<?php

declare(strict_types=1);

namespace Modules\Post\app\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Post\app\Models\Post;

class PostService
{
    public function __construct(private CommunityContentAccessService $access) {}

    public function index(Community $community, ?string $category = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Post::where('community_id', $community->id)
            ->with(['author.user:id,name'])
            ->withCount('comments')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($category !== null) {
            $query->where('category', $category);
        }

        return $query->paginate(min($perPage, 100));
    }

    public function show(Post $post): Post
    {
        return $post->loadMissing(['author.user:id,name'])
            ->loadCount('comments');
    }

    public function store(Community $community, User $user, array $validated): Post
    {
        $resident = $this->access->activeResidentFor($user, $community);

        $post = Post::create([
            'community_id' => $community->id,
            'resident_id' => $resident->id,
            'category' => $validated['category'],
            'content' => $validated['content'],
        ]);

        return $post->load('author.user:id,name')->loadCount('comments');
    }

    public function update(Post $post, array $validated): Post
    {
        $post->update($validated);

        return $post->load('author.user:id,name')->loadCount('comments');
    }

    public function delete(Post $post): void
    {
        $post->delete();
    }
}
