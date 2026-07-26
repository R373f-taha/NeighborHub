<?php

use Illuminate\Support\Facades\Route;
use Modules\Poll\Http\Controllers\PollController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('polls', PollController::class)->names('poll');
});
