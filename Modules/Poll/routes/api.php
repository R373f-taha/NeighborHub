<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Poll\App\Http\Controllers\V1\ChangePollStatusController;
use Modules\Poll\app\Http\Controllers\V1\PollController;
use Modules\Poll\App\Http\Controllers\V1\VotesManagementController;

Route::prefix('api/v1')->group(function () {

    Route::middleware(['auth:sanctum'])->group(function () {



        Route::prefix('communities/{communityId}')->group(function () {


            Route::get('polls', [PollController::class, 'index'])
              ->middleware('can:view_polls')
               ->name('polls.index');


            Route::post('polls', [PollController::class, 'store'])
                ->middleware(['manager.or.super.admin', 'can:create_poll'])
                ->name('polls.store');

            
            Route::get('polls/{pollId}', [PollController::class, 'show'])
                ->middleware('can:view_polls')
                ->name('polls.show');


            Route::post('polls/{pollId}/activate', [ChangePollStatusController::class, 'activate'])
                ->middleware(['manager.or.super.admin', 'can:create_poll'])
                ->name('polls.activate');


            Route::post('polls/{pollId}/close', [ChangePollStatusController::class, 'close'])
                ->middleware(['manager.or.super.admin', 'can:close_poll'])
                ->name('polls.close');


            Route::post('polls/{pollId}/vote', [VotesManagementController::class, 'vote'])
                ->middleware(['resident', 'can:vote_poll'])
                ->name('polls.vote');


            Route::get('polls/{pollId}/results', [VotesManagementController::class, 'results'])
                ->middleware('manager','can:view_poll_result')
                ->name('polls.results');
        });
    });
});
