<?php

namespace App\Support\AiSupport;

final readonly class AiSupportEligibilityResult
{
    /** @param array<string, int|string|null> $evidence */
    public function __construct(
        public bool $allowed,
        public string $reasonCode,
        public ?string $grantId = null,
        public array $evidence = [],
    ) {}

    /** @param array<string, int|string|null> $evidence */
    public static function deny(string $reasonCode, array $evidence = []): self
    {
        return new self(false, $reasonCode, null, $evidence);
    }

    /** @param array<string, int|string|null> $evidence */
    public static function allow(?string $grantId = null, array $evidence = []): self
    {
        return new self(true, 'eligible', $grantId, $evidence);
    }
}
