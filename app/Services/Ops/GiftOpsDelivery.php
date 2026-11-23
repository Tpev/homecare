<?php

namespace App\Services\Ops;

use App\Models\CareHourGiftEvent;
use App\Services\CareHours\GiftFunnelRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GiftOpsDelivery
{
    public function deliver(int $id): void
    {
        $slack = app(SlackOpsNotificationService::class);
        if (! $slack->enabled()) {
            return;
        }
        $event = DB::transaction(function () use ($id) {
            $event = CareHourGiftEvent::lockForUpdate()->find($id);
            if (! $event || $event->slack_status !== 'pending' || $event->available_at?->isFuture()) {
                return null;
            }
            $event->update(['slack_status' => 'sending', 'sending_at' => now(), 'slack_attempts' => $event->slack_attempts + 1]);

            return $event;
        });
        if (! $event) {
            return;
        }
        try {
            $slack->deliverManagedPayload($this->payload($event));
        } catch (SlackDeliveryException $exception) {
            $retry = ! $exception->uncertain && $exception->retryable && $event->slack_attempts < 4;
            $event->update(['slack_status' => $exception->uncertain ? 'uncertain' : ($retry ? 'pending' : 'failed'),
                'last_error' => $exception->getMessage(), 'available_at' => now()->addSeconds([10, 60, 300][$event->slack_attempts - 1] ?? 300)]);
            if ($retry) {
                throw new RuntimeException('Gift Ops delivery will retry.');
            }
            Log::warning('Gift Ops delivery needs review.', ['event_id' => $id, 'status' => $event->slack_status]);

            return;
        } catch (Throwable) {
            $event->update(['slack_status' => 'uncertain', 'last_error' => 'Delivery outcome unknown. Check Ops before replaying.']);

            return;
        }
        $event->update(['slack_status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
    }

    public function payload(CareHourGiftEvent $event): array
    {
        $purchase = $event->purchase;
        if (! $purchase || ! in_array($event->event, GiftFunnelRecorder::ALERTS, true)) {
            throw new RuntimeException('Gift event has no deliverable purchase.');
        }
        $title = match ($event->event) {
            'checkout_started' => 'Gift card checkout started', 'payment_confirmed' => 'Gift card purchased',
            'payment_failed' => 'Gift card payment failed', 'gift_claimed' => 'Gift card claimed',
        };
        $note = match ($event->event) {
            'checkout_started' => 'A checkout session was created. Payment is not yet confirmed.',
            'payment_confirmed' => 'Payment was confirmed by the payment provider. Open the gift for its current status.',
            'payment_failed' => 'The provider reported a failed payment. The buyer may retry; check the current status before following up.',
            'gift_claimed' => 'The recipient claimed the gift and the hours were credited to their family account.',
        };
        $mode = $purchase->livemode === null ? 'Not yet confirmed' : ($purchase->livemode ? 'Live' : 'Test');
        $fields = [
            'Gift' => $event->hours.' hours',
            'Amount' => '$'.number_format($event->amount_cents / 100, 2).' '.strtoupper($event->currency),
            'Purchase' => $purchase->public_id,
            'Payment mode' => $mode,
            'Event time' => Carbon::parse($event->occurred_at, 'UTC')->timezone('Europe/Paris')->format('M j, Y H:i:s T'),
        ];

        return ['text' => $title.' · '.$event->hours.' hours', 'unfurl_links' => false, 'unfurl_media' => false, 'blocks' => [
            ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => $title]],
            ['type' => 'section', 'fields' => collect($fields)->map(fn ($value, $key) => [
                'type' => 'plain_text', 'text' => $key."\n".$value,
            ])->values()->all()],
            ['type' => 'section', 'text' => ['type' => 'plain_text', 'text' => $note]],
            ['type' => 'actions', 'elements' => [[
                'type' => 'button', 'text' => ['type' => 'plain_text', 'text' => 'Open gift in admin'],
                'url' => route('admin.care-hours.gifts.show', $purchase->public_id),
            ]]],
        ]];
    }
}
