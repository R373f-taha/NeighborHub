<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\app\Http\Middleware\EnsureUserIsActive;
use Modules\Media\app\Http\Controllers\Api\V1\MediaController;

Route::middleware(['auth:sanctum', EnsureUserIsActive::class])
    ->prefix('v1/communities/{community}')
    ->name('api.v1.communities.')
    ->group(function (): void {
        Route::scopeBindings()->controller(MediaController::class)->group(function (): void {
            Route::post('posts/{post}/media', 'uploadPost')->name('posts.media.store');
            Route::post('service-listings/{service_listing}/media', 'uploadServiceListing')->name('service-listings.media.store');
            Route::patch('posts/{post}/media/reorder', 'reorderPost')->name('posts.media.reorder');
            Route::patch('service-listings/{service_listing}/media/reorder', 'reorderServiceListing')->name('service-listings.media.reorder');
        });

     
        Route::delete('media/{media}', [MediaController::class, 'delete'])->name('media.destroy');
    });
