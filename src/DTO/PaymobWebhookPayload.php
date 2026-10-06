<?php

namespace Paymob\Laravel\DTO;

use DateTimeImmutable;

final class PaymobWebhookPayload
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $orderId,
        public readonly int $amountCents,
        public readonly string $status,
        public readonly DateTimeImmutable $verifiedAt,
    ) {}
}
