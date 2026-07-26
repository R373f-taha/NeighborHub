<?php

use Illuminate\Support\Facades\Route;
use Modules\Issue\Http\Controllers\IssueController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('issues', IssueController::class)->names('issue');
});
