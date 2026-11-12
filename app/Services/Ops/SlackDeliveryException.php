<?php

namespace App\Services\Ops;

use RuntimeException;

class SlackDeliveryException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $uncertain = false, public readonly bool $retryable = true)
    {
        parent::__construct($message);
    }
}
