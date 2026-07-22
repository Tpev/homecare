<?php

namespace App\Exceptions\Payments;

class PaymentActionRequiredException extends PaymentException
{
    public function __construct(
        string $userMessage,
        string $debugMessage = '',
        public readonly ?string $paymentIntentId = null,
        public readonly ?string $clientSecret = null,
    ) {
        parent::__construct($userMessage, $debugMessage);
    }
}
