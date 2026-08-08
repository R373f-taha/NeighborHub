<?php

declare(strict_types=1);

namespace Modules\Community\app\Policies;

use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;

class CommunityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Community $community): bool
    {
        return $user !== null;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isManager();
    }

    public function create_community(User $user): bool
    {
        return $this->create($user);
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

    public function update_community(User $user, Community $community): bool
    {
        return $this->update($user, $community);
    }

    public function delete(User $user, Community $community): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete_community(User $user, Community $community): bool
    {
        return $this->delete($user, $community);
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

    public function view_communities(User $user, Community $community): bool
    {
        return $this->view($user, $community);
    }

    public function view_community_stats(User $user, Community $community): bool
    {
        return $this->viewStats($user, $community);
    }

    public function view_residents(User $user, Community $community): bool
    {
        return $this->manageResidents($user, $community);
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

    public function join_community(User $user, Community $community): bool
    {
        return $this->join($user, $community);
    }

    public function approve_resident(User $user, Community $community): bool
    {
        return $this->manageResidents($user, $community);
    }

    public function reject_resident(User $user, Community $community): bool
    {
        return $this->manageResidents($user, $community);
    }

    public function suspend_resident(User $user, Community $community): bool
    {
        return $this->manageResidents($user, $community);
    }

    public function view_my_residency(User $user): bool
    {
        return $user->isResident();
    }

    public function view_announcements(User $user): bool
    {
        return $user !== null;
    }

    public function create_announcement(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isManager();
    }

    public function update_announcement(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isManager();
    }

    public function delete_announcement(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isManager();
    }

    public function react_announcement(User $user): bool
    {
        return $user->isResident();
    }
}
