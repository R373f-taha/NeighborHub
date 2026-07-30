<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

use Modules\Community\app\Http\Controllers\V1\AnnouncementController;
use Modules\Community\app\Http\Controllers\CommunityController;
use Modules\Community\app\Http\Controllers\V1\CommunityController;
use Modules\Community\app\Http\Controllers\V1\StatsController;
use Modules\Community\app\Http\Controllers\V1\MembershipController;


Route::middleware([
    'auth:sanctum',
])
->prefix('v1')
->group(function () {


    Route::apiResource( 'communities',CommunityController::class )->names('community');



    Route::prefix('communities/{communityId}')
        ->group(function () {


            Route::get(
                'announcements',
                [
                    AnnouncementController::class,
                    'index'
                ]
            );



            Route::post(
                'announcements',
                [
                    AnnouncementController::class,
                    'store'
                ]
            );



            Route::get(
                'announcements/{announcement}',
                [
                    AnnouncementController::class,
                    'show'
                ]
            );



Route::put(
    'announcements/{announcement}', [AnnouncementController::class,'update']
);


            Route::delete(
                'announcements/{announcement}',
                [ AnnouncementController::class, 'destroy']
            );



            Route::post(
                'announcements/{announcement}/react',
                [AnnouncementController::class, 'react']
            );


        });

});
=
Route::prefix('api/v1')->group(function () {

    Route::get('communities', [CommunityController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {

        // ===== COMMUNITY CRUD =====
        Route::post('communities', [CommunityController::class, 'store'])
            ->middleware(['super.admin', 'throttle:10,60']); // 10 requests per minute

        Route::get('communities/{communityId}', [CommunityController::class, 'show'])
            ->middleware(['manager', 'throttle:60,60']); // 60 requests per minute

        Route::put('communities/{communityId}', [CommunityController::class, 'update'])
            ->middleware(['manager.or.super.admin', 'throttle:20,60']); // 20 requests per minute

        Route::delete('communities/{communityId}', [CommunityController::class, 'destroy'])
            ->middleware(['super.admin', 'throttle:5,60']); // 5 requests per minute (sensitive)

        // ===== COMMUNITY STATS =====
        Route::get('communities/{communityId}/stats', [StatsController::class, 'stats'])
            ->middleware(['manager.or.super.admin', 'throttle:30,60']); // 30 requests per minute

        Route::get('communities/{communityId}/residents', [StatsController::class, 'residents'])
            ->middleware(['manager', 'throttle:30,60']); // 30 requests per minute

        // ===== JOIN COMMUNITY =====
        Route::post('communities/{communityId}/join', [MembershipController::class, 'join'])
            ->middleware(['resident', 'throttle:5,60']); // 5 requests per minute (to prevent spam)

        // ===== RESIDENTS MANAGEMENT =====
        Route::post('communities/{communityId}/residents/{residentId}/approve', [MembershipController::class, 'approve'])
            ->middleware(['manager', 'throttle:20,60']); // 20 requests per minute

        Route::post('communities/{communityId}/residents/{residentId}/reject', [MembershipController::class, 'reject'])
            ->middleware(['manager', 'throttle:20,60']); // 20 requests per minute

        Route::post('communities/{communityId}/residents/{residentId}/suspend', [MembershipController::class, 'suspend'])
            ->middleware(['manager', 'throttle:20,60']); // 20 requests per minute

        // ===== MY RESIDENCY =====
        Route::get('residents/me', [MembershipController::class, 'myResidency'])
            ->middleware(['resident', 'throttle:60,60']); // 60 requests per minute
    });
});

