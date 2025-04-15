<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AdminConsignServices;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AdminConsignServices::class, function ($app) {
            return new AdminConsignServices();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
