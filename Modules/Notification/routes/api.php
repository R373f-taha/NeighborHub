<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Notification\app\Http\Controllers\Api\V1\NotificationController;

Route::middleware([
    'auth:sanctum',
])
->prefix('v1')
->group(function () {

    Route::get(
        'notifications',
        [NotificationController::class, 'index']
    );

    Route::get(
        'notifications/{notification}',
        [NotificationController::class, 'show']
    );

    Route::put(
        'notifications/{notification}/read',
        [NotificationController::class, 'read']
    );

    Route::delete(
        'notifications/{notification}',
        [NotificationController::class, 'destroy']
    );

});