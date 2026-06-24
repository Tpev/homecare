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
        $to = TwilioSmsClient::normalizePhone((string) ($target['phone'] ?? ''));
        $from = TwilioSmsClient::normalizePhone((string) config('services.twilio.voice_from'));

        if ($to === '') {
            throw new RuntimeException('Enter a valid provider phone number to call.');
        }

        if ($from === '') {
            throw new RuntimeException('Configure TWILIO_VOICE_FROM or TWILIO_PHONE_NUMBER before starting voice calls.');
        }

        $lead = $this->upsertProviderOutreachLead($target, $to, $admin);

        $metadata = [
            'voice_agent_profile' => VoiceAiCall::PROFILE_PROVIDER_OUTREACH,
            'assistant_name' => 'Julie',
            'referral_lead_id' => $lead->id,
            'target_name' => (string) ($target['contact_name'] ?? ''),
            'target_organization' => (string) ($target['practice_name'] ?? ''),
            'target_role' => (string) ($target['contact_role'] ?? ''),
            'target_email' => (string) ($target['email'] ?? ''),
            'target_fax' => (string) ($target['fax'] ?? ''),
            'target_location' => (string) ($target['location'] ?? ''),
            'compliance_guardrails' => [
                'no_referral_fees',
                'no_patient_information',
                'honor_do_not_call',
                'no_medical_or_clinical_claims',
            ],
        ];

        $call = VoiceAiCall::query()->create([
            'admin_user_id' => $admin?->id,
            'direction' => VoiceAiCall::DIRECTION_OUTBOUND,
            'status' => VoiceAiCall::STATUS_QUEUED,
            'to_phone' => $to,
            'from_phone' => $from,
            'current_step' => 'provider_outreach',
            'gathered_name' => (string) ($target['contact_name'] ?? ''),
            'gathered_relationship' => (string) ($target['contact_role'] ?? ''),
            'gathered_phone' => $to,
            'gathered_location' => (string) ($target['location'] ?? ''),
            'gathered_care_needs' => (string) ($target['notes'] ?? ''),
            'started_at' => now(),
            'metadata' => $metadata,
        ]);

        $lead->activities()->create([
            'actor_user_id' => $admin?->id,
            'type' => LeadActivity::TYPE_CALL,
            'summary' => 'Julie AI provider outreach queued',
            'body' => 'Queued an AI outreach call to ask whether the office needs a simple non-medical home-support resource for families.',
            'occurred_at' => now(),
            'metadata' => [
                'voice_ai_call_id' => $call->id,
                'voice_agent_profile' => VoiceAiCall::PROFILE_PROVIDER_OUTREACH,
                'to_phone' => $to,
            ],
        ]);

        return $this->dispatchCallToTwilio($call, [
            'prompt_profile' => VoiceAiCall::PROFILE_PROVIDER_OUTREACH,
            'referral_lead_id' => (string) $lead->id,
            'target_name' => (string) ($target['contact_name'] ?? ''),
            'target_organization' => (string) ($target['practice_name'] ?? ''),
            'target_role' => (string) ($target['contact_role'] ?? ''),
            'target_email' => (string) ($target['email'] ?? ''),
            'target_fax' => (string) ($target['fax'] ?? ''),
            'target_location' => (string) ($target['location'] ?? ''),
        ]);
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
