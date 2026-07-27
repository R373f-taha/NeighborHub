<?php

use Illuminate\Support\Facades\Route;
use Modules\ServiceListing\app\Http\Controllers\ServiceListingController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('servicelistings', ServiceListingController::class)->names('servicelisting');
});
