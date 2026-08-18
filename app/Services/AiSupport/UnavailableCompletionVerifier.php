<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportGuidedTask;
use App\Models\User;

class UnavailableCompletionVerifier implements AiSupportCompletionVerifier
{
    public function id(): string
    {
        return 'unavailable_v1';
    }

    public function verify(User $actor, AiSupportGuidedTask $task): CompletionVerificationResult
    {
        return new CompletionVerificationResult(
            'unverified',
            'verifier_unavailable',
            'I cannot safely verify that change from here, so I have not marked it complete. Check the page for the current status, or talk to a person.',
        );
    }
}
