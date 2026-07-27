<?php

use Illuminate\Support\Facades\Route;
use Modules\Interaction\app\Http\Controllers\InteractionController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('interactions', InteractionController::class)->names('interaction');
});
