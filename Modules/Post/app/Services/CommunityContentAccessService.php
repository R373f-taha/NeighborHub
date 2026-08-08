<?php

declare(strict_types=1);

namespace Modules\Post\app\Services;

use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\CommunityManager;
use Modules\Community\app\Models\Resident;

class CommunityContentAccessService
{
    public function residentFor(User $user, Community $community): ?Resident
    {
        return Resident::where('user_id', $user->id)
            ->where('community_id', $community->id)
            ->first();
    }

    public function activeResidentFor(User $user, Community $community): ?Resident
    {
        return Resident::where('user_id', $user->id)
            ->where('community_id', $community->id)
            ->where('status', 'active')
            ->first();
    }

    public function isSuspendedResident(User $user, Community $community): bool
    {
        return Resident::where('user_id', $user->id)
            ->where('community_id', $community->id)
            ->where('status', 'suspended')
            ->exists();
    }

    public function manages(User $user, Community $community): bool
    {
        if (! $user->hasRole('manager') && ! $user->hasRole('super_admin')) {
            return false;
        }

        return CommunityManager::where('community_id', $community->id)
            ->where('manager_id', $user->id)
            ->exists();
    }

    public function canRead(User $user, Community $community): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($this->manages($user, $community)) {
            return true;
        }

        $resident = $this->residentFor($user, $community);

        if ($resident === null) {
            return false;
        }

        return in_array($resident->status, ['active', 'suspended'], true);
    }
}
