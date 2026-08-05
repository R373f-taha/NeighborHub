<?php
declare(strict_types=1);


use Illuminate\Support\Facades\Route;
use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Http\Controllers\V1\IssueController;
use Modules\Issue\app\Http\Controllers\V1\IssueCategoryController;

Route::model('issue', Issue::class);





Route::middleware('auth:sanctum')
    ->prefix('v1')
    ->group(function () {


        /*
        |--------------------------------------------------------------------------
        | Issue Categories
        |--------------------------------------------------------------------------
        */

        Route::get(
            'issue-categories',
            [IssueCategoryController::class, 'index']
        )
        ->middleware('can:view_issues');



        /*
        |--------------------------------------------------------------------------
        | Issues
        |--------------------------------------------------------------------------
        */


        Route::prefix('communities/{communityId}/issues')
            ->group(function () {


                // List

                Route::get(
                    '/',
                    [IssueController::class, 'index']
                )
                ->middleware('can:view_issues');



                // Create

                Route::post(
                    '/',
                    [IssueController::class, 'store']
                )
                ->middleware([
                    'resident',
                    'can:create_issue',
                ]);

                // Show

                Route::get(
                    '/{issue}',
                    [IssueController::class, 'show']
                )
                ->middleware('can:view_issues');

                // Update

                Route::put(
                    '/{issue}',
                    [IssueController::class, 'update']
                )
                ->middleware('can:update_issue');



                // Assign Provider
Route::patch(
    '/{issue}/assign',
    [IssueController::class, 'assign']
)
->whereNumber('issue')->middleware(['manager','can:assign_issue',]);



                // Update Status

                Route::patch(
                    '/{issue}/status',
                    [IssueController::class, 'updateStatus']
                )
                ->middleware([
                    'can:update_issue_status',
                ]);



Route::post(
    '/{issue}/updates',
    [IssueController::class, 'addUpdate']
)
->middleware('can:add_issue_update');

                // History

                Route::get(
    '/{issue}/updates',
    [IssueController::class, 'updates']
)
->middleware('can:view_issues');

                // Delete
                Route::delete('/{issue}',[IssueController::class, 'destroy'])
                ->middleware(['manager','can:delete_issue']);


Route::post('/{issue}/comments',[IssueController::class, 'addComment'])
->middleware('can:comment_issue');

            });

    });