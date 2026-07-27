<?php

use Illuminate\Support\Facades\Route;
use Modules\Media\app\Http\Controllers\MediaController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('media', MediaController::class)->names('media');
});
