<?php

namespace App\Http\Controllers;

use App\Models\SmsMessage;
use App\Services\Messaging\TwilioSmsClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TwilioSmsStatusWebhookController extends Controller
{
    public function __invoke(Request $request, TwilioSmsClient $twilio): Response
    {
        if (! $twilio->hasValidWebhookSignature($request)) {
            Log::warning('Twilio SMS status webhook signature validation failed', [
                'message_sid' => $request->input('MessageSid'),
            ]);

            return response('Invalid Twilio signature', 401);
        }

        $payload = $request->request->all();
        $messageSid = trim((string) ($payload['MessageSid'] ?? ''));

        if ($messageSid === '') {
            return response('<Response></Response>', 200)->header('Content-Type', 'text/xml');
        }

        $message = SmsMessage::query()
            ->where('twilio_sid', $messageSid)
            ->first();

        if (! $message) {
            Log::info('Twilio SMS status callback for unknown message', [
                'message_sid' => $messageSid,
                'status' => $payload['MessageStatus'] ?? $payload['SmsStatus'] ?? null,
            ]);

            return response('<Response></Response>', 200)->header('Content-Type', 'text/xml');
        }

        $status = (string) ($payload['MessageStatus'] ?? $payload['SmsStatus'] ?? $message->status);
        $rawPayload = is_array($message->raw_payload) ? $message->raw_payload : [];
        $rawPayload['status_callback'] = $payload;

        $message->update([
            'status' => $status !== '' ? $status : $message->status,
            'twilio_status' => $status !== '' ? $status : $message->twilio_status,
            'error_code' => filled($payload['ErrorCode'] ?? null) ? (string) $payload['ErrorCode'] : null,
            'error_message' => filled($payload['ErrorMessage'] ?? null) ? (string) $payload['ErrorMessage'] : null,
            'raw_payload' => $rawPayload,
        ]);

        return response('<Response></Response>', 200)->header('Content-Type', 'text/xml');
    }
}
