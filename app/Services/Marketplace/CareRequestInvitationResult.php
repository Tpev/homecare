<?php

namespace App\Services\Marketplace;

use App\Models\CareRequestApplication;
use App\Models\CareRequestInvitation;

final readonly class CareRequestInvitationResult
{
    public function __construct(
        public string $state,
        public string $message,
        public ?CareRequestInvitation $invitation = null,
        public ?CareRequestApplication $application = null,
        public bool $sentNow = false,
    ) {}
}
