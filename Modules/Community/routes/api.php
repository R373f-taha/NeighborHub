<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

use Modules\Community\app\Http\Controllers\V1\AnnouncementController;
use Modules\Community\app\Http\Controllers\V1\CommunityController;
use Modules\Community\app\Http\Controllers\V1\StatsController;
use Modules\Community\app\Http\Controllers\V1\MembershipController;


Route::middleware([
    'auth:sanctum',
])
->prefix('v1')
->group(function () {


    Route::apiResource( 'communities',CommunityController::class )->names('community');

    Route::prefix('communities/{communityId}') ->group(function () {


            Route::get( 'announcements',  [AnnouncementController::class, 'index'  ] );



     Route::post( 'announcements', [ AnnouncementController::class, 'store' ] );
            Route::get('announcements/{announcement}',[ AnnouncementController::class, 'show'] );
Route::put('announcements/{announcement}', [AnnouncementController::class,'update']);


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
Route::prefix('api/v1')->group(function () {

    Route::get('communities', [CommunityController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {

      // ===== COMMUNITY CRUD =====
    Route::post('communities', [CommunityController::class, 'store'])
    ->middleware(['super.admin', 'can:create_community', 'throttle:10,60']);

    Route::get('communities/{communityId}', [CommunityController::class, 'show'])
    ->middleware(['manager', 'can:view_communities', 'throttle:60,60']);

    Route::put('communities/{communityId}', [CommunityController::class, 'update'])
    ->middleware(['manager.or.super.admin', 'can:update_community', 'throttle:20,60']);

   Route::delete('communities/{communityId}', [CommunityController::class, 'destroy'])
    ->middleware(['super.admin', 'can:delete_community', 'throttle:5,60']);

// ===== COMMUNITY STATS =====
    Route::get('communities/{communityId}/stats', [StatsController::class, 'stats'])
    ->middleware(['manager.or.super.admin', 'can:view_community_stats', 'throttle:30,60']);

    Route::get('communities/{communityId}/residents', [StatsController::class, 'residents'])
    ->middleware(['manager', 'can:view_residents', 'throttle:30,60']);

// ===== JOIN COMMUNITY =====
    Route::post('communities/{communityId}/join', [MembershipController::class, 'join'])
    ->middleware(['resident', 'can:join_community', 'throttle:5,60']);

// ===== RESIDENTS MANAGEMENT =====
    Route::post('communities/{communityId}/residents/{residentId}/approve', [MembershipController::class, 'approve'])
    ->middleware(['manager', 'can:approve_resident', 'throttle:20,60']);

     Route::post('communities/{communityId}/residents/{residentId}/reject', [MembershipController::class, 'reject'])
    ->middleware(['manager', 'can:reject_resident', 'throttle:20,60']);

      Route::post('communities/{communityId}/residents/{residentId}/suspend', [MembershipController::class, 'suspend'])
    ->middleware(['manager', 'can:suspend_resident', 'throttle:20,60']);

// ===== MY RESIDENCY =====
     Route::get('residents/me', [MembershipController::class, 'myResidency'])
    ->middleware(['resident', 'can:view_my_residency', 'throttle:60,60']);
    });
});

