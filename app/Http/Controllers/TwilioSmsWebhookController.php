<?php

namespace App\Http\Controllers;

use App\Models\SmsMessage;
use App\Services\Messaging\TwilioSmsClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TwilioSmsWebhookController extends Controller
{
    public function __invoke(Request $request, TwilioSmsClient $twilio): Response
    {
        if (! $twilio->hasValidWebhookSignature($request)) {
            Log::warning('Twilio SMS webhook signature validation failed', [
                'from' => $request->input('From'),
                'message_sid' => $request->input('MessageSid'),
            ]);

            return response('Invalid Twilio signature', 401);
        }

        $payload = $request->request->all();
        $messageSid = trim((string) ($payload['MessageSid'] ?? ''));
        $numMedia = max(0, (int) ($payload['NumMedia'] ?? 0));
        $media = [];

        for ($index = 0; $index < $numMedia; $index++) {
            $media[] = [
                'url' => (string) ($payload['MediaUrl'.$index] ?? ''),
                'content_type' => (string) ($payload['MediaContentType'.$index] ?? ''),
            ];
        }

        $attributes = [
            'direction' => SmsMessage::DIRECTION_INCOMING,
            'status' => SmsMessage::STATUS_RECEIVED,
            'from_phone' => (string) ($payload['From'] ?? ''),
            'to_phone' => (string) ($payload['To'] ?? ''),
            'body' => (string) ($payload['Body'] ?? ''),
            'twilio_sid' => $messageSid ?: null,
            'twilio_account_sid' => (string) ($payload['AccountSid'] ?? ''),
            'twilio_status' => (string) ($payload['SmsStatus'] ?? $payload['MessageStatus'] ?? 'received'),
            'num_media' => $numMedia,
            'media' => $media,
            'raw_payload' => $payload,
            'received_at' => now(),
        ];

        if ($messageSid !== '') {
            SmsMessage::query()->updateOrCreate(['twilio_sid' => $messageSid], $attributes);
        } else {
            SmsMessage::query()->create($attributes);
        }

        return response('<Response></Response>', 200)->header('Content-Type', 'text/xml');
    }
}
