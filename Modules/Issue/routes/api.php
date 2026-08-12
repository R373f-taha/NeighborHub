<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Issue\app\Http\Controllers\V1\IssueCategoryController;
use Modules\Issue\app\Http\Controllers\V1\IssueController;

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
        )->middleware([
            'can:view_issues',
        ]);



        Route::prefix('communities/{communityId}/issues')
            ->group(function () {



                Route::get('/',[IssueController::class, 'index']
                )->middleware([
                    'manager.superadmin.resident',
                    'can:view_issues',
                ]);




                Route::post('/', [IssueController::class, 'store']
                )->middleware([
                    'resident.of.community',
                    'can:create_issue',
                ]);




              Route::get('/{issue}', [IssueController::class, 'show'])
           ->whereNumber('issue')->middleware([
        'manager.superadmin.resident',
        'can:view_issues',
                      ]);




                Route::put('/{issue}',[IssueController::class, 'update']
                )->whereNumber('issue')
                 ->middleware([
                     'manager.superadmin.resident',
                     'can:update_issue',
                 ]);




                Route::patch('/{issue}/assign',[IssueController::class, 'assign']
                )->whereNumber('issue')
                 ->middleware([
                     'manager.or.super.admin',
                     'can:assign_issue',
                 ]);



                Route::patch('/{issue}/status',[IssueController::class, 'updateStatus']
                )->whereNumber('issue')
                 ->middleware([
                     'manager.superadmin.or.assigned.provider',
                     'can:update_issue_status',
                 ]);




                Route::post('/{issue}/updates', [IssueController::class, 'addUpdate']
                )->whereNumber('issue')
                 ->middleware([
                     'manager.superadmin.or.assigned.provider',
                     'can:add_issue_update',
                 ]);


                Route::get( '/{issue}/updates',[IssueController::class, 'updates']
                )->whereNumber('issue')
                 ->middleware([
                     'manager.superadmin.resident',
                     'can:view_issues',
                 ]);




                Route::delete('/{issue}',[IssueController::class, 'destroy']
                )->whereNumber('issue')
                 ->middleware([
                     'manager.or.super.admin',
                     'can:delete_issue',
                 ]);




                Route::post('/{issue}/comments',[IssueController::class, 'addComment']
                )->whereNumber('issue')
                 ->middleware([
                     'resident.of.community',
                     'can:comment_issue',
                 ]);
            });
    });