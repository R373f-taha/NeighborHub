<?php

namespace Modules\Community\app\Services\V1;

use Illuminate\Support\Facades\Log;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Traits\CacheableTraits;

class StatsService
{
    use CacheableTraits;

    /**
     * Get community statistics with cache protection using Tags
     */
    public function getStats(int $communityId): array
    {
        $cacheKey = $this->cacheKey('stats', $communityId);

        return $this->rememberStats($cacheKey, self::CACHE_TTL, function () use ($communityId) {
            $community = Community::withCount(['units', 'residents'])
                ->findOrFail($communityId);

            $data = [
                'total_units' => $community->units_count,
                'total_residents' => $community->residents_count,
                'active_residents' => Resident::where('community_id', $communityId)
                    ->where('status', 'active')
                    ->where('current_marker', true)
                    ->count(),
            ];

            Log::info('Stats calculated:', $data);

            return $data;
        });
    }

    /**
     * Get detailed residents statistics with cache protection using Tags
     */
    public function getResidentsStats(int $communityId): array
    {
        $cacheKey = $this->cacheKey('residents_stats', $communityId);


        
        return $this->rememberResidentsStats($cacheKey, $communityId, self::CACHE_TTL, function () use ($communityId) {
            return [
                'total' => Resident::where('community_id', $communityId)
                    ->where('current_marker', true)
                    ->count(),
                'active' => Resident::where('community_id', $communityId)
                    ->where('status', 'active')
                    ->where('current_marker', true)
                    ->count(),
                'pending' => Resident::where('community_id', $communityId)
                    ->where('status', 'pending')
                    ->where('current_marker', true)
                    ->count(),
                'suspended' => Resident::where('community_id', $communityId)
                    ->where('status', 'suspended')
                    ->count(),
                'by_residence_type' => Resident::where('community_id', $communityId)
                    ->where('current_marker', true)
                    ->selectRaw('residence_type, count(*) as count')
                    ->groupBy('residence_type')
                    ->pluck('count', 'residence_type'),
            ];
        });
    }

    /**
     * Get all residents of a community
     */
    public function getResidents(int $communityId)
    {
        return Resident::with(['user', 'unit'])
            ->where('community_id', $communityId)
            ->where('current_marker', true)
            ->get();
    }
}
