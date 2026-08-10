<?php

use App\Http\Middleware\RequestLoggerMiddleware;
use App\Http\Middleware\Security\CorsMiddleware;
use App\Http\Middleware\Security\RequestValidatorMiddleware;
use App\Http\Middleware\Security\HeadersMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Modules\Community\app\Http\Middleware\ManagerOrSuperAdminMiddleware;
use Modules\Community\app\Http\Middleware\ManagerSuperAdminOrProviderMiddleware;
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
            'managerSuperAdminOrProvider' => ManagerSuperAdminOrProviderMiddleware::class,
              'residentOfCommunity' => ResidentOfCommunityMiddleware::class,
        ]);
        $middleware->append(RequestLoggerMiddleware::class);
    $middleware->append(RequestValidatorMiddleware::class);
    $middleware->append(HeadersMiddleware::class);
    $middleware->append(CorsMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
