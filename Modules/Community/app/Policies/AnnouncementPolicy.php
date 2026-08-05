<?php

declare(strict_types=1);

namespace Modules\Community\app\Policies;

use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Models\Community;

class AnnouncementPolicy
{
    /**
     * Determine whether the user can view an announcement.
     */
    public function view(
        User $user,
        Announcement $announcement
    ): bool {
        if (! $user->can('view_announcements')) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isManager()) {
            return $this->managesCommunity(
                $user,
                (int) $announcement->community_id
            );
        }

        return $user->isResident()
            && $this->belongsToCommunity(
                $user,
                $announcement
            );
    }

    /**
     * Determine whether the user can create an announcement.
     */
    public function create(
        User $user,
        Community $community
    ): bool {
        if (! $user->can('create_announcement')) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isManager()
            && $this->managesCommunity(
                $user,
                (int) $community->id
            );
    }


 public function update(
    User $user,
    Announcement $announcement
): bool {

    if (! $user->can('update_announcement')) {
        return false;
    }

    if ($user->isSuperAdmin()) {
        return true;
    }

    return $user->isManager()
        && (int) $announcement->created_by_manager === (int) $user->id
        && $this->managesCommunity(
            $user,
            (int) $announcement->community_id
        );
}

    /**
     * Determine whether the user can delete an announcement.
     */
   public function delete(
    User $user,
    Announcement $announcement
): bool {

    if (! $user->can('delete_announcement')) {
        return false;
    }

    if ($user->isSuperAdmin()) {
        return true;
    }

    return $user->isManager()
        && (int) $announcement->created_by_manager === (int) $user->id
        && $this->managesCommunity(
            $user,
            (int) $announcement->community_id
        );
}

    /**
     * Determine whether the user can react to an announcement.
     */
    public function react(
        User $user,
        Announcement $announcement
    ): bool {
        if (! $user->can('react_announcement')) {
            return false;
        }

        return $user->isResident()
            && $this->belongsToCommunity(
                $user,
                $announcement
            );
    }

    /**
     * Check whether the manager manages the specified community.
     */
    private function managesCommunity(
        User $user,
        int $communityId
    ): bool {
        return $user->managedCommunities()
            ->where('communities.id', $communityId)
            ->exists();
    }

    /**
     * Check whether the resident belongs to the announcement community.
     */
    private function belongsToCommunity(
        User $user,
        Announcement $announcement
    ): bool {
        return $user->resident()
            ->where('community_id', $announcement->community_id)
            ->where('status', 'active')
            ->where('current_marker', true)
            ->exists();
    }
}