<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\app\Http\Controllers\AuthController;
use Modules\Auth\app\Http\Controllers\PasswordController;
use Modules\Auth\app\Http\Middleware\EnsureUserIsActive;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])
            ->name('register')
            ->middleware('throttle:auth-register');

        Route::post('login', [AuthController::class, 'login'])
            ->name('login')
            ->middleware(['throttle:auth-login-email', 'throttle:auth-login-ip']);

        Route::post('forgot-password', [PasswordController::class, 'forgotPassword'])
            ->name('forgot-password')
            ->middleware(['throttle:auth-forgot-email', 'throttle:auth-forgot-ip']);

        Route::post('reset-password', [PasswordController::class, 'resetPassword'])
            ->name('reset-password')
            ->middleware('throttle:auth-reset');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');

            Route::get('me', [AuthController::class, 'me'])
                ->name('me')
                ->middleware(EnsureUserIsActive::class);

            Route::put('password', [PasswordController::class, 'change'])
                ->name('password')
                ->middleware(EnsureUserIsActive::class);
        });
    });
});
