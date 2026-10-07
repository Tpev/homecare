<?php

namespace App\Services\FamilyAcquisition;

use App\Models\FamilyAccount;
use App\Models\FamilyOnboarding;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

class FamilyCrmSyncService
{
    /** Enrollment, completion and backfill share the same household lock and event keys. */
    public function sync(FamilyOnboarding $onboarding, array $attribution = []): ?Lead
    {
        return DB::transaction(function () use ($onboarding, $attribution) {
            $account = FamilyAccount::query()->lockForUpdate()->find($onboarding->family_account_id);
            $onboarding = $onboarding->fresh(['welcomeVisit']);
            $user = $account?->owner;
            if (! $account || ! $onboarding || $account->status !== FamilyAccount::STATUS_ACTIVE || ! $user
                || $user->role !== 'family' || $user->isAdministrator()
                || ! in_array($onboarding->status, ['in_progress', 'completed'], true)
                || (int) $account->owner_user_id !== (int) $onboarding->initiated_by_user_id) {
                return null;
            }
            $lead = Lead::query()->where('family_account_id', $account->id)->lockForUpdate()->first();
            if (! $lead) {
                // Preserve imported/manual leads and their source, assignment and outreach history.
                $lead = Lead::query()->where('lead_type', Lead::TYPE_FAMILY)->whereNull('family_account_id')
                    ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($user->email))])
                    ->latest('id')->lockForUpdate()->first();
            }
            if (! $lead) {
                // Registration already sends its own notifications; backfills must not send campaigns.
                $lead = Lead::withoutEvents(fn () => Lead::query()->create([
                    'family_account_id' => $account->id, 'lead_type' => Lead::TYPE_FAMILY,
                    'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone,
                    'status' => 'new', 'priority' => Lead::PRIORITY_NORMAL,
                    'source' => 'website_signup', 'source_detail' => $this->sourceDetail($attribution),
                    'source_url' => $attribution['url'] ?? null, 'referrer_url' => $attribution['referrer'] ?? null,
                    'submitted_at' => $onboarding->created_at,
                ]));
            }
            $lead->family_account_id = $account->id;
            $lead->name = $lead->name ?: $user->name;
            $lead->email = $lead->email ?: $user->email;
            $lead->phone = $lead->phone ?: $user->phone;
            $data = $lead->data ?? [];
            $data['website_signup'] ??= [
                'user_id' => $user->id, 'family_onboarding_id' => $onboarding->id,
                'registered_at' => $onboarding->created_at->toIso8601String(),
                'entry_point' => $onboarding->source, 'attribution' => $attribution,
            ];
            $lead->data = $data;
            $lead->save();

            $this->note($lead, 'family_signup:'.$account->id, 'Family account created',
                "Name: {$user->name}\nEmail: {$user->email}\nPhone: {$user->phone}\nEntry point: {$onboarding->source}"
                    ."\nSignup source: ".$this->sourceDetail($data['website_signup']['attribution'] ?? [])
                    .(! empty($attribution['utm_campaign']) ? "\nCampaign: {$attribution['utm_campaign']}" : '')
                    ."\nOnboarding started; care details will be added when submitted.",
                $onboarding->created_at);

            if ($onboarding->status === 'completed') {
                $body = collect($onboarding->submissionDetails())->map(fn ($row) => $row['label'].': '.$row['value'])->implode("\n");
                if ($this->note($lead, 'family_onboarding:'.$onboarding->id, 'Family onboarding completed', $body, $onboarding->completed_at)) {
                    $snapshot = $onboarding->submitted_snapshot ?? [];
                    $lead->phone = ($snapshot['phone'] ?? null) ?: $lead->phone;
                    $lead->zip = ($snapshot['zip'] ?? null) ?: $lead->zip;
                    $location = implode(', ', array_filter([$snapshot['city'] ?? null, $snapshot['state'] ?? null]));
                    $lead->location = $location ?: $lead->location;
                    $lead->save();
                    $this->advance($lead, ['new', 'attempting_contact', 'contacted'], 'qualified', 'Family onboarding completed', $onboarding->completed_at);
                }
            }

            $visit = $onboarding->welcomeVisit;
            if ($visit && $visit->revision > 0) {
                $confirmed = $visit->confirmed_start_at?->setTimezone($visit->timezone)->format('M j, Y g:i A');
                $body = 'Status: '.ucfirst($visit->status).($confirmed ? "\nConfirmed time: {$confirmed} ({$visit->timezone})" : '')
                    .($visit->staff_note ? "\nTeam note: {$visit->staff_note}" : '');
                if ($this->note($lead, "family_welcome_visit:{$visit->id}:{$visit->revision}", 'Welcome visit updated', $body, $visit->handled_at ?? $visit->updated_at)
                    && $visit->status === 'confirmed' && $visit->confirmed_start_at) {
                    $this->advance($lead, ['new', 'attempting_contact', 'contacted', 'qualified'], 'assessment_scheduled', 'Welcome visit confirmed', $visit->handled_at ?? $visit->updated_at);
                }
            }

            return $lead;
        }, 3);
    }

    private function note(Lead $lead, string $key, string $summary, string $body, $occurredAt): bool
    {
        if ($lead->activities()->where('metadata->event_key', $key)->exists()) {
            return false;
        }
        $lead->activities()->create([
            'type' => 'note', 'summary' => $summary, 'body' => $body,
            'occurred_at' => $occurredAt, 'metadata' => ['event_key' => $key, 'family_account_id' => $lead->family_account_id],
        ]);

        return true;
    }

    private function advance(Lead $lead, array $from, string $to, string $reason, $occurredAt): void
    {
        if ($lead->do_not_contact_at || $lead->converted_at || ! in_array($lead->status, $from, true)) {
            return;
        }
        $previous = $lead->status;
        $lead->update(['status' => $to]);
        $lead->activities()->create([
            'type' => 'stage_change', 'summary' => $reason.' → '.Lead::FAMILY_STAGES[$to],
            'occurred_at' => $occurredAt, 'metadata' => ['from' => $previous, 'to' => $to, 'automatic' => true],
        ]);
    }

    private function sourceDetail(array $attribution): string
    {
        $channel = $attribution['utm_source'] ?? parse_url($attribution['referrer'] ?? '', PHP_URL_HOST);
        $detail = $channel ? 'Website signup · '.$channel : 'Website signup';
        if (! empty($attribution['utm_medium'])) {
            $detail .= ' / '.$attribution['utm_medium'];
        }

        return mb_substr($detail, 0, 255);
    }
}
