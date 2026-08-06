<?php

declare(strict_types=1);

namespace Modules\ServiceListing\app\Services;

use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\CommunityManager;
use Modules\Community\app\Models\Resident;


class ServiceListingAccessService
{
    /**
     * The active Resident record for the user within the given community,
     * or null when the user is not an active resident there.
     */
    public function activeResidentFor(User $user, Community $community): ?Resident
    {
        return Resident::query()
            ->where('user_id', $user->id)
            ->where('community_id', $community->id)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Whether the user manages the given community via the
     * community_mangers pivot. Requires the Spatie "manager" or
     * "super_admin" global role in addition to the pivot row.
     */
    public function manages(User $user, Community $community): bool
    {
        if (! $user->hasRole('manager') && ! $user->hasRole('super_admin')) {
            return false;
        }

        return CommunityManager::query()
            ->where('community_id', $community->id)
            ->where('manager_id', $user->id)
            ->exists();
    }

    /**
     * Read access for Service Listings within a community.
     *
     * 1. super_admin           -> allowed (global moderation override)
     * 2. manager of community  -> allowed
     * 3. active resident       -> allowed
     * 4. everyone else         -> denied
     */
    public function canRead(User $user, Community $community): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($this->manages($user, $community)) {
            return true;
        }

        return $this->activeResidentFor($user, $community) !== null;
    }

    /**
     * Whether the user owns a listing (identified by community + resident id)
     * through an ACTIVE resident record in that community. Resolves the active
     * resident once (no User id == resident_id inference; Resident is a
     * separate model) and compares the resident id against the listing's
     * resident_id.
     *
     * A suspended/inactive former owner is denied here.
     */
    public function ownsActive(User $user, Community $community, int $residentId): bool
    {
        $resident = $this->activeResidentFor($user, $community);

        if ($resident === null) {
            return false;
        }

        return (int) $resident->id === $residentId;
    }

    /**
     * Optimistic HTTP pre-check for the status endpoint: whether the actor
     * plausibly holds ANY capability to touch this listing's status (owner,
     * manager of this community, or super_admin).
     *
     * This is NOT the concurrency authority. The Service re-evaluates all three
     * capabilities from fresh locked DB state. It exists only so a clear
     * outsider is rejected with 403 at the policy gate rather than entering the
     * transaction; transition validity (422) is decided under the locks.
     */
    public function canUpdateStatus(User $user, Community $community, int $residentId): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($this->manages($user, $community)) {
            return true;
        }

        return $this->ownsActive($user, $community, $residentId);
    }
}
