<?php

namespace App\Observers;

use App\Models\Lead;
use App\Services\FamilyAcquisition\FamilyLeadAlertService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class FamilyLeadObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly FamilyLeadAlertService $alerts) {}

    public function created(Lead $lead): void
    {
        if ($lead->lead_type !== Lead::TYPE_FAMILY) {
            return;
        }

        if (! $lead->submitted_at) {
            $lead->forceFill(['submitted_at' => $lead->created_at ?: now()])->saveQuietly();
        }

        $this->alerts->notifyNewLead($lead->fresh() ?? $lead);
    }
}
