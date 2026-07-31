<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\app\Http\Middleware\EnsureUserIsActive;
use Modules\Post\app\Http\Controllers\Api\V1\PostController;

Route::middleware(['auth:sanctum', EnsureUserIsActive::class])
    ->prefix('v1/communities/{community}')
    ->name('v1.communities.posts.')
    ->scopeBindings()
    ->controller(PostController::class)
    ->group(function (): void {
        Route::get('posts', 'index')->name('index');
        Route::post('posts', 'store')->name('store');
        Route::get('posts/{post}', 'show')->name('show');
        Route::patch('posts/{post}', 'update')->name('update');
        Route::delete('posts/{post}', 'destroy')->name('destroy');
    });
