<?php

namespace App\Services\Ops;

use App\Jobs\SendSlackOpsNotification;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Support\WeeklySchedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SlackOpsNotificationService
{
    public const EVENT_CARE_REQUEST_CREATED = 'care_request_created';

    public const EVENT_CAREGIVER_HIRED = 'caregiver_hired';

    public function queueCareRequestCreated(CareRequest $careRequest): void
    {
        if (! $this->enabled() || $careRequest->is_system_generated || $careRequest->status !== CareRequest::STATUS_OPEN) {
            return;
        }

        try {
            Bus::dispatch((new SendSlackOpsNotification(
                self::EVENT_CARE_REQUEST_CREATED,
                (int) $careRequest->id,
            ))->afterCommit());
        } catch (Throwable) {
            Log::warning('Slack operations notification could not be queued.', [
                'event' => self::EVENT_CARE_REQUEST_CREATED,
                'care_request_id' => $careRequest->id,
            ]);
        }
    }

    public function queueCaregiverHired(CareRequestApplication $application): void
    {
        if (! $this->enabled() || $application->status !== CareRequestApplication::STATUS_HIRED) {
            return;
        }

        $careRequest = $application->careRequest()->first();
        if (! $careRequest || $careRequest->is_system_generated) {
            return;
        }

        try {
            Bus::dispatch((new SendSlackOpsNotification(
                self::EVENT_CAREGIVER_HIRED,
                (int) $careRequest->id,
                (int) $application->id,
            ))->afterCommit());
        } catch (Throwable) {
            Log::warning('Slack operations notification could not be queued.', [
                'event' => self::EVENT_CAREGIVER_HIRED,
                'care_request_id' => $careRequest->id,
                'application_id' => $application->id,
            ]);
        }
    }

    public function deliver(string $event, int $careRequestId, ?int $applicationId = null): void
    {
        $webhookUrl = $this->webhookUrl();
        if (! $webhookUrl) {
            return;
        }

        $payload = match ($event) {
            self::EVENT_CARE_REQUEST_CREATED => $this->careRequestCreatedPayload($careRequestId),
            self::EVENT_CAREGIVER_HIRED => $this->caregiverHiredPayload($careRequestId, $applicationId),
            default => null,
        };

        if (! $payload) {
            return;
        }

        $this->post($webhookUrl, $payload);
    }

    public function sendTest(): void
    {
        $webhookUrl = $this->webhookUrl();
        if (! (bool) config('services.slack.ops.enabled', false) || ! $webhookUrl) {
            throw new RuntimeException('Slack operations notifications are disabled or the webhook configuration is invalid.');
        }

        $this->post($webhookUrl, [
            'text' => 'LoLo Care Slack operations notifications are connected.',
            'unfurl_links' => false,
            'unfurl_media' => false,
            'blocks' => [[
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => ':white_check_mark: *LoLo Care operations notifications are connected.*',
                ],
            ]],
        ]);
    }

    private function post(string $webhookUrl, array $payload): void
    {
        try {
            $response = Http::asJson()
                ->acceptJson()
                ->withOptions(['allow_redirects' => false])
                ->connectTimeout(3)
                ->timeout(max(3, (int) config('services.slack.ops.timeout_seconds', 8)))
                ->post($webhookUrl, $payload);
        } catch (ConnectionException) {
            throw new RuntimeException('Slack operations notification connection failed.');
        }

        if (! $response->successful() || trim($response->body()) !== 'ok') {
            throw new RuntimeException('Slack operations notification was rejected with HTTP '.$response->status().'.');
        }
    }

    public function enabled(): bool
    {
        return (bool) config('services.slack.ops.enabled', false) && $this->webhookUrl() !== null;
    }

    private function careRequestCreatedPayload(int $careRequestId): ?array
    {
        $careRequest = CareRequest::query()
            ->with('family:id,name,email')
            ->find($careRequestId);

        if (! $careRequest || $careRequest->is_system_generated) {
            return null;
        }

        return $this->payload(
            fallback: 'New family care request #'.$careRequest->id.'.',
            heading: ':new: New family care request',
            fields: [
                ['Family', $this->familyLabel($careRequest)],
                ['When', $this->scheduleLabel($careRequest)],
                ['Duration', $this->durationLabel($careRequest)],
                ['Service area', $this->locationLabel($careRequest)],
            ],
            careRequest: $careRequest,
        );
    }

    private function caregiverHiredPayload(int $careRequestId, ?int $applicationId): ?array
    {
        if (! $applicationId) {
            return null;
        }

        $application = CareRequestApplication::query()
            ->with(['caregiver:id,name,email', 'careRequest.family:id,name,email'])
            ->find($applicationId);
        $careRequest = $application?->careRequest;

        if (! $application || ! $careRequest || (int) $careRequest->id !== $careRequestId || $careRequest->is_system_generated) {
            return null;
        }

        $caregiver = $application->caregiver;
        $caregiverLabel = $caregiver
            ? $this->personLabel((string) $caregiver->name, (string) $caregiver->email)
            : 'Caregiver account unavailable';

        return $this->payload(
            fallback: 'A caregiver was hired for request #'.$careRequest->id.'.',
            heading: ':white_check_mark: Family hired a caregiver',
            fields: [
                ['Family', $this->familyLabel($careRequest)],
                ['Caregiver hired', $caregiverLabel],
                ['When', $this->scheduleLabel($careRequest)],
                ['Duration', $this->durationLabel($careRequest)],
                ['Service area', $this->locationLabel($careRequest)],
            ],
            careRequest: $careRequest,
        );
    }

    /** @param list<array{0:string,1:string}> $fields */
    private function payload(string $fallback, string $heading, array $fields, CareRequest $careRequest): array
    {
        return [
            'text' => $this->plain($fallback),
            'unfurl_links' => false,
            'unfurl_media' => false,
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => ['type' => 'plain_text', 'text' => Str::limit($heading, 150, '')],
                ],
                [
                    'type' => 'section',
                    'fields' => collect($fields)->map(fn (array $field): array => [
                        'type' => 'mrkdwn',
                        'text' => '*'.$this->escape($field[0])."*\n".$this->escape($field[1]),
                    ])->all(),
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => '<'.$this->adminUrl($careRequest).'|Open care request #'.$careRequest->id.' in LoLo Admin>',
                    ],
                ],
                [
                    'type' => 'context',
                    'elements' => [[
                        'type' => 'mrkdwn',
                        'text' => 'Private operations notice · Full address and care details remain protected in LoLo Admin.',
                    ]],
                ],
            ],
        ];
    }

    private function familyLabel(CareRequest $careRequest): string
    {
        $family = $careRequest->family;

        return $family
            ? $this->personLabel((string) $family->name, (string) $family->email)
            : 'Family account #'.($careRequest->family_user_id ?: 'unavailable');
    }

    private function personLabel(string $name, string $email): string
    {
        $name = trim($name) ?: 'Unnamed account';
        $email = trim($email);

        return Str::limit($name.($email !== '' ? ' ('.$email.')' : ''), 300, '…');
    }

    private function scheduleLabel(CareRequest $careRequest): string
    {
        if ($careRequest->request_type === CareRequest::TYPE_RECURRING) {
            $schedule = $careRequest->recurringScheduleLabel() ?: 'Schedule to be confirmed';
            $starts = $careRequest->recurring_starts_on?->format('M j, Y');
            $ends = $careRequest->recurring_ends_on?->format('M j, Y');

            return $schedule.($starts ? ' · starts '.$starts : '').($ends ? ' · through '.$ends : ' · ongoing');
        }

        $start = $careRequest->requested_start_at?->copy()->timezone((string) config('app.timezone'));
        $end = $careRequest->requested_end_at?->copy()->timezone((string) config('app.timezone'));
        if (! $start) {
            return 'Time to be confirmed';
        }

        return $start->format('D, M j, Y · g:i A')
            .($end ? '–'.$end->format('g:i A') : '')
            .' '.$start->format('T');
    }

    private function durationLabel(CareRequest $careRequest): string
    {
        if ($careRequest->request_type === CareRequest::TYPE_RECURRING) {
            $minutes = collect($careRequest->recurringScheduleSlots())
                ->sum(fn (array $slot): int => WeeklySchedule::durationMinutes($slot));

            return $minutes > 0 ? $this->minutesLabel((int) $minutes).' per week' : 'To be confirmed';
        }

        $start = $careRequest->requested_start_at;
        $end = $careRequest->requested_end_at;

        return $start && $end
            ? $this->minutesLabel(max(0, (int) $start->diffInMinutes($end, false)))
            : 'To be confirmed';
    }

    private function minutesLabel(int $minutes): string
    {
        if ($minutes <= 0) {
            return 'To be confirmed';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;
        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours.' '.Str::plural('hour', $hours);
        }
        if ($remaining > 0) {
            $parts[] = $remaining.' '.Str::plural('minute', $remaining);
        }

        return implode(' ', $parts);
    }

    private function locationLabel(CareRequest $careRequest): string
    {
        return collect([$careRequest->city, $careRequest->state, $careRequest->zip])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->implode(', ') ?: 'Not provided';
    }

    private function adminUrl(CareRequest $careRequest): string
    {
        return route('admin.requests.show', $careRequest);
    }

    private function plain(string $value): string
    {
        return Str::limit(preg_replace('/\s+/', ' ', trim($value)) ?: '', 300, '…');
    }

    private function escape(string $value): string
    {
        return Str::limit(str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value), 1000, '…');
    }

    private function webhookUrl(): ?string
    {
        $url = trim((string) config('services.slack.ops.webhook_url'));
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== 'hooks.slack.com'
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || preg_match('#^/services/[A-Za-z0-9]+/[A-Za-z0-9]+/[A-Za-z0-9_-]+$#', (string) ($parts['path'] ?? '')) !== 1) {
            return null;
        }

        return $url;
    }
}
