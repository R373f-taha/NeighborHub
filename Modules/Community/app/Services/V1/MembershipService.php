<?php

declare(strict_types=1);

namespace Modules\Community\app\Services\V1;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Traits\CacheableTraits;

class MembershipService
{
    use CacheableTraits;

    /**
     * Request to join a community.
     *
     * Uses Database Pessimistic Locking (lockForUpdate) to prevent Race Condition
     * when a user double-clicks the "Join" button or submits multiple requests simultaneously.
     *
     * @param Community $community The community to join
     * @param User $user The authenticated user making the request
     * @param array $data Request data (unit_id, residence_type)
     * @return array|Resident
     */
    public function joinCommunity(Community $community, User $user, array $data)
    {
        return DB::transaction(function () use ($community, $user, $data) {
            $existing = Resident::where('user_id', $user->id)
                ->where('unit_id', $data['unit_id'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                Log::warning('⚠️ Existing membership found, resetting to pending', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'community_id' => $community->id,
                    'existing_status' => $existing->status,
                    'existing_id' => $existing->id,
                ]);

                $existing->update([
                    'community_id' => $community->id,
                    'residence_type' => $data['residence_type'],
                    'status' => 'pending',
                    'current_marker' => true,
                    'joined_at' => now(),
                ]);

                $this->clearCache($community->id);

                return [
                    'message' => 'You already have a membership in this unit, but your status has been reset to pending.',
                    'record' => $existing->fresh(),
                ];
            }

            Log::info('✅ New join request, creating resident record', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'community_id' => $community->id,
                'community_name' => $community->name,
                'unit_id' => $data['unit_id'],
                'residence_type' => $data['residence_type'],
            ]);

            $resident = Resident::create([
                'user_id' => $user->id,
                'community_id' => $community->id,
                'unit_id' => $data['unit_id'],
                'residence_type' => $data['residence_type'],
                'status' => 'pending',
                'current_marker' => false,
            ]);

            $this->clearCache($community->id);

            return $resident;
        });
    }

    /**
     * Approve a pending resident
     */
    public function approveResident(Community $community, Resident $resident): Resident
    {
        if ($resident->community_id !== $community->id) {
            throw new \RuntimeException('This resident does not belong to the specified community');
        }

        if ($resident->status !== 'pending') {
            throw new \RuntimeException('Only pending residents can be approved');
        }

        return DB::transaction(function () use ($resident) {
            $resident->update([
                'status' => 'active',
                'joined_at' => now(),
            ]);

            Log::info('✅ Resident approved successfully', [
                'resident_id' => $resident->id,
                'user_id' => $resident->user_id,
                'community_id' => $resident->community_id,
                'new_status' => 'active',
                'approved_by' => Auth::id(),
            ]);

            $this->clearCache($resident->community_id);

            return $resident->fresh();
        });
    }

    /**
     * Reject a pending resident
     */
    public function rejectResident(Community $community, Resident $resident): Resident
    {
        if ($resident->community_id !== $community->id) {
            throw new \RuntimeException('This resident does not belong to the specified community');
        }

        if ($resident->status !== 'pending') {
            throw new \RuntimeException('Only pending residents can be rejected');
        }

        return DB::transaction(function () use ($resident) {
            $resident->update([
                'status' => 'rejected',
                'current_marker' => false,
            ]);

            Log::info('❌ Resident rejected successfully', [
                'resident_id' => $resident->id,
                'user_id' => $resident->user_id,
                'community_id' => $resident->community_id,
                'new_status' => 'rejected',
                'rejected_by' => Auth::id(),
            ]);

            $this->clearCache($resident->community_id);

            return $resident->fresh();
        });
    }

    /**
     * Suspend an active resident
     */
    public function suspendResident(Resident $resident, Community $community): Resident
    {
        if ($resident->community_id !== $community->id) {
            throw new \RuntimeException('This resident does not belong to the specified community');
        }

        if ($resident->status !== 'active') {
            throw new \RuntimeException('Only active residents can be suspended');
        }

        return DB::transaction(function () use ($resident) {
            $resident->update([
                'status' => 'suspended',
            ]);

            Log::info('⛔ Resident suspended successfully', [
                'resident_id' => $resident->id,
                'user_id' => $resident->user_id,
                'community_id' => $resident->community_id,
                'new_status' => 'suspended',
                'suspended_by' => Auth::id(),
            ]);

            $this->clearCache($resident->community_id);

            return $resident->fresh();
        });
    }

    /**
     * Get current user's residency with cache
     */
    public function getMyResidency(User $user): ?Resident
    {
        $cacheKey = $this->cacheKey('my_residency', $user->id);

     return $this->rememberStats($cacheKey, self::CACHE_TTL, function () use ($user) {
            $resident = Resident::where('user_id', $user->id)
                ->where('current_marker', true)
                ->first();

            if ($resident) {
                Log::info('✅ Residency found', [
                    'user_id' => $user->id,
                    'resident_id' => $resident->id,
                    'community_id' => $resident->community_id,
                    'unit_id' => $resident->unit_id,
                    'status' => $resident->status,
                ]);
            } else {
                Log::warning('⚠️ No residency found', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ]);
            }

            return $resident;
        });
    }
}
