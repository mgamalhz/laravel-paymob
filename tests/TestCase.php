<?php

namespace Paymob\Laravel\Tests;

use Paymob\Laravel\PaymobServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app)
    {
        return [PaymobServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('paymob.api_key', null);
        $app['config']->set('paymob.integration_id', null);
        $app['config']->set('paymob.iframe_id', null);
        $app['config']->set('paymob.secret_key', null);
        $app['config']->set('paymob.base_url', null);
        $app['config']->set('paymob.paymob_webhook_url', '/');
        $app['config']->set('paymob.hmac_secret', null);
    }
}
