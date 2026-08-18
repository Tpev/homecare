<?php

namespace App\Services\AiSupport;

class AiSupportCompletionVerifierRegistry
{
    public function __construct(
        private readonly FamilyPaymentMethodCompletionVerifier $paymentMethod,
        private readonly FamilyPaymentAttentionCompletionVerifier $paymentAttention,
        private readonly CareRequestReceiptCompletionVerifier $careRequestReceipt,
        private readonly UnavailableCompletionVerifier $unavailable,
    ) {}

    public function for(?string $verifierId): AiSupportCompletionVerifier
    {
        return match ($verifierId) {
            'family_payment_method_v1' => $this->paymentMethod,
            'family_payment_attention_v1' => $this->paymentAttention,
            'care_request_receipt_v1' => $this->careRequestReceipt,
            default => $this->unavailable,
        };
    }

    public function has(string $verifierId): bool
    {
        return in_array($verifierId, [
            'family_payment_method_v1', 'family_payment_attention_v1', 'care_request_receipt_v1',
            'care_request_draft_state_v1', 'authoritative_family_state_v1', 'unavailable_v1',
        ], true);
    }
}
