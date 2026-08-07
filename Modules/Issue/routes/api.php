<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

use Modules\Issue\app\Http\Controllers\V1\IssueController;
use Modules\Issue\app\Http\Controllers\V1\IssueCategoryController;


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



                // List Issues

                Route::get(
                    '/',
                    [IssueController::class, 'index']
                )
                ->middleware('can:view_issues');



                // Create Issue
                // Resident must belong to this community

                Route::post(
                    '/',
                    [IssueController::class, 'store']
                )
                ->middleware([
                    'resident',
                    'residentOfCommunity',
                    'can:create_issue',
                ]);



                // Show Issue

                Route::get(
                    '/{issue}',
                    [IssueController::class, 'show']
                )
                ->middleware('can:view_issues');



                // Update Issue

                Route::put(
                    '/{issue}',
                    [IssueController::class, 'update']
                )
                ->middleware(['managerOrSuperAdmin','can:update_issue',]);


                // Assign Provider
                // Manager must belong to this community

                Route::patch(
                    '/{issue}/assign',
                    [IssueController::class, 'assign']
                )
                ->whereNumber('issue')
                ->middleware([
                    'managerOrSuperAdmin',
                    'can:assign_issue',
                ]);



                // Update Status

                Route::patch(
                    '/{issue}/status',
                    [IssueController::class, 'updateStatus']
                )
                ->middleware(['managerOrSuperAdmin','can:update_issue_status',]);



                // Add Update Log

                Route::post(
                    '/{issue}/updates',
                    [IssueController::class, 'addUpdate']
                )->middleware([ 'managerOrSuperAdmin','can:add_issue_update',]);


                // Get Updates History

                Route::get(
                    '/{issue}/updates',
                    [IssueController::class, 'updates']
                )
                ->middleware('can:view_issues');



                // Delete Issue

                Route::delete(
                    '/{issue}',
                    [IssueController::class, 'destroy']
                )
                ->middleware([
                    'managerOrSuperAdmin',
                    'can:delete_issue',
                ]);



                // Comments

                Route::post(
                    '/{issue}/comments',
                    [IssueController::class, 'addComment']
                )
                ->middleware('can:comment_issue');


            });


    });