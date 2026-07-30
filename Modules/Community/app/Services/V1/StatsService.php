<?php

namespace Modules\Community\app\Services\V1;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Traits\CacheableTraits;

class StatsService{


   use CacheableTraits;


      /**
     * Get community statistics with cache protection
     *
     * @param int $communityId The community ID
     * @return array Statistics data
     */
    public function getStats(int $communityId): array
    {
        $cacheKey = $this->cacheKey('stats', $communityId);

        return $this->rememberWithLock($cacheKey, self::CACHE_TTL, function () use ($communityId) {
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
     * Get detailed residents statistics with cache protection
     *
     * @param int $communityId The community ID
     * @return array Residents statistics data
     */
    public function getResidentsStats(int $communityId): array
    {
        $cacheKey = $this->cacheKey('residents_stats', $communityId);

       $data=$this->rememberWithLock($cacheKey, self::CACHE_TTL, function () use ($communityId) {
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

          Log::info(' Resisdents Stats calculated:', $data);
          return $data;
    }

    public function getResidents(int $communityId)
{
    return Resident::with(['user', 'unit'])
        ->where('community_id', $communityId)
        ->where('current_marker', true)
        ->get();
}

}
