<?php

namespace Paymob\Laravel\Exceptions;

use Throwable;

class PaymobDomainException extends PaymobException
{
    public function __construct(
        string $message,
        private readonly ?int $status = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function status(): ?int
    {
        return $this->status;
    }
}
