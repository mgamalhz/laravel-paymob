<?php

namespace Paymob\Laravel;

use Illuminate\Support\ServiceProvider;
use Paymob\Laravel\Contracts\PaymobClientContract;

class PaymobServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/paymob.php' => config_path('paymob.php'),
        ], 'paymob-config');
        $this->loadRoutesFrom(__DIR__.'/Routes/paymob.php');
        $this->publishes([
            __DIR__.'/Routes/paymob.php' => base_path('routes/paymob.php'),
        ], 'paymob-routes');
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'paymob-migrations');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/paymob.php', 'paymob');

        $this->app->singleton(PaymobClient::class, function ($app) {
            return new PaymobClient($app['config']->get('paymob'));
        });

        $this->app->singleton(PaymobClientContract::class, function ($app) {
            return $app->make(PaymobClient::class);
        });

        $this->app->alias(PaymobClientContract::class, 'paymob');
    }
}
