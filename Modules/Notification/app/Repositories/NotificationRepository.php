<?php

declare(strict_types=1);

namespace Modules\Notification\app\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Notification\app\Models\Notification;
use Modules\Notification\app\Repositories\Contracts\NotificationRepositoryInterface;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function create(
        array $data
    ): Notification {

        return Notification::create($data);

    }


    public function paginateForUser(
        int $userId,
        int $perPage = 15
    ): LengthAwarePaginator {

        return Notification::query()

            ->where(
                'user_id',
                $userId
            )

            ->latest()

            ->paginate($perPage);
    }


    public function markAsRead(
        Notification $notification
    ): bool {

        return $notification->update([
            'read_at' => now(),
        ]);

    }


    public function delete(
        Notification $notification
    ): bool {

        return (bool) $notification->delete();

    }


    public function unreadCount(
        int $userId
    ): int {

        return Notification::query()

            ->where(
                'user_id',
                $userId
            )

            ->whereNull('read_at')

            ->count();
    }
}