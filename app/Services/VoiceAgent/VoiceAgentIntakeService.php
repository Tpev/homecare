<?php

namespace App\Services\VoiceAgent;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\VoiceAiCall;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VoiceAgentIntakeService
{
    public function createLead(array $payload, Request $request): Lead
    {
        return $this->storeLead(
            request: $request,
            leadType: $payload['lead_type'],
            name: $payload['name'] ?? null,
            email: $payload['email'] ?? null,
            phone: $payload['phone'] ?? null,
            company: $payload['company'] ?? null,
            location: $payload['location'] ?? null,
            zip: $payload['zip'] ?? null,
            sourceUrl: $payload['source_url'] ?? null,
            referrerUrl: $payload['referrer_url'] ?? null,
            data: [
                'source' => 'voice_agent',
                'intent' => $payload['intent'],
                'notes' => $payload['notes'] ?? null,
                'call_sid' => $payload['call_sid'] ?? null,
                'transcript_excerpt' => $payload['transcript_excerpt'] ?? null,
                'metadata' => $payload['metadata'] ?? [],
            ],
        );
    }

    public function createCallbackRequest(array $payload, Request $request): Lead
    {
        return $this->storeLead(
            request: $request,
            leadType: $payload['lead_type'] ?? 'general',
            name: $payload['name'] ?? null,
            phone: $payload['phone'],
            data: [
                'source' => 'voice_agent',
                'intent' => 'callback_request',
                'callback_time' => $payload['callback_time'] ?? null,
                'reason' => $payload['reason'] ?? null,
                'call_sid' => $payload['call_sid'] ?? null,
                'transcript_excerpt' => $payload['transcript_excerpt'] ?? null,
                'metadata' => $payload['metadata'] ?? [],
            ],
        );
    }

