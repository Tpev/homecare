<?php

namespace App\Services\Messaging;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class TwilioSmsClient
{
    /**
     * @return array<string, mixed>
     */
    public function sendMessage(string $to, string $body, ?string $from = null): array
    {
        $to = self::normalizePhone($to);
        $from = self::normalizePhone($from ?: (string) config('services.twilio.sms_from'));
        $body = trim($body);

        if ($to === '') {
            throw new InvalidArgumentException('A recipient phone number is required.');
        }

        if ($from === '') {
            throw new RuntimeException('TWILIO_SMS_FROM or TWILIO_PHONE_NUMBER is not configured.');
        }

        if ($body === '') {
            throw new InvalidArgumentException('A message body is required.');
        }

        if ((bool) config('services.twilio.bypass', false)) {
            return [
                'sid' => 'SM_bypass_'.(string) Str::ulid(),
                'status' => 'queued',
                'from' => $from,
                'to' => $to,
                'body' => $body,
            ];
        }

        $accountSid = (string) config('services.twilio.account_sid');
        $authToken = (string) config('services.twilio.auth_token');

        if ($accountSid === '' || $authToken === '') {
            throw new RuntimeException('Twilio SMS credentials are not configured.');
        }

        try {
            $payload = [
                'To' => $to,
                'Body' => $body,
                'StatusCallback' => $this->statusCallbackUrl(),
            ];

            $messagingServiceSid = trim((string) config('services.twilio.messaging_service_sid'));
            if ($messagingServiceSid !== '') {
                $payload['MessagingServiceSid'] = $messagingServiceSid;
            }

            if ($from !== '') {
                $payload['From'] = $from;
            }

            $response = Http::asForm()
                ->withBasicAuth($accountSid, $authToken)
                ->timeout((int) config('services.twilio.timeout', 15))
                ->post($this->messagesEndpoint($accountSid), $payload);
        } catch (Throwable $e) {
            throw new RuntimeException('Twilio SMS request failed: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new RuntimeException($this->responseErrorMessage($response));
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Twilio returned an invalid SMS response.');
        }

        return $payload;
    }

    public function hasValidWebhookSignature(Request $request): bool
    {
        if ((bool) config('services.twilio.bypass', false)) {
            return true;
        }

        $authToken = (string) config('services.twilio.auth_token');
        $provided = trim((string) $request->header('X-Twilio-Signature'));

        if ($authToken === '' || $provided === '') {
            return false;
        }

        $base = $this->webhookUrl($request);
        $params = $request->request->all();
        ksort($params, SORT_STRING);

        foreach ($params as $key => $value) {
            $values = is_array($value) ? array_values($value) : [$value];
            sort($values, SORT_STRING);

            foreach ($values as $item) {
                $base .= (string) $key.(string) $item;
            }
        }

        $expected = base64_encode(hash_hmac('sha1', $base, $authToken, true));

        return hash_equals($expected, $provided);
    }

    public static function normalizePhone(?string $phone): string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return '';
        }

        $hasPlus = str_starts_with($phone, '+');
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if ($digits === '') {
            return '';
        }

        if ($hasPlus) {
            return '+'.$digits;
        }

        if (strlen($digits) === 10) {
            return '+1'.$digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '+'.$digits;
        }

        return '+'.$digits;
    }

    private function webhookUrl(Request $request): string
    {
        $baseUrl = trim((string) config('services.twilio.webhook_base_url'));

        if ($baseUrl !== '') {
            return rtrim($baseUrl, '/').$request->getRequestUri();
        }

        return $request->fullUrl();
    }

    private function messagesEndpoint(string $accountSid): string
    {
        return 'https://api.twilio.com/2010-04-01/Accounts/'.$accountSid.'/Messages.json';
    }

    private function statusCallbackUrl(): string
    {
        $configured = trim((string) config('services.twilio.status_callback_url'));

        return $configured !== '' ? $configured : route('webhooks.twilio.sms.status');
    }

    private function responseErrorMessage(Response $response): string
    {
        $payload = $response->json();
        $message = is_array($payload)
            ? (string) ($payload['message'] ?? $payload['detail'] ?? '')
            : '';

        return trim($message) !== ''
            ? 'Twilio SMS returned '.$response->status().': '.$message
            : 'Twilio SMS returned HTTP '.$response->status().'.';
    }
}
