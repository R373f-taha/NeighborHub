<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Issue\app\Http\Controllers\V1\IssueCategoryController;
use Modules\Issue\app\Http\Controllers\V1\IssueController;

Route::middleware('auth:sanctum')
    ->prefix('v1')
    ->group(function () {

        Route::get(
            'issue-categories',
            [IssueCategoryController::class, 'index']
        )->middleware('can:view_issues');


        Route::prefix('communities/{communityId}/issues')
            ->group(function () {

                Route::get(
                    '/',
                    [IssueController::class, 'index']
                )->middleware('can:view_issues');


                // Resident must belong to this community
                Route::post(
                    '/',
                    [IssueController::class, 'store']
                )->middleware([
                    'resident',
                    'resident.of.community',
                    'can:create_issue',
                ]);


                // Show Issue
                Route::get(
                    '/{issue}',
                    [IssueController::class, 'show']
                )->middleware('can:view_issues');


                // Update Issue
                Route::put(
                    '/{issue}',
                    [IssueController::class, 'update']
                )->middleware([
                    'manager.or.super.admin',
                    'can:update_issue',
                ]);


                // Assign Issue
                Route::patch(
                    '/{issue}/assign',
                    [IssueController::class, 'assign']
                )->whereNumber('issue')->middleware([
                    'manager.or.super.admin',
                    'can:assign_issue',
                ]);


                // Update Issue Status
                Route::patch(
                    '/{issue}/status',
                    [IssueController::class, 'updateStatus']
                )->middleware([
                    'manager.or.superadmin.or.Provider',
                    'can:update_issue_status',
                ]);


                // Add Issue Update
                Route::post(
                    '/{issue}/updates',
                    [IssueController::class, 'addUpdate']
                )->middleware([
                    'manager.or.superadmin.or.Provider',
                    'can:add_issue_update',
                ]);


                // Get Updates History
                Route::get(
                    '/{issue}/updates',
                    [IssueController::class, 'updates']
                )->middleware('can:view_issues');


                // Delete Issue
                Route::delete(
                    '/{issue}',
                    [IssueController::class, 'destroy']
                )->middleware([
                    'manager.or.super.admin',
                    'can:delete_issue',
                ]);


                // Add Comment
                Route::post(
                    '/{issue}/comments',
                    [IssueController::class, 'addComment']
                )->middleware('can:comment_issue');

            });
    });