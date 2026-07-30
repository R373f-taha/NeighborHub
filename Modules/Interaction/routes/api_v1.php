<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\app\Http\Middleware\EnsureUserIsActive;
use Modules\Interaction\app\Http\Controllers\Api\V1\CommentController;
use Modules\Interaction\app\Http\Controllers\Api\V1\ReactionController;

Route::middleware(['auth:sanctum', EnsureUserIsActive::class])
    ->prefix('v1/communities/{community}/posts/{post}')
    ->scopeBindings()
    ->group(function (): void {
        Route::name('v1.communities.posts.comments.')
            ->controller(CommentController::class)
            ->group(function (): void {
                Route::get('comments', 'index')->name('index');
                Route::post('comments', 'store')->name('store');
                Route::patch('comments/{comment}', 'update')->name('update');
                Route::delete('comments/{comment}', 'destroy')->name('destroy');
            });

        Route::post('react', [ReactionController::class, 'react'])
            ->name('v1.communities.posts.react');
    });

