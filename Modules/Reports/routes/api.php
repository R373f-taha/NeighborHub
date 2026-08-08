<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Reports\app\Http\Controllers\ReportsController;

Route::middleware([
    'auth:sanctum',
    'managerOrSuperAdmin',
])
    ->prefix('communities/{communityId}/reports')
    ->group(function () {

        Route::get(
            'issues-summary',
            [ReportsController::class, 'issuesSummary']
        );

        Route::get(
            'engagement',
            [ReportsController::class, 'engagement']
        );

        Route::get(
            'providers',
            [ReportsController::class, 'providers']
        );

        Route::get(
            'services-activity',
            [ReportsController::class, 'servicesActivity']
        );
    });