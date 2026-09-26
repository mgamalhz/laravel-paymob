<?php

namespace Paymob\Laravel\Facade;

use Illuminate\Support\Facades\Facade;

class Paymob extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'paymob';
    }
}