    public function createSignupRequest(array $payload, Request $request, string $signupLink): array
    {
        $lead = $this->storeLead(
            request: $request,
            leadType: $payload['lead_type'],
            name: $payload['name'] ?? null,
            phone: $payload['phone'] ?? null,
            data: [
                'source' => 'voice_agent',
                'intent' => 'signup_link',
                'consent_received' => (bool) $payload['consent_received'],
                'call_sid' => $payload['call_sid'] ?? null,
                'transcript_excerpt' => $payload['transcript_excerpt'] ?? null,
                'signup_link' => $signupLink,
                'metadata' => $payload['metadata'] ?? [],
            ],
        );

        return [
            'lead_id' => $lead->id,
            'status' => $lead->status,
            'signup_link' => $signupLink,
            'sms_message' => trim(sprintf(
                'Thanks for calling %s. Here is your sign-up link: %s',
                (string) config('voice_agent.brand_name', config('app.name')),
                $signupLink
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordProviderOutreachResult(array $payload, Request $request): Lead
    {
        $metadata = (array) ($payload['metadata'] ?? []);
        $providerMetadata = (array) data_get($metadata, 'provider_outreach', []);

        $target = [
            'name' => $this->firstFilled($payload['target_name'] ?? null, data_get($metadata, 'target_name')),
            'organization' => $this->firstFilled($payload['target_organization'] ?? null, data_get($metadata, 'target_organization')),
            'role' => $this->firstFilled($payload['target_role'] ?? null, data_get($metadata, 'target_role')),
            'phone' => $this->firstFilled($payload['target_phone'] ?? null, $payload['phone'] ?? null, data_get($metadata, 'target_phone')),
            'email' => $this->firstFilled($payload['target_email'] ?? null, data_get($metadata, 'target_email')),
            'fax' => $this->firstFilled($payload['target_fax'] ?? null, data_get($metadata, 'target_fax')),
            'location' => $this->firstFilled($payload['target_location'] ?? null, data_get($metadata, 'target_location')),
        ];

        $result = [
            'outcome' => $this->firstFilled($payload['outcome'] ?? null, data_get($providerMetadata, 'outcome'), 'completed'),
            'summary' => $this->firstFilled($payload['summary'] ?? null, data_get($providerMetadata, 'summary')),
            'notes' => $this->firstFilled($payload['notes'] ?? null, data_get($providerMetadata, 'notes')),
            'contact_name' => $this->firstFilled($payload['contact_name'] ?? null, data_get($providerMetadata, 'contact_name')),
            'contact_role' => $this->firstFilled($payload['contact_role'] ?? null, data_get($providerMetadata, 'contact_role')),
            'email' => $this->firstFilled($payload['email'] ?? null, data_get($providerMetadata, 'email')),
            'fax' => $this->firstFilled($payload['fax'] ?? null, data_get($providerMetadata, 'fax')),
            'resource_requested' => $this->boolish($payload['resource_requested'] ?? data_get($providerMetadata, 'resource_requested')),
            'follow_up_needed' => $this->boolish($payload['follow_up_needed'] ?? data_get($providerMetadata, 'follow_up_needed')),
            'best_follow_up' => $this->firstFilled($payload['best_follow_up'] ?? null, data_get($providerMetadata, 'best_follow_up')),
            'do_not_call' => $this->boolish($payload['do_not_call'] ?? data_get($providerMetadata, 'do_not_call')),
            'voicemail_detected' => $this->boolish($payload['voicemail_detected'] ?? data_get($providerMetadata, 'voicemail_detected')),
            'ivr_detected' => $this->boolish($payload['ivr_detected'] ?? data_get($providerMetadata, 'ivr_detected')),
            'ai_detected' => $this->boolish($payload['ai_detected'] ?? data_get($providerMetadata, 'ai_detected')),
            'objection' => $this->firstFilled($payload['objection'] ?? null, data_get($providerMetadata, 'objection')),
            'reported_at' => now()->toISOString(),
        ];

        $lead = $this->providerOutreachLead($payload, $metadata, $target);

        $existingData = is_array($lead->data) ? $lead->data : [];
        $providerOutreach = array_replace_recursive(
            (array) data_get($existingData, 'provider_outreach', []),
            [
                'target' => $target,
                'last_summary' => $result['summary'] ?: $result['notes'],
                'last_outcome' => $result['outcome'],
                'resource_requested' => $result['resource_requested'],
                'follow_up_needed' => $result['follow_up_needed'],
                'do_not_call' => $result['do_not_call'],
                'last_result' => $result,
            ],
        );

        $status = $this->providerOutreachStatus($lead, $result);
        $updates = [
            'lead_type' => Lead::TYPE_REFERRAL,
            'name' => $result['contact_name'] ?: ($target['name'] ?: $lead->name),
            'phone' => $target['phone'] ?: $lead->phone,
            'email' => $result['email'] ?: ($target['email'] ?: $lead->email),
            'company' => $target['organization'] ?: $lead->company,
            'contact_role' => $result['contact_role'] ?: ($target['role'] ?: $lead->contact_role),
            'location' => $target['location'] ?: $lead->location,
            'status' => $status,
            'source' => $lead->source ?: 'ai_provider_outreach',
            'source_detail' => $lead->source_detail ?: 'Julie AI provider outreach',
            'source_url' => $lead->source_url ?: 'voice-agent://provider-outreach',
            'last_contacted_at' => $this->reportTime($payload),
            'data' => array_replace_recursive($existingData, [
                'source' => 'voice_agent',
                'intent' => 'provider_outreach',
                'provider_outreach' => $providerOutreach,
            ]),
        ];

        if ($result['do_not_call']) {
            $updates['closed_reason'] = 'Do-not-call requested during Julie AI provider outreach.';
        }

        $lead->forceFill($updates)->save();

        $this->recordProviderOutreachActivity($lead, $payload, $target, $result);

        return $lead->fresh();
    }

    private function storeLead(
        Request $request,
        string $leadType,
        ?string $name = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $company = null,
        ?string $location = null,
        ?string $zip = null,
        ?string $sourceUrl = null,
        ?string $referrerUrl = null,
        array $data = [],
    ): Lead {
        return Lead::query()->create([
            'lead_type' => $leadType,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'company' => $company,
            'location' => $location,
            'zip' => $zip,
            'data' => $data,
            'status' => 'new',
            'source' => 'voice_agent',
            'source_url' => $sourceUrl ?: 'voice-agent://phone-call',
            'referrer_url' => $referrerUrl,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     * @param  array<string, string>  $target
     */
    private function providerOutreachLead(array $payload, array $metadata, array $target): Lead
    {
        $leadId = $this->firstFilled($payload['referral_lead_id'] ?? null, data_get($metadata, 'referral_lead_id'));
        if ($leadId !== '' && ctype_digit((string) $leadId)) {
            $lead = Lead::query()->where('lead_type', Lead::TYPE_REFERRAL)->find((int) $leadId);
            if ($lead) {
                return $lead;
            }
        }

        if ($target['phone'] !== '') {
            $lead = Lead::query()
                ->where('lead_type', Lead::TYPE_REFERRAL)
                ->where('phone', $target['phone'])
                ->first();

            if ($lead) {
                return $lead;
            }
        }

        return Lead::query()->create([
            'lead_type' => Lead::TYPE_REFERRAL,
            'name' => $target['name'] ?: $target['organization'],
            'phone' => $target['phone'],
            'email' => $target['email'],
            'company' => $target['organization'],
            'contact_role' => $target['role'],
            'location' => $target['location'],
            'status' => 'outreach',
            'priority' => Lead::PRIORITY_NORMAL,
            'source' => 'ai_provider_outreach',
            'source_detail' => 'Julie AI provider outreach',
            'source_url' => 'voice-agent://provider-outreach',
            'data' => [
                'source' => 'voice_agent',
                'intent' => 'provider_outreach',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function providerOutreachStatus(Lead $lead, array $result): string
    {
        if ($result['do_not_call']) {
            return 'closed';
        }

        if ($result['resource_requested'] || $result['follow_up_needed']) {
            return 'nurturing';
        }

        if (in_array($result['outcome'], ['interested', 'resource_requested', 'follow_up_needed'], true)) {
            return 'nurturing';
        }

        if (in_array($result['outcome'], ['not_fit', 'not_interested'], true)) {
            return 'not_fit';
        }

        return $lead->status ?: 'outreach';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $target
     * @param  array<string, mixed>  $result
     */
    private function recordProviderOutreachActivity(Lead $lead, array $payload, array $target, array $result): void
    {
        $callSid = $this->firstFilled($payload['call_sid'] ?? null, data_get($payload, 'metadata.call_sid'));
        $voiceAiCallId = $this->firstFilled($payload['voice_ai_call_id'] ?? null, data_get($payload, 'metadata.voice_ai_call_id'));

        if ($callSid !== '' && $lead->activities()
            ->where('type', LeadActivity::TYPE_CALL)
            ->where('metadata->call_sid', $callSid)
            ->exists()) {
            return;
        }

        $summary = match (true) {
            $result['do_not_call'] => 'Julie AI provider outreach: do not call',
            $result['resource_requested'] => 'Julie AI provider outreach: resource requested',
            $result['voicemail_detected'] => 'Julie AI provider outreach: voicemail',
            $result['ivr_detected'] => 'Julie AI provider outreach: IVR',
            $result['ai_detected'] => 'Julie AI provider outreach: AI system',
            default => 'Julie AI provider outreach completed',
        };

        $body = collect([
            'Target: '.($target['organization'] ?: $target['name'] ?: 'Unknown provider source'),
            'Outcome: '.$result['outcome'],
            $result['summary'] ? 'Summary: '.$result['summary'] : null,
            $result['notes'] ? 'Notes: '.$result['notes'] : null,
            $result['objection'] ? 'Objection: '.$result['objection'] : null,
            $result['resource_requested'] ? 'Resource requested. Send the one-page LoLo resource to the captured email or fax.' : null,
            $result['best_follow_up'] ? 'Follow-up: '.$result['best_follow_up'] : null,
            $result['do_not_call'] ? 'Do not call again.' : null,
            $payload['transcript_excerpt'] ?? $payload['transcript'] ?? null,
        ])->filter()->implode("\n\n");

        $lead->activities()->create([
            'type' => LeadActivity::TYPE_CALL,
            'summary' => $summary,
            'body' => $body,
            'occurred_at' => $this->reportTime($payload),
            'metadata' => [
                'call_sid' => $callSid,
                'voice_ai_call_id' => $voiceAiCallId,
                'voice_agent_profile' => VoiceAiCall::PROFILE_PROVIDER_OUTREACH,
                'provider_outreach' => $result,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function reportTime(array $payload): Carbon
    {
        try {
            return filled($payload['ended_at'] ?? null) ? Carbon::parse((string) $payload['ended_at']) : now();
        } catch (\Throwable) {
            return now();
        }
    }

    private function boolish(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y'], true);
        }

        return (bool) $value;
    }

    private function firstFilled(mixed ...$values): string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }
}
