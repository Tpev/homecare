<?php

namespace App\Services\AiSupport;

use App\Models\AiSupportGuidedTask;
use App\Models\User;

interface AiSupportCompletionVerifier
{
    public function id(): string;

    public function verify(User $actor, AiSupportGuidedTask $task): CompletionVerificationResult;
}
