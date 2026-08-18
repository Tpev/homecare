<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportGuidedTask;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Auth\Access\AuthorizationException;

class FamilyPaymentAttentionCompletionVerifier implements AiSupportCompletionVerifier
{
    public function __construct(
        private readonly FamilyPaymentTimeStateReader $reader,
        private readonly FamilyAccountContext $familyAccounts,
    ) {}

    public function id(): string
    {
        return 'family_payment_attention_v1';
    }

    public function verify(User $actor, AiSupportGuidedTask $task): CompletionVerificationResult
    {
        if (! in_array($task->resource_type, ['care_request', 'care_plan'], true) || ! $task->resource_id) {
            return new CompletionVerificationResult(
                'unverified',
                'payment_reference_missing',
                'I cannot safely re-check this payment without its exact care reference. Nothing has been marked complete.',
            );
        }

        try {
            $account = $this->familyAccounts->account($actor);
            $ownsResource = $task->resource_type === 'care_request'
                ? CareRequest::query()->forFamilyAccount($account)->whereKey($task->resource_id)->exists()
                : CarePlan::query()->forFamilyAccount($account)->whereKey($task->resource_id)->exists();
            if (! $ownsResource) {
                return new CompletionVerificationResult(
                    'unverified',
                    'payment_reference_unavailable',
                    'I cannot safely re-check that payment for this Family account. Nothing has been marked complete.',
                );
            }
            $state = $this->reader->latestPaymentAttention(
                $actor,
                (string) $task->resource_type,
                (int) $task->resource_id,
            );
        } catch (AuthorizationException) {
            return new CompletionVerificationResult(
                'unverified',
                'payment_verification_unauthorized',
                'I cannot safely re-check that payment for this Family account. Nothing has been marked complete.',
            );
        }

        if ($state !== null) {
            return new CompletionVerificationResult(
                'unverified',
                'payment_'.$state['reason_code'].'_still_needs_attention',
                'I checked again. This payment still needs attention. '.$state['reason'].' '.$state['recovery'].' Nothing has been marked complete.',
                $state['state_hash'],
            );
        }

        return new CompletionVerificationResult(
            'verified',
            'payment_attention_cleared',
            'I checked again. This care payment no longer needs attention.',
            hash('sha256', implode(':', [
                $task->resource_type,
                $task->resource_id,
                'payment_attention_cleared',
            ])),
        );
    }
}
