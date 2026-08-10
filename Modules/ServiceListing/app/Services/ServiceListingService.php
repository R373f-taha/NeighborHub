<?php

declare(strict_types=1);

namespace Modules\ServiceListing\app\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\CommunityManager;
use Modules\Community\app\Models\Resident;
use Modules\ServiceListing\app\Exceptions\InvalidServiceListingStatusTransitionException;
use Modules\ServiceListing\app\Models\ServiceListing;

class ServiceListingService
{
    /**
     * Default and maximum page sizes, consistent with existing project APIs.
     */
    public const DEFAULT_PER_PAGE = 15;
    public const MAX_PER_PAGE = 100;

    /**
     * OWNER status transition matrix (including idempotent self-targets).
     *
     * Closed is terminal: closed -> active / reserved are denied. closed ->
     * closed is permitted as an authorized idempotent no-op (the owner may
     * maintain a state it legitimately reached).
     */
    private const OWNER_TRANSITIONS = [
        'active' => ['reserved', 'closed', 'active'],
        'reserved' => ['active', 'closed', 'reserved'],
        'closed' => ['closed'],
    ];

    /**
     * MODERATOR (community manager / super_admin) transition matrix.
     *
     * Moderation is CLOSE ONLY. A moderator may close an active or reserved
     * listing, and maintain an already-closed listing idempotently, but may
     * never reserve, reactivate, or maintain an active/reserved state.
     */
    private const MODERATOR_TRANSITIONS = [
        'active' => ['closed'],
        'reserved' => ['closed'],
        'closed' => ['closed'],
    ];

    public function __construct(private ServiceListingMutationLogger $logger) {}

