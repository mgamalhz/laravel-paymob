<?php

namespace Paymob\Laravel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Paymob\Laravel\Contracts\PaymobClientContract;
use Paymob\Laravel\DTO\AuthenticationResponseDto;
use Paymob\Laravel\DTO\IntentionResponseDto;
use Paymob\Laravel\DTO\PaymentKeyResponseDto;
use Paymob\Laravel\DTO\PaymentMethodDto;
use Paymob\Laravel\DTO\RegisterOrderData;
use Paymob\Laravel\DTO\RequestPaymentKeyData;
use RuntimeException;

class PaymobClient implements PaymobClientContract
{
    public function __construct(protected array $config)
    {
    }

    /**
     * @throws ConnectionException
     */
    public function authenticate(): AuthenticationResponseDto
    {

      $response = $this
          ->http( )
          ->post('/api/auth/tokens' , [
              'api_key' => config('paymob.api_key'),
          ]);

         $response->throw();

        $response =  $response->json();
        $authDto =  new AuthenticationResponseDto(
            $response['token'],
        );
        Cache::put("paymob_token"  , $authDto ,  now()->addMinutes(58) );

        return $authDto;

    }

    public function registerOrder(RegisterOrderData $data): IntentionResponseDto
    {
        $response = $this->http()
            ->withHeaders([
                'Authorization' => 'Token ' . $this->requiredSecretKey(),
            ])
            ->post("/v1/intention/", $data->toArray());

        $response->throw();

        $response = $response->json();

        return new IntentionResponseDto(
            id: $response['id'],
            clientSecret: $response['client_secret'],
            intentionOrderId: $response['intention_order_id'],
            amount: $response['intention_detail']['amount'] ?? $response['amount'],
            currency: $response['intention_detail']['currency'] ?? $response['currency'],
            status: $response['status'],
            paymentMethods: array_map(
                fn (array $paymentMethod): PaymentMethodDto => new PaymentMethodDto(
                    integrationId: $paymentMethod['integration_id'],
                    name: $paymentMethod['name'],
                    methodType: $paymentMethod['method_type'],
                    currency: $paymentMethod['currency'],
                ),
                $response['payment_methods'] ?? []
            ),
            created: $response['created'] ?? null,
        );
    }

    public function requestPaymentKey(RequestPaymentKeyData $data): PaymentKeyResponseDto
    {
        $response = $this->http()
            ->post('/api/acceptance/payment_keys', array_merge(
                ['auth_token' => $this->getToken()],
                $data->toArray(),
            ));

        $response->throw();

        $response = $response->json();

        return new PaymentKeyResponseDto(
            token: $response['token'],
        );
    }

    public function getApiKey(): string
    {
        return (string) ($this->config['api_key'] ?? '');
    }

    public function getSecretKey(): string
    {
        return (string) ($this->config['secret_key'] ?? '');
    }

    public function baseUrl(): string
    {
        return (string) ($this->config['base_url'] ?? '');
    }

    public function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 30);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function configs(): array
    {
        return $this->config;
    }



    private function http()
    {
        return Http::baseUrl($this->baseUrl())
            ->accept('application/json')
            ->asJson()
            ->retry(3 ,  100);
    }


    private function getToken(): string {
        return Cache::get("paymob_token")?->token ?? $this->authenticate()->token;
    }

    private function requiredSecretKey(): string
    {
        $secretKey = (string) ($this->config['secret_key'] ?? '');

        if ($secretKey === '') {
            throw new RuntimeException('Paymob secret key is required for payment intentions.');
        }

        return $secretKey;
    }




}
