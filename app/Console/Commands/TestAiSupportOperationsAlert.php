<?php

namespace App\Console\Commands;

use App\Models\MarketplaceNotificationDelivery;
use App\Models\User;
use App\Services\AiSupport\AiSupportIncidentService;
use App\Services\AiSupport\AiSupportReadinessService;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Notifications\NotificationChannels;
use App\Support\MarketplaceEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestAiSupportOperationsAlert extends Command
{
    protected $signature = 'ai-support:test-operations-alert
        {--send : Dispatch one content-free test to every full administrator}
        {--actor-email= : Full administrator recording the test}
        {--confirm= : Must equal SEND-CONTENT-FREE-ALERT}';

    protected $description = 'Plan or dispatch the content-free AI Support in-app and email operations-alert test.';

    public function handle(
        MarketplaceNotificationService $notifications,
        AiSupportReadinessService $readiness,
        AiSupportIncidentService $incidents,
    ): int {
        $admins = User::query()->where('role', 'admin')->orderBy('id')->get();
        $this->line('Recipients: '.$admins->count().' full administrator(s); channels: in-app and email.');
        if (! $this->option('send')) {
            $this->warn('Plan only. No notification or evidence record was created.');

            return self::SUCCESS;
        }

        if ((string) $this->option('confirm') !== 'SEND-CONTENT-FREE-ALERT') {
            $this->error('Use --confirm=SEND-CONTENT-FREE-ALERT to dispatch the test.');

            return self::FAILURE;
        }
        $actor = User::query()->where('email', trim((string) $this->option('actor-email')))->first();
        if (! $actor?->isAdministrator() || $admins->isEmpty()) {
            $this->error('A valid full-administrator actor and at least one administrator recipient are required.');

            return self::FAILURE;
        }

        $reference = (string) Str::uuid();
        $dedupeKey = 'ai-support-operations-test-'.$reference;
        $notifications->notify(
            recipients: $admins,
            eventKey: MarketplaceEvent::SUPPORT_TICKET_REPLY,
            title: 'AI Support operational test',
            body: 'This is a content-free AI Support alert test. No customer action is required.',
            url: route('admin.ai-support.readiness'),
            payload: ['test_reference' => $reference],
            dedupeKey: $dedupeKey,
            channelOverrides: [
                NotificationChannels::EMAIL => true,
                NotificationChannels::IN_APP => true,
            ],
        );

        $deliveries = MarketplaceNotificationDelivery::query()
            ->whereIn('user_id', $admins->pluck('id'))
            ->where('dedupe_key', 'like', $dedupeKey.':%')
            ->get();
        $expected = $admins->count() * 2;
        $failed = $deliveries->where('status', 'failed')->count();
        $dispatchAccepted = $deliveries->count() === $expected && $failed === 0;
        $status = $dispatchAccepted ? 'pending' : 'failed';
        $summary = $dispatchAccepted
            ? "Dispatch accepted for {$admins->count()} administrator(s) on both channels; inbox receipt still requires human confirmation."
            : "Operations-alert test recorded {$deliveries->count()} of {$expected} expected channel dispatches with {$failed} failure(s).";
        $readiness->record(
            $actor,
            'operations_alert_delivery',
            $status,
            $summary,
            'operations-alert-test:'.$reference,
            safeMetadata: [
                'expected_deliveries' => $expected,
                'recorded_deliveries' => $deliveries->count(),
                'failed_deliveries' => $failed,
            ],
        );

        if (! $dispatchAccepted) {
            $incidents->open(
                'operations_alert_test_failed',
                'The content-free administrator operations-alert test did not dispatch on every required channel.',
            );
            $this->error($summary);

            return self::FAILURE;
        }

        $this->info($summary);
        $this->line('After both administrators confirm receipt, record this evidence item as Passed in Admin Release readiness.');

        return self::SUCCESS;
    }
}
