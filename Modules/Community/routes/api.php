<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Community\app\Http\Controllers\V1\AnnouncementController;
use Modules\Community\app\Http\Controllers\CommunityController;


Route::middleware([
    'auth:sanctum',
])
->prefix('v1')
->group(function () {


    Route::apiResource( 'communities',CommunityController::class )->names('community');



    Route::prefix('communities/{communityId}')
        ->group(function () {


            Route::get(
                'announcements',
                [
                    AnnouncementController::class,
                    'index'
                ]
            );



            Route::post(
                'announcements',
                [
                    AnnouncementController::class,
                    'store'
                ]
            );



            Route::get(
                'announcements/{announcement}',
                [
                    AnnouncementController::class,
                    'show'
                ]
            );



Route::put(
    'announcements/{announcement}', [AnnouncementController::class,'update']
);


            Route::delete(
                'announcements/{announcement}',
                [ AnnouncementController::class, 'destroy']
            );



            Route::post(
                'announcements/{announcement}/react',
                [AnnouncementController::class, 'react']
            );


        });

});