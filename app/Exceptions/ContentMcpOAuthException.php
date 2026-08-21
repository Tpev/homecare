<?php

namespace App\Exceptions;

use RuntimeException;

class ContentMcpOAuthException extends RuntimeException
{
    public function __construct(
        public readonly string $oauthError,
        string $description,
        public readonly int $httpStatus = 400,
    ) {
        parent::__construct($description);
    }
}
