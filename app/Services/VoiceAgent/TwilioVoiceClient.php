<?php

namespace App\Services\VoiceAgent;

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
                'voice_agent_profile' => 'callback_discovery',
            ],
        ]);

        if ((bool) config('services.twilio.bypass', false)) {
            $call->update([
                'twilio_call_sid' => 'CA_bypass_'.(string) Str::ulid(),
                'twilio_account_sid' => (string) config('services.twilio.account_sid') ?: 'AC_bypass',
                'twilio_status' => 'queued',
                'raw_payload' => ['bypass' => true],
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

        $voiceAgentCallbackUrl = $this->voiceAgentCallbackUrl($call);
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
                    'To' => $to,
                    'From' => $from,
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

    private function voiceAgentCallbackUrl(VoiceAiCall $call): string
    {
        $url = trim((string) config('services.twilio.voice_agent_callback_url'));
        if ($url === '') {
            return '';
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query([
            'voice_ai_call_id' => $call->id,
        ]);
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
