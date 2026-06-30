<?php

namespace Paymob\Laravel;
use Illuminate\Support\ServiceProvider;
class PaymobServiceProvider extends  ServiceProvider
{

    public function boot() {
        $this->publishes([
            __DIR__.'/../config/paymob.php' => config_path('paymob.php'),
        ] , 'paymob-config');


    }

    public function register() {

        $this->mergeConfigFrom(__DIR__.'/../config/paymob.php', 'paymob');

        $this->app->singleton(PaymobClient::class, function ($app) {
            return new PaymobClient($app['config']->get('paymob'));
        });

        $this->app->alias(PaymobClient::class, 'paymob');
    }

}