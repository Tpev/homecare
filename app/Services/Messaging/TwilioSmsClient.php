<?php

namespace App\Services\Messaging;

use App\Exceptions\Messaging\TwilioSmsRejectedException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TwilioSmsClient
{
    /**
     * @return array<string, mixed>
     */
    public function sendMessage(string $to, string $body, ?string $from = null, ?string $statusCallbackUrl = null): array
    {
        $to = self::normalizePhone($to);
        $from = self::normalizePhone($from ?: (string) config('services.twilio.sms_from'));
        $body = trim($body);

        if ($to === '') {
            throw new TwilioSmsRejectedException('A recipient phone number is required.');
        }

        if ($from === '') {
            throw new TwilioSmsRejectedException('TWILIO_SMS_FROM or TWILIO_PHONE_NUMBER is not configured.');
        }

        if ($body === '') {
            throw new TwilioSmsRejectedException('A message body is required.');
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
            throw new TwilioSmsRejectedException('Twilio SMS credentials are not configured.');
        }

        try {
            $payload = [
                'To' => $to,
                'Body' => $body,
                'StatusCallback' => $statusCallbackUrl ?: $this->statusCallbackUrl(),
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
            // A timeout or server error may follow provider acceptance. Only a clear
            // client rejection permits a replacement send without reconciliation.
            if (in_array($response->status(), [400, 401, 403, 404, 405, 422, 429], true)) {
                throw new TwilioSmsRejectedException($this->responseErrorMessage($response));
            }
            throw new RuntimeException($this->responseErrorMessage($response));
        }

        $payload = $response->json();
        if (! is_array($payload) || ! is_string($payload['sid'] ?? null) || trim($payload['sid']) === '') {
            throw new RuntimeException('Twilio returned an invalid SMS response.');
        }

        return $payload;
    }

    /** @return array{message: array<string, mixed>, media: list<array{url: string, content_type: string}>} */
    public function retrieveInboundMessage(string $messageSid): array
    {
        if (! preg_match('/^(SM|MM)[0-9a-fA-F]{32}$/D', $messageSid)) {
            throw new RuntimeException('Enter a valid Twilio Message SID (SM or MM followed by 32 hexadecimal characters).');
        }
        if ((bool) config('services.twilio.bypass', false)) {
            throw new RuntimeException('Message recovery is unavailable while Twilio bypass is enabled.');
        }

        $accountSid = trim((string) config('services.twilio.account_sid'));
        $authToken = (string) config('services.twilio.auth_token');
        if (! preg_match('/^AC[0-9a-fA-F]{32}$/D', $accountSid) || $authToken === '') {
            throw new RuntimeException('Twilio SMS credentials are not configured correctly.');
        }
        $endpoint = 'https://api.twilio.com/2010-04-01/Accounts/'.$accountSid.'/Messages/'.$messageSid;
        $get = function (string $url, array $query = []) use ($accountSid, $authToken): array {
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->withoutRedirecting()->timeout((int) config('services.twilio.timeout', 15))
                ->get($url, $query);
            if (! $response->successful()) {
                throw new RuntimeException($this->responseErrorMessage($response));
            }
            $payload = $response->json();
            if (! is_array($payload)) {
                throw new RuntimeException('Twilio returned an invalid message response.');
            }

            return $payload;
        };

        $payload = $get($endpoint.'.json');
        if (($payload['sid'] ?? null) !== $messageSid || ($payload['account_sid'] ?? null) !== $accountSid
            || ($payload['direction'] ?? null) !== 'inbound') {
            throw new RuntimeException('Twilio did not return the requested inbound message for this account.');
        }
        $ourPhone = self::normalizePhone((string) config('services.twilio.sms_from'));
        if ($ourPhone === '' || ($payload['to'] ?? null) !== $ourPhone
            || ! preg_match('/^\+[1-9][0-9]{7,14}$/D', (string) ($payload['from'] ?? ''))) {
            throw new RuntimeException('This message is not an incoming SMS to the configured app phone number.');
        }
        $numMedia = filter_var($payload['num_media'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000]]);
        if ($numMedia === false || ! is_string($payload['body'] ?? null)
            || ($payload['body'] === '' && $numMedia === 0)) {
            throw new RuntimeException('The original message content is missing or redacted in Twilio.');
        }
        $media = [];
        if ($numMedia > 0) {
            $page = $get($endpoint.'/Media.json', ['PageSize' => 1000]);
            if (! is_array($page['media_list'] ?? null) || count($page['media_list']) !== $numMedia
                || ! empty($page['next_page_uri'])) {
                throw new RuntimeException('Twilio did not return all message attachments; nothing was imported.');
            }
            $seen = [];
            foreach ($page['media_list'] as $item) {
                $sid = (string) ($item['sid'] ?? '');
                if (! preg_match('/^ME[0-9a-fA-F]{32}$/D', $sid) || isset($seen[$sid])
                    || ($item['account_sid'] ?? null) !== $accountSid || ($item['parent_sid'] ?? null) !== $messageSid
                    || ! is_string($item['content_type'] ?? null) || $item['content_type'] === '') {
                    throw new RuntimeException('Twilio returned an invalid message attachment; nothing was imported.');
                }
                $seen[$sid] = true;
                $media[] = ['url' => $endpoint.'/Media/'.$sid, 'content_type' => $item['content_type']];
            }
        }

        return ['message' => $payload, 'media' => $media];
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
