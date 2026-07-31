<?php

declare(strict_types=1);

namespace Modules\Post\app\Policies;

use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Post\app\Models\Post;
use Modules\Post\app\Services\CommunityContentAccessService;

class PostPolicy
{
    public function __construct(private CommunityContentAccessService $access) {}

    public function viewAny(User $user, Community $community): bool
    {
        return $this->access->canRead($user, $community);
    }

    public function view(User $user, Post $post): bool
    {
        return $this->access->canRead($user, $post->community);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->access->activeResidentFor($user, $community) !== null;
    }

    public function update(User $user, Post $post): bool
    {
        $resident = $this->access->activeResidentFor($user, $post->community);

        if ($resident === null) {
            return false;
        }

        return (int) $resident->id === (int) $post->resident_id;
    }

    public function delete(User $user, Post $post): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($this->access->manages($user, $post->community)) {
            return true;
        }

        $resident = $this->access->activeResidentFor($user, $post->community);

        if ($resident === null) {
            return false;
        }

        return (int) $resident->id === (int) $post->resident_id;
    }

    public function react(User $user, Post $post): bool
    {
        return $this->access->activeResidentFor($user, $post->community) !== null;
    }
}

