<?php

namespace App\Services\Ops;

use App\Models\ManagedCareOpsEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ManagedCareOpsDelivery
{
    public function deliver(int $id): void
    {
        if (! app(SlackOpsNotificationService::class)->enabled()) {
            return;
        }
        $event = DB::transaction(function () use ($id) {
            $event = ManagedCareOpsEvent::lockForUpdate()->find($id);
            if (! $event || $event->status !== 'pending' || $event->available_at->isFuture()) {
                return null;
            }
            $event->update(['status' => 'sending', 'sending_at' => now(), 'attempts' => $event->attempts + 1]);

            return $event;
        });
        if (! $event) {
            return;
        }
        try {
            app(SlackOpsNotificationService::class)->deliverManagedPayload($event->payload);
        } catch (SlackDeliveryException $exception) {
            $retry = ! $exception->uncertain && $exception->retryable && $event->attempts < 4;
            $event->update(['status' => $exception->uncertain ? 'uncertain' : ($retry ? 'pending' : 'failed'),
                'last_error' => $exception->getMessage(), 'available_at' => now()->addSeconds([10, 60, 300][$event->attempts - 1] ?? 300)]);
            if ($retry) {
                throw new RuntimeException('Managed care Slack delivery will retry.');
            }
            Log::warning('Managed care Slack delivery needs review.', ['event_id' => $id, 'status' => $event->status]);

            return;
        } catch (Throwable) {
            // Never replay an attempt whose provider outcome is unknown.
            $event->update(['status' => 'uncertain', 'last_error' => 'Delivery outcome unknown. Check the Ops channel before replaying.']);
            Log::warning('Managed care Slack delivery outcome unknown.', ['event_id' => $id]);

            return;
        }
        // If the process stops between posting and this write, stale sending becomes uncertain.
        $event->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
    }
}
