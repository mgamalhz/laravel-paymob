<?php

namespace Paymob\Laravel;

use Barryvdh\DomPDF\ServiceProvider as DompdfServiceProvider;
use Paymob\Laravel\Contracts\PaymobClientContract;
use Paymob\Laravel\Contracts\PaymobReceiptRenderer;
use Paymob\Laravel\Receipts\DompdfPaymobReceiptRenderer;
use Illuminate\Support\ServiceProvider;

class PaymobServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'paymob');

        $this->publishes([
            __DIR__ . '/../config/paymob.php' => config_path('paymob.php'),
        ], 'paymob-config');
        if (file_exists(__DIR__ . '/Routes/paymob.php')) {
            $this->loadRoutesFrom(__DIR__ . '/Routes/paymob.php');
        }

        $this->publishes([
            __DIR__ . '/Routes/paymob.php' => base_path('routes/paymob.php'),
        ]);
        $this->loadRoutesFrom(__DIR__ . '/Routes/paymob.php');
        $this->publishes([
            __DIR__ . '/Routes/paymob.php' => base_path('routes/paymob.php'),
        ], 'paymob-routes');
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'paymob-migrations');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/paymob.php', 'paymob');

        if (class_exists(DompdfServiceProvider::class) && ! $this->app->bound('dompdf.wrapper')) {
            $this->app->register(DompdfServiceProvider::class);
        }

        $this->app->singleton(PaymobClient::class, function ($app) {
            return new PaymobClient($app['config']->get('paymob'));
        });

        $this->app->singleton(PaymobClientContract::class, function ($app) {
            return $app->make(PaymobClient::class);
        });

        $this->app->bind(PaymobReceiptRenderer::class, function ($app) {
            return new DompdfPaymobReceiptRenderer($app->make('dompdf.wrapper'));
        });

        $this->app->alias(PaymobClientContract::class, 'paymob');
    }
}
