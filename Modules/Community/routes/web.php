<?php

use Illuminate\Support\Facades\Route;
use Modules\Community\Http\Controllers\CommunityController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('communities', CommunityController::class)->names('community');
});
