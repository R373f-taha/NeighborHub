<?php

declare(strict_types=1);

namespace Modules\Community\app\Policies;

use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Models\Community;

class AnnouncementPolicy
{
    public function view(
        User $user,
        Announcement $announcement
    ): bool {

        if ($user->isSuperAdmin()) {
            return true;
        }

// Manager of the same community
 if (
            $user->isManager() &&
            $user->managedCommunities()
                ->where('communities.id', $announcement->community_id)
                ->exists()
        ) {
            return true;
        }

// Resident of the same community

        return $this->belongsToCommunity(
            $user,
            $announcement
        );
    }

    public function create(
        User $user,
        Community $community
    ): bool {

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isManager()
            && $user->managedCommunities()
                ->where(
                    'communities.id',
                    $community->id
                )
                ->exists();
    }

    public function update(
        User $user,
        Announcement $announcement
    ): bool {

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isManager()
            && $announcement->created_by_manager === $user->id
            && $user->managedCommunities()
                ->where(
                    'communities.id',
                    $announcement->community_id
                )
                ->exists();
    }

    public function delete(
        User $user,
        Announcement $announcement
    ): bool {

        return $this->update(
            $user,
            $announcement
        );
    }

    public function react(
        User $user,
        Announcement $announcement
    ): bool {

        return $user->isResident()
            && $this->belongsToCommunity(
                $user,
                $announcement
            );
    }

    private function belongsToCommunity(
        User $user,
        Announcement $announcement
    ): bool {

        if (! $user->resident) {
            return false;
        }

        return $user->resident()
            ->whereHas(
                'unit',
                fn ($query) => $query->where(
                    'community_id',
                    $announcement->community_id
                )
            )
            ->exists();
    }
}