<?php

namespace App\Exceptions\Payments;

use RuntimeException;

class PaymentException extends RuntimeException
{
    public function __construct(
        public readonly string $userMessage,
        string $debugMessage = '',
    ) {
        parent::__construct($debugMessage !== '' ? $debugMessage : $userMessage);
    }
}

