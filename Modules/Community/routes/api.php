<?php

use Illuminate\Support\Facades\Route;
use Modules\Community\Http\Controllers\CommunityController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('communities', CommunityController::class)->names('community');
});
