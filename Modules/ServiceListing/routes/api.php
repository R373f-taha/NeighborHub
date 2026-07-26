<?php

use Illuminate\Support\Facades\Route;
use Modules\ServiceListing\Http\Controllers\ServiceListingController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('servicelistings', ServiceListingController::class)->names('servicelisting');
});
