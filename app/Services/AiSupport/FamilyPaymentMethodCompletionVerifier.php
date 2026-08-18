<?php

namespace App\Services\AiSupport;

use App\Exceptions\Payments\PaymentException;
use App\Models\AiSupportGuidedTask;
use App\Models\User;

class FamilyPaymentMethodCompletionVerifier implements AiSupportCompletionVerifier
{
    public function __construct(private readonly FamilyPaymentMethodStatusReader $reader) {}

    public function id(): string
    {
        return 'family_payment_method_v1';
    }

    public function verify(User $actor, AiSupportGuidedTask $task): CompletionVerificationResult
    {
        try {
            $status = $this->reader->read($actor);
        } catch (PaymentException) {
            return new CompletionVerificationResult(
                'unverified',
                'verification_unavailable',
                "I couldn't verify the saved payment method right now. I have not marked this complete. You can check again or talk to a person.",
            );
        }

        if (! $status['ready'] || ! $status['card']) {
            return new CompletionVerificationResult(
                'unverified',
                'payment_method_not_ready',
                'I checked again, but there is not a ready saved payment method yet. Nothing has been marked complete. You can try again or talk to a person.',
                $status['state_hash'],
            );
        }

        return new CompletionVerificationResult(
            'verified',
            'payment_method_verified',
            sprintf(
                'Your saved payment method ending in %s is ready. The card details stayed in the secure payment page.',
                $status['card']['last4'],
            ),
            $status['state_hash'],
        );
    }
}
