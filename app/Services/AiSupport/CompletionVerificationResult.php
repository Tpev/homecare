<?php

namespace App\Services\AiSupport;

final readonly class CompletionVerificationResult
{
    public function __construct(
        public string $status,
        public string $resultCode,
        public string $message,
        public ?string $stateHash = null,
    ) {}

    public function verified(): bool
    {
        return $this->status === 'verified';
    }
}
