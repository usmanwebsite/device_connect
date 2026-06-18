<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Services\MenuService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\MenuService::class, function ($app) {
            return new \App\Services\MenuService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(MenuService $menuService)
    {
        // Share menu data with all views
        View::share('angularMenu', $menuService->getAngularMenu());
    }
}
