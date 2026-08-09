<?php

declare(strict_types=1);

namespace Modules\Community\app\Services\V1;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Traits\CacheableTraits;

class CommunityService
{
    use CacheableTraits;

    /**
     * Get list of communities with optional filters
     */
    public function getCommunities(array $filters = [], int $perPage = 20)
    {
        Log::info('📋 Communities list requested', [
            'filters' => $filters,
            'per_page' => $perPage,
        ]);

        $cacheKey = 'communities_list_' . md5(json_encode($filters) . $perPage);

        return $this->rememberList($cacheKey, self::CACHE_TTL, function () use ($filters, $perPage) {
            $query = Community::where('is_active', true);

            if (!empty($filters['city'])) {
                $query->where('city', 'like', '%' . $filters['city'] . '%');
            }

            if (!empty($filters['name'])) {
                $query->where('name', 'like', '%' . $filters['name'] . '%');
            }

            return $query->paginate($perPage);
        });
    }

    /**
     * Create a new community
     */
    public function create(array $data): Community
    {
        return DB::transaction(function () use ($data) {
            $community = Community::create($data);

            if (isset($data['manager_ids'])) {
                $community->managers()->attach($data['manager_ids']);
            }

            $this->clearCache($community->id);
            $this->clearListCache();

            return $community;
        });
    }

    /**
     * Update an existing community
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
            $this->clearListCache();

            return $community->fresh();
        });
    }

    /**
     * Delete a community
     */
    public function delete(Community $community): bool
    {
        $communityId = $community->id;

        $this->clearCache($communityId);
        $this->clearListCache();

        return $community->delete();
    }
}
