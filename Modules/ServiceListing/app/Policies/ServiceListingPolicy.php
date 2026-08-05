<?php

declare(strict_types=1);

namespace Modules\ServiceListing\app\Policies;

use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\ServiceListing\app\Models\ServiceListing;
use Modules\ServiceListing\app\Services\ServiceListingAccessService;

class ServiceListingPolicy
{
    public function __construct(private ServiceListingAccessService $access) {}

    /**
     * Whether the user may browse listings within a community.
     */
    public function viewAny(User $user, Community $community): bool
    {
        return $this->access->canRead($user, $community);
    }

    /**
     * Whether the user may view a single listing.
     *
     * The listing's community is the domain scope; cross-community
     * mismatch is already rejected as 404 by scoped route binding
     * before this policy is consulted.
     */
    public function view(User $user, ServiceListing $listing): bool
    {
        return $this->access->canRead($user, $listing->community);
    }

    /**
     * Create: only an ACTIVE resident of the route community may create a
     * personal listing. Global roles (provider/manager/super_admin) alone
     * are NOT sufficient without an active resident record there.
     */
    public function create(User $user, Community $community): bool
    {
        return $this->access->activeResidentFor($user, $community) !== null;
    }

    /**
     * Update: the caller must hold the active resident record whose id
     * matches the listing's resident_id. No role bypass; managers and
     * super_admins do not become content owners.
     */
    public function update(User $user, ServiceListing $listing): bool
    {
        return $this->access->ownsActive($user, $listing->community, (int) $listing->resident_id);
    }

    /**
     * Delete: same ownership rule as update (soft delete by owner).
     */
    public function delete(User $user, ServiceListing $listing): bool
    {
        return $this->access->ownsActive($user, $listing->community, (int) $listing->resident_id);
    }

  
    public function updateStatus(User $user, ServiceListing $listing): bool
    {
        return $this->access->canUpdateStatus($user, $listing->community, (int) $listing->resident_id);
    }
}
