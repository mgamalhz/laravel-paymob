<?php

namespace Paymob\Laravel\Events;

use Paymob\Laravel\DTO\PaymobWebhookPayload;

final class PaymobWebhookReceived
{
    public function __construct(
        public readonly PaymobWebhookPayload $payload,
    ) {
    }
}
