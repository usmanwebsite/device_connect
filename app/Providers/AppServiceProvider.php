<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Services\MenuService;
use App\Models\SyncSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Config;

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

                try {
            $sync = SyncSetting::first(); // Get the only row
            if ($sync && !empty($sync->db_password)) {
                Config::set('database.connections.mysql_second', [
                    'driver'   => 'mysql',
                    'host'     => $sync->ip_host,
                    'port'     => env('DB_SECOND_PORT', '3306'), // port can still come from .env or add column
                    'database' => $sync->db_name,
                    'username' => $sync->db_user,
                    'password' => Crypt::decryptString($sync->db_password), // decrypt stored password
                    'charset'  => 'utf8mb4',
                    'collation'=> 'utf8mb4_unicode_ci',
                    'prefix'   => '',
                    'strict'   => true,
                    'engine'   => null,
                ]);
            }
        } catch (\Exception $e) {

        }

    }
}
