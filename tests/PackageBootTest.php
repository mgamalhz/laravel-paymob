<?php

namespace Paymob\Laravel\Tests;

use Paymob\Laravel\Contracts\PaymobClientContract;
use Paymob\Laravel\PaymobClient;

class PackageBootTest extends TestCase
{
    public function test_it_registers_paymob_client_as_singleton(): void
    {
        $first = $this->app->make(PaymobClient::class);
        $second = $this->app->make(PaymobClient::class);

        $this->assertInstanceOf(PaymobClient::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_it_registers_paymob_alias(): void
    {
        $client = $this->app->make('paymob');

        $this->assertInstanceOf(PaymobClient::class, $client);
    }

    public function test_it_registers_paymob_contract(): void
    {
        $client = $this->app->make(PaymobClientContract::class);

        $this->assertInstanceOf(PaymobClient::class, $client);
        $this->assertInstanceOf(PaymobClientContract::class, $client);
    }

    public function test_it_loads_config(): void
    {
        $client = $this->app->make(PaymobClient::class);

        $this->assertSame('https://accept.paymob.com/api', $client->baseUrl());
        $this->assertSame(30, $client->timeout());
    }
}
