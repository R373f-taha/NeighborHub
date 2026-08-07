<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Modules\Community\app\Http\Middleware\ManagerOrSuperAdminMiddleware;
use Modules\Community\app\Http\Middleware\ResidentOfCommunityMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
         $middleware->alias([
            'managerOrSuperAdmin' => ManagerOrSuperAdminMiddleware::class,
              'residentOfCommunity' => ResidentOfCommunityMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
