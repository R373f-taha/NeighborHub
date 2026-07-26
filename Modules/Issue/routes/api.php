<?php

use Illuminate\Support\Facades\Route;
use Modules\Issue\Http\Controllers\IssueController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('issues', IssueController::class)->names('issue');
});
