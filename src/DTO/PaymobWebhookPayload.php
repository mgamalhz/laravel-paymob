<?php

namespace Paymob\Laravel\DTO;

use DateTimeImmutable;

final readonly class PaymobWebhookPayload
{
    public function __construct(
        public string $transactionId,
        public string $orderId,
        public int $amountCents,
        public string $status,
        public DateTimeImmutable $verifiedAt,
    ) {
    }
}
