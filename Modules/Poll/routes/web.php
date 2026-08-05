<?php

use Illuminate\Support\Facades\Route;
use Modules\Poll\app\Http\Controllers\V1\PollController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('polls', PollController::class)->names('poll');
});
