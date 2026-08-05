<?php

declare(strict_types=1);

namespace Modules\Community\app\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait AnnouncementCacheableTrait
{
    protected const ANNOUNCEMENT_CACHE_TTL = 600;
    protected const ANNOUNCEMENT_LOCK_TIMEOUT = 10;


    protected function announcementCacheKey(
        string $type,
        int $communityId,
        ?int $announcementId = null
    ): string {

        if ($announcementId !== null) {
            return "announcement_{$type}_{$communityId}_{$announcementId}";
        }

        return "announcement_{$type}_{$communityId}";
    }



    protected function announcementTags(
        int $communityId
    ): array {

        return [
            'announcements',
            "community_{$communityId}_announcements",
        ];
    }



    protected function rememberAnnouncement(
        string $key,
        int $ttl,
        callable $callback,
        int $communityId
    ): mixed {


        $tags = $this->announcementTags($communityId);



        $value = Cache::tags($tags)->get($key);



        if ($value !== null) {
            return $value;
        }



        $lock = Cache::lock(
            "lock_{$key}",
            self::ANNOUNCEMENT_LOCK_TIMEOUT
        );


        try {

            $lock->block(
                self::ANNOUNCEMENT_LOCK_TIMEOUT
            );



            $value = Cache::tags($tags)->get($key);



            if ($value !== null) {
                return $value;
            }



            $value = $callback();



            Cache::tags($tags)->put(
                $key,
                $value,
                $ttl + random_int(0,60)
            );



            Log::info(
                'Announcement cache generated',
                [
                    'key'=>$key,
                    'community_id'=>$communityId,
                ]
            );



            return $value;


        } finally {

            $lock->release();

        }

    }




    protected function clearAnnouncementCache(
        int $communityId
    ): void {


        Cache::tags(
            $this->announcementTags($communityId)
        )->flush();



        Log::info(
            'Announcement cache cleared',
            [
                'community_id'=>$communityId
            ]
        );

    }
}