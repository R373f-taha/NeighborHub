<?php

use Illuminate\Support\Facades\Route;
use Modules\Messaging\Http\Controllers\MessagingController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('messagings', MessagingController::class)->names('messaging');
});
