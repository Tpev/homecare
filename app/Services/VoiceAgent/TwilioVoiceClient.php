<?php

namespace App\Services\VoiceAgent;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Models\VoiceAiCall;
use App\Services\Messaging\TwilioSmsClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TwilioVoiceClient
{
    public function startTestCall(string $toPhone, ?User $admin = null): VoiceAiCall
    {
        $to = TwilioSmsClient::normalizePhone($toPhone);
        $from = TwilioSmsClient::normalizePhone((string) config('services.twilio.voice_from'));

        if ($to === '') {
            throw new RuntimeException('Enter a valid phone number to call.');
        }

        if ($from === '') {
            throw new RuntimeException('Configure TWILIO_VOICE_FROM or TWILIO_PHONE_NUMBER before starting voice calls.');
        }

        $call = VoiceAiCall::query()->create([
            'admin_user_id' => $admin?->id,
            'direction' => VoiceAiCall::DIRECTION_OUTBOUND,
            'status' => VoiceAiCall::STATUS_QUEUED,
            'to_phone' => $to,
            'from_phone' => $from,
            'current_step' => 'intro',
            'gathered_phone' => $to,
            'started_at' => now(),
            'metadata' => [
                'voice_agent_profile' => VoiceAiCall::PROFILE_CALLBACK_DISCOVERY,
            ],
        ]);

        return $this->dispatchCallToTwilio($call);
    }

    /**
     * @param  array<string, string|null>  $target
     */
    public function startProviderOutreachCall(array $target, ?User $admin = null): VoiceAiCall
    {
        $target = $this->providerTarget($target);
        $to = TwilioSmsClient::normalizePhone($target['phone']);
        $from = $this->voiceFrom();

        if ($to === '') {
            throw new RuntimeException('Enter a valid provider phone number to call.');
        }

        if ($from === '') {
            throw new RuntimeException('Configure TWILIO_VOICE_FROM or TWILIO_PHONE_NUMBER before starting voice calls.');
        }

        $lead = $this->upsertProviderOutreachLead($target, $to, $admin);
        $call = $this->createProviderOutreachCall($target, $lead, $admin, VoiceAiCall::STATUS_QUEUED, 'provider_outreach');

        $this->recordProviderOutreachActivity(
            $lead,
            $call,
            $admin,
            'Julie AI provider outreach queued',
            'Queued an AI outreach call to ask whether the office needs a simple non-medical home-support resource for families.',
        );

        return $this->dispatchProviderOutreachCall($call, $lead, $target);
    }

    /**
     * @param  array<string, string|null>  $target
     */
    public function queueProviderOutreachDraft(array $target, ?User $admin = null, ?string $batchId = null, int $position = 0, ?string $batchLabel = null): VoiceAiCall
    {
        $target = $this->providerTarget($target);
        $to = TwilioSmsClient::normalizePhone($target['phone']);
        $from = $this->voiceFrom();

        if ($to === '') {
            throw new RuntimeException('Enter a valid provider phone number to call.');
        }

        if ($from === '') {
            throw new RuntimeException('Configure TWILIO_VOICE_FROM or TWILIO_PHONE_NUMBER before starting voice calls.');
        }

        $lead = $this->upsertProviderOutreachLead($target, $to, $admin);
        $metadata = [
            'provider_outreach_batch_id' => $batchId,
            'provider_outreach_batch_label' => $batchLabel,
            'provider_outreach_batch_position' => $position,
            'provider_outreach_batch_status' => 'waiting',
            'provider_outreach_batch_queued_at' => now()->toISOString(),
        ];

        $call = $this->createProviderOutreachCall(
            $target,
            $lead,
            $admin,
            VoiceAiCall::STATUS_DRAFT,
            'provider_outreach_batch_waiting',
            $metadata,
        );

        $this->recordProviderOutreachActivity(
            $lead,
            $call,
            $admin,
            'Julie AI provider outreach added to CSV queue',
            'Added this source to a CSV outreach queue. The call has not started yet.',
        );

        return $call;
    }

    public function startQueuedProviderOutreachCall(VoiceAiCall $call, ?User $admin = null): VoiceAiCall
    {
        if ($call->status !== VoiceAiCall::STATUS_DRAFT) {
            throw new RuntimeException('This provider outreach item has already been started.');
        }

        if (data_get($call->metadata, 'voice_agent_profile') !== VoiceAiCall::PROFILE_PROVIDER_OUTREACH) {
            throw new RuntimeException('This queued call is not a provider outreach call.');
        }

        $target = $this->providerTargetFromCall($call);
        $to = TwilioSmsClient::normalizePhone($target['phone']);
        $from = $this->voiceFrom();

        if ($to === '') {
            throw new RuntimeException('Enter a valid provider phone number to call.');
        }

        if ($from === '') {
            throw new RuntimeException('Configure TWILIO_VOICE_FROM or TWILIO_PHONE_NUMBER before starting voice calls.');
        }

        $lead = $this->upsertProviderOutreachLead($target, $to, $admin);
        $metadata = array_replace_recursive(
            $this->providerOutreachMetadata($target, $lead),
            is_array($call->metadata) ? $call->metadata : [],
            [
                'referral_lead_id' => $lead->id,
                'provider_outreach_batch_status' => 'calling',
                'provider_outreach_batch_started_at' => now()->toISOString(),
            ],
        );

        $call->forceFill([
            'admin_user_id' => $call->admin_user_id ?: $admin?->id,
            'direction' => VoiceAiCall::DIRECTION_OUTBOUND,
            'status' => VoiceAiCall::STATUS_QUEUED,
            'to_phone' => $to,
            'from_phone' => $from,
            'current_step' => 'provider_outreach',
            'gathered_name' => $target['contact_name'],
            'gathered_relationship' => $target['contact_role'],
            'gathered_phone' => $to,
            'gathered_location' => $target['location'],
            'gathered_care_needs' => $target['notes'],
            'started_at' => now(),
            'metadata' => $metadata,
        ])->save();

        $this->recordProviderOutreachActivity(
            $lead,
            $call,
            $admin,
            'Julie AI provider outreach started from CSV queue',
            'Started the next queued provider outreach call from the admin interface.',
        );

        return $this->dispatchProviderOutreachCall($call->fresh(), $lead, $target);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function dispatchCallToTwilio(VoiceAiCall $call, array $query = []): VoiceAiCall
    {
        if ((bool) config('services.twilio.bypass', false)) {
            $call->update([
                'twilio_call_sid' => 'CA_bypass_'.(string) Str::ulid(),
                'twilio_account_sid' => (string) config('services.twilio.account_sid') ?: 'AC_bypass',
                'twilio_status' => 'queued',
                'raw_payload' => [
                    'bypass' => true,
                    'voice_agent_callback_url' => $this->voiceAgentCallbackUrl($call, $query),
                ],
            ]);

            return $call->fresh();
        }

        $accountSid = (string) config('services.twilio.account_sid');
        $authToken = (string) config('services.twilio.auth_token');

        if ($accountSid === '' || $authToken === '') {
            $call->update([
                'status' => VoiceAiCall::STATUS_FAILED,
                'last_error' => 'Twilio Voice credentials are not configured.',
            ]);

            throw new RuntimeException('Twilio Voice credentials are not configured.');
        }

        $voiceAgentCallbackUrl = $this->voiceAgentCallbackUrl($call, $query);
        if ($voiceAgentCallbackUrl === '') {
            $call->update([
                'status' => VoiceAiCall::STATUS_FAILED,
                'last_error' => 'Twilio Voice Agent callback URL is not configured.',
            ]);

            throw new RuntimeException('Configure TWILIO_VOICE_AGENT_CALLBACK_URL to the Go Deepgram voice agent callback endpoint before starting discovery calls.');
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($accountSid, $authToken)
                ->timeout((int) config('services.twilio.timeout', 15))
                ->post($this->callsEndpoint($accountSid), [
                    'To' => $call->to_phone,
                    'From' => $call->from_phone,
                    'Url' => $voiceAgentCallbackUrl,
                    'Method' => 'POST',
                    'StatusCallback' => $this->publicRoute('webhooks.twilio.voice.status', $call),
                    'StatusCallbackMethod' => 'POST',
                ]);
        } catch (Throwable $e) {
            $call->update([
                'status' => VoiceAiCall::STATUS_FAILED,
                'last_error' => 'Twilio Voice request failed: '.$e->getMessage(),
            ]);

            throw new RuntimeException('Twilio Voice request failed: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            $message = $this->responseErrorMessage($response);
            $call->update([
                'status' => VoiceAiCall::STATUS_FAILED,
                'last_error' => $message,
                'raw_payload' => $response->json(),
            ]);

            throw new RuntimeException($message);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            $call->update([
                'status' => VoiceAiCall::STATUS_FAILED,
                'last_error' => 'Twilio returned an invalid Voice response.',
            ]);

            throw new RuntimeException('Twilio returned an invalid Voice response.');
        }

        $call->update([
            'twilio_call_sid' => filled($payload['sid'] ?? null) ? (string) $payload['sid'] : null,
            'twilio_account_sid' => filled($payload['account_sid'] ?? null) ? (string) $payload['account_sid'] : $accountSid,
            'twilio_status' => filled($payload['status'] ?? null) ? (string) $payload['status'] : 'queued',
            'status' => $this->normalizeStatus((string) ($payload['status'] ?? VoiceAiCall::STATUS_QUEUED)),
            'raw_payload' => $payload,
        ]);

        return $call->fresh();
    }

    public function publicRoute(string $routeName, VoiceAiCall $call): string
    {
        $baseUrl = trim((string) config('services.twilio.webhook_base_url'));
        if ($baseUrl !== '') {
            return rtrim($baseUrl, '/').route($routeName, $call, false);
        }

        return route($routeName, $call);
    }

    public function normalizeStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'queued' => VoiceAiCall::STATUS_QUEUED,
            'initiated', 'ringing' => VoiceAiCall::STATUS_RINGING,
            'in-progress', 'in_progress', 'answered' => VoiceAiCall::STATUS_IN_PROGRESS,
            'completed' => VoiceAiCall::STATUS_COMPLETED,
            'busy' => VoiceAiCall::STATUS_BUSY,
            'no-answer', 'no_answer' => VoiceAiCall::STATUS_NO_ANSWER,
            'canceled', 'cancelled' => VoiceAiCall::STATUS_CANCELLED,
            'failed' => VoiceAiCall::STATUS_FAILED,
            default => $status !== '' ? strtolower($status) : VoiceAiCall::STATUS_QUEUED,
        };
    }

    private function callsEndpoint(string $accountSid): string
    {
        return 'https://api.twilio.com/2010-04-01/Accounts/'.$accountSid.'/Calls.json';
    }

    private function voiceFrom(): string
    {
        return TwilioSmsClient::normalizePhone((string) config('services.twilio.voice_from'));
    }

    /**
     * @param  array<string, string|null>  $target
     * @return array{practice_name: string, contact_name: string, contact_role: string, phone: string, email: string, fax: string, location: string, notes: string}
     */
    private function providerTarget(array $target): array
    {
        return [
            'practice_name' => trim((string) ($target['practice_name'] ?? '')),
            'contact_name' => trim((string) ($target['contact_name'] ?? '')),
            'contact_role' => trim((string) ($target['contact_role'] ?? '')),
            'phone' => trim((string) ($target['phone'] ?? '')),
            'email' => trim((string) ($target['email'] ?? '')),
            'fax' => trim((string) ($target['fax'] ?? '')),
            'location' => trim((string) ($target['location'] ?? '')),
            'notes' => trim((string) ($target['notes'] ?? '')),
        ];
    }

    /**
     * @return array{practice_name: string, contact_name: string, contact_role: string, phone: string, email: string, fax: string, location: string, notes: string}
     */
    private function providerTargetFromCall(VoiceAiCall $call): array
    {
        return $this->providerTarget([
            'practice_name' => data_get($call->metadata, 'target_organization', ''),
            'contact_name' => data_get($call->metadata, 'target_name', $call->gathered_name),
            'contact_role' => data_get($call->metadata, 'target_role', $call->gathered_relationship),
            'phone' => $call->to_phone ?: data_get($call->metadata, 'target_phone', $call->gathered_phone),
            'email' => data_get($call->metadata, 'target_email', ''),
            'fax' => data_get($call->metadata, 'target_fax', ''),
            'location' => data_get($call->metadata, 'target_location', $call->gathered_location),
            'notes' => $call->gathered_care_needs,
        ]);
    }

    /**
     * @param  array<string, string>  $target
     * @param  array<string, mixed>  $extraMetadata
     */
    private function createProviderOutreachCall(
        array $target,
        Lead $lead,
        ?User $admin,
        string $status,
        string $currentStep,
        array $extraMetadata = [],
    ): VoiceAiCall {
        $to = TwilioSmsClient::normalizePhone($target['phone']);
        $from = $this->voiceFrom();

        return VoiceAiCall::query()->create([
            'admin_user_id' => $admin?->id,
            'direction' => VoiceAiCall::DIRECTION_OUTBOUND,
            'status' => $status,
            'to_phone' => $to,
            'from_phone' => $from,
            'current_step' => $currentStep,
            'gathered_name' => $target['contact_name'],
            'gathered_relationship' => $target['contact_role'],
            'gathered_phone' => $to,
            'gathered_location' => $target['location'],
            'gathered_care_needs' => $target['notes'],
            'started_at' => $status === VoiceAiCall::STATUS_DRAFT ? null : now(),
            'metadata' => array_replace_recursive($this->providerOutreachMetadata($target, $lead), $extraMetadata),
        ]);
    }

    /**
     * @param  array<string, string>  $target
     * @return array<string, mixed>
     */
    private function providerOutreachMetadata(array $target, Lead $lead): array
    {
        return [
            'voice_agent_profile' => VoiceAiCall::PROFILE_PROVIDER_OUTREACH,
            'assistant_name' => 'Julie',
            'referral_lead_id' => $lead->id,
            'target_name' => $target['contact_name'],
            'target_organization' => $target['practice_name'],
            'target_role' => $target['contact_role'],
            'target_phone' => TwilioSmsClient::normalizePhone($target['phone']),
            'target_email' => $target['email'],
            'target_fax' => $target['fax'],
            'target_location' => $target['location'],
            'compliance_guardrails' => [
                'no_referral_fees',
                'no_patient_information',
                'honor_do_not_call',
                'no_medical_or_clinical_claims',
            ],
        ];
    }

    /**
     * @param  array<string, string>  $target
     */
    private function dispatchProviderOutreachCall(VoiceAiCall $call, Lead $lead, array $target): VoiceAiCall
    {
        return $this->dispatchCallToTwilio($call, [
            'prompt_profile' => VoiceAiCall::PROFILE_PROVIDER_OUTREACH,
            'referral_lead_id' => (string) $lead->id,
            'target_name' => $target['contact_name'],
            'target_organization' => $target['practice_name'],
            'target_role' => $target['contact_role'],
            'target_email' => $target['email'],
            'target_fax' => $target['fax'],
            'target_location' => $target['location'],
        ]);
    }

    private function recordProviderOutreachActivity(Lead $lead, VoiceAiCall $call, ?User $admin, string $summary, string $body): void
    {
        $lead->activities()->create([
            'actor_user_id' => $admin?->id,
            'type' => LeadActivity::TYPE_CALL,
            'summary' => $summary,
            'body' => $body,
            'occurred_at' => now(),
            'metadata' => [
                'voice_ai_call_id' => $call->id,
                'voice_agent_profile' => VoiceAiCall::PROFILE_PROVIDER_OUTREACH,
                'to_phone' => $call->to_phone,
                'provider_outreach_batch_id' => data_get($call->metadata, 'provider_outreach_batch_id'),
            ],
        ]);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function voiceAgentCallbackUrl(VoiceAiCall $call, array $query = []): string
    {
        $url = trim((string) config('services.twilio.voice_agent_callback_url'));
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            $separator = str_contains($url, '?') ? '&' : '?';

            return $url.$separator.http_build_query(array_merge([
                'voice_ai_call_id' => $call->id,
            ], $query));
        }

        $existingQuery = [];
        if (isset($parts['query'])) {
            parse_str((string) $parts['query'], $existingQuery);
        }

        $mergedQuery = array_merge($existingQuery, [
            'voice_ai_call_id' => $call->id,
        ], $query);

        $rebuilt = $parts['scheme'].'://'.$parts['host'];
        if (isset($parts['port'])) {
            $rebuilt .= ':'.$parts['port'];
        }
        $rebuilt .= $parts['path'] ?? '';
        if ($mergedQuery !== []) {
            $rebuilt .= '?'.http_build_query($mergedQuery);
        }
        if (isset($parts['fragment'])) {
            $rebuilt .= '#'.$parts['fragment'];
        }

        return $rebuilt;
    }

    /**
     * @param  array<string, string|null>  $target
     */
    private function upsertProviderOutreachLead(array $target, string $phone, ?User $admin): Lead
    {
        $practiceName = trim((string) ($target['practice_name'] ?? ''));
        $contactName = trim((string) ($target['contact_name'] ?? ''));

        $lead = Lead::query()
            ->where('lead_type', Lead::TYPE_REFERRAL)
            ->where('phone', $phone)
            ->first();

        if ($lead && (bool) data_get($lead->data, 'provider_outreach.do_not_call')) {
            throw new RuntimeException('This referral source is marked do-not-call.');
        }

        $lead ??= new Lead([
            'lead_type' => Lead::TYPE_REFERRAL,
            'phone' => $phone,
            'status' => 'outreach',
            'priority' => Lead::PRIORITY_NORMAL,
            'source' => 'ai_provider_outreach',
            'source_detail' => 'Julie AI provider outreach',
            'source_url' => 'voice-agent://provider-outreach',
        ]);

        $data = is_array($lead->data) ? $lead->data : [];
        $data['provider_outreach'] = array_replace_recursive((array) data_get($data, 'provider_outreach', []), [
            'target' => [
                'practice_name' => $practiceName,
                'contact_name' => $contactName,
                'contact_role' => (string) ($target['contact_role'] ?? ''),
                'email' => (string) ($target['email'] ?? ''),
                'fax' => (string) ($target['fax'] ?? ''),
                'location' => (string) ($target['location'] ?? ''),
                'phone' => $phone,
            ],
            'last_queued_at' => now()->toISOString(),
        ]);

        $lead->forceFill([
            'name' => $contactName !== '' ? $contactName : ($lead->name ?: $practiceName),
            'company' => $practiceName !== '' ? $practiceName : $lead->company,
            'contact_role' => (string) ($target['contact_role'] ?? $lead->contact_role),
            'email' => (string) ($target['email'] ?? $lead->email),
            'location' => (string) ($target['location'] ?? $lead->location),
            'assigned_admin_id' => $lead->assigned_admin_id ?: $admin?->id,
            'source' => $lead->source ?: 'ai_provider_outreach',
            'source_detail' => $lead->source_detail ?: 'Julie AI provider outreach',
            'status' => $lead->status ?: 'outreach',
            'data' => $data,
        ])->save();

        return $lead;
    }

    private function responseErrorMessage(Response $response): string
    {
        $payload = $response->json();
        $message = is_array($payload)
            ? (string) ($payload['message'] ?? $payload['detail'] ?? '')
            : '';

        return trim($message) !== ''
            ? 'Twilio Voice returned '.$response->status().': '.$message
            : 'Twilio Voice returned HTTP '.$response->status().'.';
    }
}
