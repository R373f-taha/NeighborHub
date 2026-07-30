<?php

declare(strict_types=1);

namespace Modules\Community\app\Services\V1;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Traits\CacheableTraits;

class CommunityService
{
    use CacheableTraits;

      /**
     * Get list of communities with optional filters
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getCommunities(array $filters = [], int $perPage = 20)
    {
        Log::info('📋 Communities list requested', [
            'filters' => $filters,
            'per_page' => $perPage,
            'ip' => request()->ip(),
        ]);

        $query = Community::where('is_active', true);

        if (!empty($filters['city'])) {
            $query->where('city', 'like', '%' . $filters['city'] . '%');
            Log::info('🔍 Filtering by city', ['city' => $filters['city']]);
        }

        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
            Log::info('🔍 Filtering by name', ['name' => $filters['name']]);
        }

        $communities = $query->paginate($perPage);

        Log::info('✅ Communities retrieved', [
            'total' => $communities->total(),
            'count' => $communities->count(),
        ]);

        return $communities;
    }

    /**
     * Create a new community
     *
     * @param array $data Community data (name, city, address, etc.)
     * @return Community The created community
     */
    public function create(array $data): Community
    {
        return DB::transaction(function () use ($data) {
            $community = Community::create($data);

            if (isset($data['manager_ids'])) {
                $community->managers()->attach($data['manager_ids']);
            }


            $this->clearCache($community->id);

            return $community;
        });
    }

    /**
     * Update an existing community
     *
     * @param Community $community The community model
     * @param array $data The updated data
     * @return Community The updated community
     */
    public function update(Community $community, array $data): Community
    {
        $communityId = $community->id;

        return DB::transaction(function () use ($community, $data, $communityId) {
            $community->update($data);

            if (isset($data['manager_ids'])) {
                $community->managers()->sync($data['manager_ids']);
            }

            $this->clearCache($communityId);

            return $community->fresh();
        });
    }

    /**
     * Delete a community
     *
     * @param Community $community The community to delete
     * @return bool True if deleted successfully
     */
    public function delete(Community $community): bool
    {
        $communityId = $community->id;

        $this->clearCache($communityId);

        return $community->delete();
    }



}
