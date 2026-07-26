<?php

use Illuminate\Support\Facades\Route;
use Modules\Poll\Http\Controllers\PollController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('polls', PollController::class)->names('poll');
});
