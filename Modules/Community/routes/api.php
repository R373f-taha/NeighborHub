<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Community\app\Http\Controllers\V1\CommunityController;
use Modules\Community\app\Services\V1\MembershipController;

Route::prefix('api/v1')->group(function () {
    // Public
    Route::get('communities', [CommunityController::class, 'index']);

    // Authenticated
  //  Route::middleware('auth:sanctum')->group(function () {
        // Community CRUD
        Route::post('communities', [CommunityController::class, 'store']); // Super Admin
        Route::get('communities/{community}', [CommunityController::class, 'show']);
        Route::put('communities/{community}', [CommunityController::class, 'update']); // Super Admin / Manager
        Route::delete('communities/{community}', [CommunityController::class, 'destroy']); // Super Admin

        // Community Stats
        Route::get('communities/{communityId}/stats', [CommunityController::class, 'stats']); // Manager

        // Join Community
        Route::post('communities/{communityId}/join', [MembershipController::class, 'join'])->middleware('auth:sanctum'); // Resident

        // Residents Management
        Route::get('communities/{communityId}/residents', [CommunityController::class, 'residents']); // Manager
        Route::post('communities/{communityId}/residents/{residentId}/approve', [MembershipController::class, 'approve'])->middleware('auth:sanctum'); // Manager
        Route::post('communities/{communityId}/residents/{residentId}/reject', [MembershipController::class, 'reject']); // Manager
        Route::post('communities/{community}/residents/{resident}/suspend', [MembershipController::class, 'suspend']); // Manager

        // My Residency
        Route::get('residents/me', [CommunityController::class, 'myResidency'])->middleware('auth:sanctum'); // Resident
  //  });
});
