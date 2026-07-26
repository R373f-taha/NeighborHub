<?php

use Illuminate\Support\Facades\Route;
use Modules\Messaging\Http\Controllers\MessagingController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('messagings', MessagingController::class)->names('messaging');
});
