<?php

namespace App\Services\FamilyAcquisition;

use App\Mail\Ops\FamilyLeadAlertMail;
use App\Models\FamilyAcquisitionSetting;
use App\Models\Lead;
use Illuminate\Support\Facades\Mail;
use Throwable;

class FamilyLeadAlertService
{
    public function notifyNewLead(Lead $lead): bool
    {
        $lead = $lead->fresh() ?? $lead;

        if ($lead->new_lead_alerted_at || $this->isDemoLead($lead)) {
            return false;
        }

        $settings = FamilyAcquisitionSetting::query()->first();
        if (! $settings?->alerts_enabled || $settings->newLeadRecipients() === []) {
            return false;
        }

        try {
            Mail::to($settings->newLeadRecipients())->send(new FamilyLeadAlertMail($lead, FamilyLeadAlertMail::TYPE_NEW));
            $lead->forceFill(['new_lead_alerted_at' => now()])->saveQuietly();

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function notifyFirstCallEscalation(Lead $lead): bool
    {
        $lead = $lead->fresh() ?? $lead;

        if ($lead->first_call_at || $lead->first_call_escalated_at || $this->isDemoLead($lead)) {
            return false;
        }

        $settings = FamilyAcquisitionSetting::query()->first();
        if (! $settings?->alerts_enabled || $settings->escalationRecipients() === []) {
            return false;
        }

        try {
            Mail::to($settings->escalationRecipients())->send(new FamilyLeadAlertMail($lead, FamilyLeadAlertMail::TYPE_ESCALATION));
            $lead->forceFill(['first_call_escalated_at' => now()])->saveQuietly();

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function isDemoLead(Lead $lead): bool
    {
        return $lead->external_source === 'demo_family_acquisition' || (bool) data_get($lead->data, 'demo', false);
    }
}
