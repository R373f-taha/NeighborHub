<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\app\Http\Middleware\EnsureUserIsActive;
use Modules\ServiceListing\app\Http\Controllers\Api\V1\ServiceListingController;

Route::middleware(['auth:sanctum', EnsureUserIsActive::class])
    ->prefix('v1/communities/{community}')
    ->name('v1.communities.service-listings.')
    ->scopeBindings()
    ->controller(ServiceListingController::class)
    ->group(function (): void {
        Route::get('service-listings', 'index')->name('index');
        Route::post('service-listings', 'store')->name('store');
        Route::get('service-listings/{service_listing}', 'show')->name('show');
        Route::match(['put', 'patch'], 'service-listings/{service_listing}', 'update')->name('update');
        Route::delete('service-listings/{service_listing}', 'destroy')->name('destroy');
        Route::patch('service-listings/{service_listing}/status', 'updateStatus')->name('status.update');
    });
