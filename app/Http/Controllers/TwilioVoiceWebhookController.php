<?php

namespace App\Http\Controllers;

use App\Models\VoiceAiCall;
use App\Services\Messaging\TwilioSmsClient;
use App\Services\VoiceAgent\TwilioVoiceClient;
use App\Services\VoiceAgent\VoiceAiCallResponder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TwilioVoiceWebhookController extends Controller
{
    public function answer(
        Request $request,
        VoiceAiCall $voiceAiCall,
        TwilioSmsClient $twilio,
        TwilioVoiceClient $voiceClient,
        VoiceAiCallResponder $responder,
    ): Response {
        if (! $twilio->hasValidWebhookSignature($request)) {
            Log::warning('Twilio Voice answer webhook signature validation failed', [
                'voice_ai_call_id' => $voiceAiCall->id,
                'call_sid' => $request->input('CallSid'),
            ]);

            return response('Invalid Twilio signature', 401);
        }

        $this->syncCallFromTwilioPayload($voiceAiCall, $request, $voiceClient);

        $message = $responder->initialPrompt();
        $voiceAiCall->appendTranscript('assistant', $message);

        return $this->twiml($this->gatherTwiml(
            message: $message,
            actionUrl: $voiceClient->publicRoute('webhooks.twilio.voice.gather', $voiceAiCall),
            redirectUrl: $voiceClient->publicRoute('webhooks.twilio.voice.answer', $voiceAiCall),
        ));
    }

    public function gather(
        Request $request,
        VoiceAiCall $voiceAiCall,
        TwilioSmsClient $twilio,
        TwilioVoiceClient $voiceClient,
        VoiceAiCallResponder $responder,
    ): Response {
        if (! $twilio->hasValidWebhookSignature($request)) {
            Log::warning('Twilio Voice gather webhook signature validation failed', [
                'voice_ai_call_id' => $voiceAiCall->id,
                'call_sid' => $request->input('CallSid'),
            ]);

            return response('Invalid Twilio signature', 401);
        }

        $this->syncCallFromTwilioPayload($voiceAiCall, $request, $voiceClient);

        $speech = trim((string) $request->input('SpeechResult', ''));
        if ($speech !== '') {
            $voiceAiCall->appendTranscript('caller', $speech);
        }

        $result = $responder->respond($voiceAiCall->fresh(), $speech);
        $voiceAiCall->refresh();
        $voiceAiCall->appendTranscript('assistant', $result['message']);

        if ($result['hangup']) {
            $voiceAiCall->update([
                'status' => VoiceAiCall::STATUS_COMPLETED,
                'ended_at' => now(),
            ]);

            return $this->twiml(
                '<Say voice="alice">'.$this->xml($result['message']).'</Say><Hangup/>',
            );
        }

        return $this->twiml($this->gatherTwiml(
            message: $result['message'],
            actionUrl: $voiceClient->publicRoute('webhooks.twilio.voice.gather', $voiceAiCall),
            redirectUrl: $voiceClient->publicRoute('webhooks.twilio.voice.gather', $voiceAiCall),
        ));
    }

    public function status(
        Request $request,
        VoiceAiCall $voiceAiCall,
        TwilioSmsClient $twilio,
        TwilioVoiceClient $voiceClient,
    ): Response {
        if (! $twilio->hasValidWebhookSignature($request)) {
            Log::warning('Twilio Voice status webhook signature validation failed', [
                'voice_ai_call_id' => $voiceAiCall->id,
                'call_sid' => $request->input('CallSid'),
            ]);

            return response('Invalid Twilio signature', 401);
        }

        $this->syncCallFromTwilioPayload($voiceAiCall, $request, $voiceClient);

        return $this->twiml('');
    }

    private function syncCallFromTwilioPayload(VoiceAiCall $call, Request $request, TwilioVoiceClient $voiceClient): void
    {
        $payload = $request->request->all();
        $callStatus = (string) ($payload['CallStatus'] ?? $payload['CallStatusCallbackEvent'] ?? $payload['DialCallStatus'] ?? '');
        $normalizedStatus = $voiceClient->normalizeStatus($callStatus);
        $rawPayload = is_array($call->raw_payload) ? $call->raw_payload : [];
        $rawPayload['voice_callback_'.now()->format('YmdHisv')] = $payload;

        $updates = [
            'twilio_call_sid' => filled($payload['CallSid'] ?? null) ? (string) $payload['CallSid'] : $call->twilio_call_sid,
            'twilio_account_sid' => filled($payload['AccountSid'] ?? null) ? (string) $payload['AccountSid'] : $call->twilio_account_sid,
            'twilio_status' => $callStatus !== '' ? $callStatus : $call->twilio_status,
            'raw_payload' => $rawPayload,
        ];

        if ($callStatus !== '') {
            $updates['status'] = $normalizedStatus;
        }

        if (in_array($normalizedStatus, [VoiceAiCall::STATUS_IN_PROGRESS, VoiceAiCall::STATUS_COMPLETED], true) && ! $call->answered_at) {
            $updates['answered_at'] = now();
        }

        if (in_array($normalizedStatus, [
            VoiceAiCall::STATUS_COMPLETED,
            VoiceAiCall::STATUS_FAILED,
            VoiceAiCall::STATUS_BUSY,
            VoiceAiCall::STATUS_NO_ANSWER,
            VoiceAiCall::STATUS_CANCELLED,
        ], true)) {
            $updates['ended_at'] = now();
        }

        if (is_numeric($payload['CallDuration'] ?? null)) {
            $updates['duration_seconds'] = (int) $payload['CallDuration'];
        }

        $call->update($updates);
    }

    private function gatherTwiml(string $message, string $actionUrl, string $redirectUrl): string
    {
        return '<Gather input="speech" action="'.$this->xml($actionUrl).'" method="POST" timeout="6" speechTimeout="auto" language="en-US">'
            .'<Say voice="alice">'.$this->xml($message).'</Say>'
            .'</Gather>'
            .'<Say voice="alice">I did not hear anything. Let us try again.</Say>'
            .'<Redirect method="POST">'.$this->xml($redirectUrl).'</Redirect>';
    }

    private function twiml(string $innerXml): Response
    {
        return response('<?xml version="1.0" encoding="UTF-8"?><Response>'.$innerXml.'</Response>', 200)
            ->header('Content-Type', 'text/xml');
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
