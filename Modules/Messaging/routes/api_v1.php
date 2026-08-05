<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\app\Http\Middleware\EnsureUserIsActive;
use Modules\Messaging\app\Http\Controllers\Api\V1\ConversationController;

Route::middleware(['auth:sanctum', EnsureUserIsActive::class])
    ->prefix('v1/communities/{community}')
    ->name('v1.communities.conversations.')
    ->controller(ConversationController::class)
    ->group(function (): void {
        // Core Messaging API only: list conversations, read message history,
        // send a message, and advance the per-participant read cursor.
        Route::get('conversations', 'index')->name('index');
        Route::get('conversations/{conversation}/messages', 'messages')->name('messages.index');
        Route::post('conversations/{conversation}/messages', 'store')
            ->middleware('throttle:messaging-send')
            ->name('messages.store');
        Route::patch('conversations/{conversation}/read', 'readUpdate')->name('read.update');
    });
