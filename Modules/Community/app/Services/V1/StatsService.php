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
        $stats = Resident::where('community_id', $communityId)
            ->where('current_marker', true)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended
            ")
            ->first();

        $byType = Resident::where('community_id', $communityId)
            ->where('current_marker', true)
            ->selectRaw('residence_type, COUNT(*) as count')
            ->groupBy('residence_type')
            ->pluck('count', 'residence_type');

        return [
            'total' => (int) $stats->total,
            'active' => (int) $stats->active,
            'pending' => (int) $stats->pending,
            'suspended' => (int) $stats->suspended,
            'by_residence_type' => $byType,
        ];
    });
}

    /**
     * Get all residents of a community
     */
    public function getResidents(int $communityId)
    {
       $cacheKey = $this->cacheKey('residents_list', $communityId);

       return $this->rememberResidents($cacheKey, $communityId, self::CACHE_TTL, function () use ($communityId) {
        return Resident::with(['user', 'unit'])
            ->where('community_id', $communityId)
            ->where('current_marker', true)
            ->get();
    });
    }
}
