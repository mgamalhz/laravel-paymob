<?php

namespace Paymob\Laravel\Tests;

use Paymob\Laravel\PaymobServiceProvider;

abstract class TestCase  extends  \Orchestra\Testbench\TestCase {

    protected function getPackageProviders($app)
    {
        return  [PaymobServiceProvider::class];
    }

}