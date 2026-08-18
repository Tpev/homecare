<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportGuidedTask;
use App\Models\CareRequest;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountContext;

class CareRequestReceiptCompletionVerifier implements AiSupportCompletionVerifier
{
    public function __construct(private readonly FamilyAccountContext $familyAccounts) {}

    public function id(): string
    {
        return 'care_request_receipt_v1';
    }

    public function verify(User $actor, AiSupportGuidedTask $task): CompletionVerificationResult
    {
        if ($task->resource_type !== 'care_request' || ! $task->resource_id) {
            return new CompletionVerificationResult('unverified', 'receipt_reference_missing', 'I cannot verify a live request without its secure receipt. Nothing has been marked complete.');
        }
        $account = $this->familyAccounts->membershipFor($actor, false)?->familyAccount;
        $request = $account ? CareRequest::query()->forFamilyAccount($account)->whereKey($task->resource_id)->first() : null;
        if (! $request || ! in_array($request->status, [CareRequest::STATUS_OPEN, CareRequest::STATUS_FILLED], true)) {
            return new CompletionVerificationResult('unverified', 'request_not_live', 'I checked, but I could not verify that this request is live. Nothing has been marked complete.');
        }

        return new CompletionVerificationResult(
            'verified',
            'care_request_receipt_verified',
            'Your care request is live. Eligible caregivers can see it, but no caregiver has been hired by this step.',
            hash('sha256', implode(':', [$request->id, $request->status, $request->updated_at?->getTimestamp()])),
        );
    }
}
