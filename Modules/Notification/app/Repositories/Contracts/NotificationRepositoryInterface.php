<?php

declare(strict_types=1);

namespace Modules\Notification\app\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Notification\app\Models\Notification;

interface NotificationRepositoryInterface
{
    public function create(
        array $data
    ): Notification;

    public function paginateForUser(
        int $userId,
        int $perPage = 15
    ): LengthAwarePaginator;

    public function markAsRead(
        Notification $notification
    ): bool;

    public function delete(
        Notification $notification
    ): bool;

    public function unreadCount(
        int $userId
    ): int;
}