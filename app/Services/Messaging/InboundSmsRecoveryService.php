<?php

namespace App\Services\Messaging;

use App\Models\SmsMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InboundSmsRecoveryService
{
    public function __construct(private TwilioSmsClient $twilio, private LeadSmsService $leads) {}

    public function recover(string $messageSid): SmsMessage
    {
        $result = $this->twilio->retrieveInboundMessage(trim($messageSid));
        $payload = $result['message'];
        if (empty($payload['date_created']) || ! is_string($payload['date_created'])) {
            throw new RuntimeException('Twilio did not return the original message timestamp; nothing was imported.');
        }
        $receivedAt = Carbon::parse($payload['date_created'])->setTimezone(config('app.timezone'));

        return DB::transaction(function () use ($payload, $result, $receivedAt): SmsMessage {
            $message = SmsMessage::query()->firstOrCreate(['twilio_sid' => $payload['sid']], [
                'direction' => SmsMessage::DIRECTION_INCOMING,
                'status' => SmsMessage::STATUS_RECEIVED,
                'from_phone' => $payload['from'],
                'to_phone' => $payload['to'],
                'body' => $payload['body'],
                'twilio_account_sid' => $payload['account_sid'],
                'twilio_status' => $payload['status'] ?? 'received',
                'num_media' => count($result['media']),
                'media' => $result['media'],
                'raw_payload' => ['recovered_from_twilio' => $payload, 'recovered_at' => now()->toIso8601String()],
                'received_at' => $receivedAt,
            ]);
            if ($message->direction !== SmsMessage::DIRECTION_INCOMING
                || $message->twilio_account_sid !== $payload['account_sid']) {
                throw new RuntimeException('An incompatible local message already uses this Twilio SID.');
            }
            if ($message->wasRecentlyCreated) {
                // The inbox displays created_at. Keep the original conversation order.
                $message->forceFill(['created_at' => $receivedAt])->save();
            }
            // Recover inbox/CRM history without sending an SMS or an alert email.
            $this->leads->receive($message, notify: false);

            return $message;
        });
    }
}
