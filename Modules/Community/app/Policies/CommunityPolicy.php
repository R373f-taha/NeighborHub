<?php

declare(strict_types=1);

namespace Modules\Community\app\Policies;

use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;

class CommunityPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Public
    }

    public function view(User $user, Community $community): bool
    {
        return true; // Auth
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Community $community): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isManager()) {
            return $community->managers()->where('manager_id', $user->id)->exists();
        }

        return false;
    }

    public function delete(User $user, Community $community): bool
    {
        return $user->isSuperAdmin();
    }

    public function viewStats(User $user, Community $community): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isManager()) {
            return $community->managers()->where('manager_id', $user->id)->exists();
        }

        return false;
    }
    public function manageResidents(User $user, Community $community): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isManager()) {
            return $community->managers()->where('manager_id', $user->id)->exists();
        }

        return false;
    }
    public function join(User $user, Community $community): bool
    {
        return $user->isResident();
    }
}
