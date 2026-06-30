<?php

namespace Paymob\Laravel;

class PaymobClient
{
       public function __construct(protected  array $config)
       {
       }

       public function getApiKey()
       {
           return $this->config['api_key'];
       }
       public function baseUrl() {
           return $this->config['base_url'];
       }
      public function timeout(): int
      {
          return (int) $this->config['timeout'];
      }

      public function config(string $key, mixed $default = null): mixed
      {
          return $this->config[$key] ?? $default;
      }

      public function configs(): array
      {
          return $this->config;
      }



}