    /**
     * Paginated, community-scoped collection with deterministic ordering.
     *
     * Uses the existing [community_id, status] index shape via the
     * community_id equality predicate. Soft-deleted rows are excluded by
     * default (no withTrashed).
     */
    public function index(Community $community, int $perPage = self::DEFAULT_PER_PAGE): LengthAwarePaginator
    {
        return ServiceListing::query()
            ->where('community_id', $community->id)
            ->with(['author.user:id,name', 'media'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->clampPerPage($perPage));
    }

    /**
     * Load relations required by the API resource for a single listing.
     */
    public function show(ServiceListing $listing): ServiceListing
    {
        return $listing->loadMissing(['author.user:id,name', 'media']);
    }

    /**
     * Cast and clamp the requested page size to a safe range.
     */
    public function clampPerPage(int $perPage): int
    {
        return max(1, min($perPage, self::MAX_PER_PAGE));
    }

    /**
     * Create a listing with server-authoritative values.
     *
     * The Policy is the normal HTTP authorization gate, but it is NOT
     * concurrency-safe: a membership can be suspended between the policy
     * check and the insert. The authoritative, race-safe decision is made
     * here inside a transaction: the caller's Resident row is locked
     * (FOR UPDATE) and its ACTIVE status is re-verified under that lock
     * before the insert. The Resident is always re-resolved from the
     * authenticated User (never from a client value or a stale model).
     *
     * Only validated content fields are taken from the client
     * (title/description/type/price/expires_at). community_id, resident_id,
     * status and closed_at are forced from server context/rules.
     */
    public function store(User $user, Community $community, array $content): ServiceListing
    {
        return DB::transaction(function () use ($user, $community, $content): ServiceListing {
            $resident = $this->lockResident($user, $community);

            if ($resident === null || $resident->status !== 'active') {
                throw new AuthorizationException();
            }

            return ServiceListing::create([
                'title' => $content['title'],
                'description' => $content['description'],
                'type' => $content['type'],
                'price' => array_key_exists('price', $content) ? $content['price'] : null,
                'expires_at' => $content['expires_at'],
                'community_id' => $community->id,
                'resident_id' => $resident->id,
                'status' => 'active',
                'closed_at' => null,
            ])->loadMissing(['author.user:id,name', 'media']);
        });
    }

    /**
     * Update only validated mutable content fields under a transaction.
     *
     * Lock order is Resident -> ServiceListing (consistent with create and
     * delete). Active residency and ownership are re-verified under the
     * locks so a concurrent suspension cannot authorize a stale mutation.
     * validated() never contains community_id/resident_id/status/closed_at,
     * so those are structurally immutable through this endpoint.
     */
    public function update(User $user, Community $community, ServiceListing $listing, array $content): ServiceListing
    {
        return DB::transaction(function () use ($user, $community, $listing, $content): ServiceListing {
            $resident = $this->lockResident($user, $community);

            if ($resident === null || $resident->status !== 'active') {
                throw new AuthorizationException();
            }

            $locked = $this->lockScopedListing($community, $listing);

            if ($locked === null) {
                abort(404); // missing / cross-community / concurrently soft-deleted
            }

            if ((int) $locked->resident_id !== (int) $resident->id) {
                throw new AuthorizationException(); // not the owner
            }

            $locked->update($content);

            return $locked->loadMissing(['author.user:id,name', 'media']);
        });
    }

   /**
    * Transition a listing's status under a single serialized boundary.
    *
    * The Policy is only the optimistic HTTP pre-check; the authoritative
    * decision is made here from fresh locked DB state. Lock order is
    * deterministic so concurrent status mutations, role assignment, residency
    * suspension, manager pivot changes, and soft-deletion cannot race past the
    * invariant:
    */
    public function updateStatus(User $user, Community $community, ServiceListing $listing, string $requestedStatus): ServiceListing
    {
        $oldStatus = null;

        $updated = DB::transaction(function () use ($user, $community, $listing, $requestedStatus, &$oldStatus): ServiceListing {
            $actor = User::query()
                ->where('id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($actor === null) {
                throw new AuthorizationException();
            }

            // Actor's Resident row for this community (null for managers/
            // super_admins who moderate without residency).
            $resident = Resident::query()
                ->where('user_id', $actor->id)
                ->where('community_id', $community->id)
                ->lockForUpdate()
                ->first();

            // Actor's community_mangers membership for THIS community (null if
            // none). The table name keeps the team-owned typo on purpose.
            $managerPivot = CommunityManager::query()
                ->where('community_id', $community->id)
                ->where('manager_id', $actor->id)
                ->lockForUpdate()
                ->first();

            // Re-resolve and lock the scoped, non-soft-deleted listing.
            $locked = $this->lockScopedListing($community, $listing);

            if ($locked === null) {
                abort(404); // missing / cross-community / concurrently soft-deleted
            }

            // Authoritative capability evaluation under all locks.
            $isSuperAdmin = $actor->hasRole('super_admin');
            $isManagerOfCommunity = $actor->hasRole('manager') && $managerPivot !== null;
            $isOwner = $resident !== null
                && $resident->status === 'active'
                && (int) $resident->id === (int) $locked->resident_id;

            if (! ($isOwner || $isManagerOfCommunity || $isSuperAdmin)) {
                throw new AuthorizationException(); // 403: lacks capability
            }

            $currentStatus = (string) $locked->status;
            $oldStatus = $currentStatus;

            $allowedTargets = ($isOwner ? self::OWNER_TRANSITIONS : self::MODERATOR_TRANSITIONS)[$currentStatus] ?? [];

            if (! in_array($requestedStatus, $allowedTargets, true)) {
                // Has capability but the transition is illegal for this tier
                // (e.g. closed -> active, or a moderator reserving). Not a
                // capability denial.
                throw new InvalidServiceListingStatusTransitionException($currentStatus, $requestedStatus);
            }

            if ($currentStatus === $requestedStatus) {
                return $locked->loadMissing(['author.user:id,name']);
            }

            $locked->status = $requestedStatus;
            $locked->closed_at = $requestedStatus === 'closed' ? now() : null;
            $locked->save();

            return $locked->loadMissing(['author.user:id,name']);
        });

        // Audit AFTER commit so no DB lock is held during logging I/O.
        $this->logger->statusUpdated(
            $user,
            (int) $community->id,
            (int) $updated->id,
            (string) $oldStatus,
            $requestedStatus,
        );

        return $updated;
    }

    /**
     * Soft-delete the listing (no forceDelete) under the same locked
     * current-state invariant as update.
     */
    public function delete(User $user, Community $community, ServiceListing $listing): void
    {
        DB::transaction(function () use ($user, $community, $listing): void {
            $resident = $this->lockResident($user, $community);

            if ($resident === null || $resident->status !== 'active') {
                throw new AuthorizationException();
            }

            $locked = $this->lockScopedListing($community, $listing);

            if ($locked === null) {
                abort(404);
            }

            if ((int) $locked->resident_id !== (int) $resident->id) {
                throw new AuthorizationException();
            }

            $locked->delete(); // soft delete only
        });
    }

    /**
     * Lock the caller's Resident row for the community (FOR UPDATE) without
     * a status filter, so the committed status is read under the lock.
     */
    private function lockResident(User $user, Community $community): ?Resident
    {
        return Resident::query()
            ->where('user_id', $user->id)
            ->where('community_id', $community->id)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Lock the community-scoped, non-soft-deleted listing row (FOR UPDATE).
     * Returns null if it is missing, belongs to another community, or was
     * concurrently soft-deleted (all -> 404).
     */
    private function lockScopedListing(Community $community, ServiceListing $listing): ?ServiceListing
    {
        return ServiceListing::query()
            ->where('id', $listing->id)
            ->where('community_id', $community->id)
            ->lockForUpdate()
            ->first();
    }
}
