<?php

declare(strict_types=1);

namespace Modules\Notification\app\Services;

use Modules\Auth\app\Models\User;
use Modules\Notification\app\Models\Notification;
use Modules\Notification\app\Repositories\Contracts\NotificationRepositoryInterface;

class NotificationService
{
    public function __construct(
        private NotificationRepositoryInterface $repository
    ) {
    }


    public function send(
        User $user,
        string $title,
        string $body,
        string $type,
        object $model,
        array $data = []
    ): Notification {

        return $this->repository->create([

            'user_id' => $user->id,

            'title' => $title,

            'body' => $body,

            'type' => $type,

            'data' => $data,

            'notifiable_type' => $model::class,

            'notifiable_id' => $model->id,

        ]);
    }


    public function markAsRead(
        Notification $notification
    ): bool {

        return $this->repository
            ->markAsRead($notification);

    }


    public function delete(
        Notification $notification
    ): bool {

        return $this->repository
            ->delete($notification);

    }
}