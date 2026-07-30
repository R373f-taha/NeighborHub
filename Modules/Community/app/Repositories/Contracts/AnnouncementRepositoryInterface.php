<?php

declare(strict_types=1);

namespace Modules\Community\app\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Announcement;

interface AnnouncementRepositoryInterface
{
    /**
     * Get announcements for specific community.
     */
    public function paginateByCommunity(
        int $communityId,
        int $perPage = 15
    ): LengthAwarePaginator;


    /**
     * Find announcement by id inside community.
     */
    public function findById(
        int $communityId,
        int $announcementId
    ): ?Announcement;


    /**
     * Create new announcement.
     */
    public function create(
        array $data
    ): Announcement;


    /**
     * Update announcement.
     */
    public function update(
        Announcement $announcement,
        array $data
    ): bool;


    /**
     * Delete announcement.
     */
    public function delete(
        Announcement $announcement
    ): bool;


    /**
     * Check if user already reacted.
     */
    public function hasUserReacted(
        Announcement $announcement,
        User $user
    ): bool;
}