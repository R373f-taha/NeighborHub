<?php

declare(strict_types=1);

namespace Modules\Notification\app\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Notification\app\Http\Resources\Api\V1\NotificationCollection;
use Modules\Notification\app\Http\Resources\Api\V1\NotificationResource;
use Modules\Notification\app\Models\Notification;
use Modules\Notification\app\Repositories\Contracts\NotificationRepositoryInterface;
use Modules\Notification\app\Services\NotificationService;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationRepositoryInterface $repository,
        private NotificationService $service
    ) {
    }


    public function index(): NotificationCollection
    {
        return new NotificationCollection(

            $this->repository->paginateForUser(
                auth()->id()
            )

        );
    }


    public function show(
        Notification $notification
    ): NotificationResource {

        abort_if(
            $notification->user_id !== auth()->id(),
            403
        );

        return new NotificationResource(
            $notification
        );
    }


    public function read(
        Notification $notification
    ): JsonResponse {

        abort_if(
            $notification->user_id !== auth()->id(),
            403
        );

        $this->service->markAsRead(
            $notification
        );

        return response()->json([
            'message' => 'Notification marked as read'
        ]);
    }


    public function destroy(
        Notification $notification
    ): JsonResponse {

        abort_if(
            $notification->user_id !== auth()->id(),
            403
        );

        $this->service->delete(
            $notification
        );

        return response()->json([
            'message' => 'Notification deleted successfully'
        ]);
    }
}