<?php

namespace Modules\Notification\app\Providers;


use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;



class RouteServiceProvider extends ServiceProvider
{


    protected string $name = 'Notification';



    public function boot(): void
    {
        parent::boot();
    }




    public function map(): void
    {

        Route::middleware('api')
            ->prefix('api')
            ->group(
                module_path(
                    $this->name,
                    '/routes/api.php'
                )
            );

    }

}