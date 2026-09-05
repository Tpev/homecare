<?php

namespace App\Http\Controllers;

use App\Models\StripeWebhookEvent;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\CaregiverStripeConnectService;
use App\Services\Payments\FamilyBillingService;
use App\Services\Payments\StripeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StripeWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        StripeClient $stripe,
        BookingPaymentService $payments,
        FamilyBillingService $familyBilling,
        CaregiverStripeConnectService $connect,
    ): JsonResponse {
        $payload = (string) $request->getContent();
        $signature = (string) $request->header('Stripe-Signature');

        try {
            $event = $stripe->constructWebhookEvent($payload, $signature);
        } catch (Throwable $e) {
            Log::warning('Stripe webhook signature validation failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Invalid webhook signature'], 401);
        }

        $type = (string) ($event->type ?? '');
        $eventId = (string) ($event->id ?? '');
        if ($eventId === '') {
            $eventId = 'payload_'.hash('sha256', $payload);
        }
        $object = json_decode(json_encode($event->data->object), true);
        if (! is_array($object)) {
            $object = [];
        }

        $inbox = StripeWebhookEvent::query()->firstOrCreate(
            ['stripe_event_id' => $eventId],
            [
                'type' => $type,
                'object_id' => (string) ($object['id'] ?? ''),
                'connected_account_id' => (string) ($event->account ?? ''),
                'livemode' => (bool) ($event->livemode ?? false),
                'status' => StripeWebhookEvent::STATUS_RECEIVED,
                'attempts' => 0,
                'payload' => json_decode($payload, true) ?: [],
            ],
        );

        $shouldProcess = DB::transaction(function () use ($inbox): bool {
            $locked = StripeWebhookEvent::query()->lockForUpdate()->find($inbox->id);
            if (! $locked || $locked->status === StripeWebhookEvent::STATUS_PROCESSED) {
                return false;
            }
            if ($locked->status === StripeWebhookEvent::STATUS_PROCESSING
                && $locked->updated_at?->isAfter(now()->subMinutes(5))) {
                return false;
            }

            $locked->forceFill([
                'status' => StripeWebhookEvent::STATUS_PROCESSING,
                'attempts' => (int) $locked->attempts + 1,
                'last_error' => null,
            ])->save();

            return true;
        });
        if (! $shouldProcess) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        try {
            if (in_array($type, [
                'payment_intent.amount_capturable_updated',
                'payment_intent.succeeded',
                'payment_intent.payment_failed',
                'payment_intent.canceled',
                'payment_intent.requires_action',
            ], true)) {
                $payments->handlePaymentIntentWebhook($object);
            }

            if (in_array($type, ['transfer.created', 'transfer.paid'], true)) {
                $payments->handleTransferWebhook($object);
            }

            if (in_array($type, ['transfer.reversed', 'transfer.reversal.created'], true)) {
                if (! $payments->handleTransferReversalWebhook($object, $type)) {
                    throw new \RuntimeException('Stripe transfer reversal dependencies are not ready yet.');
                }
            }

            if ($type === 'charge.refunded') {
                if (! $payments->handleChargeRefundWebhook($object)) {
                    throw new \RuntimeException('Stripe charge refund classification or dependencies are not ready yet.');
                }
            }

            if (in_array($type, ['refund.updated', 'refund.failed'], true)) {
                if (! $payments->handleRefundWebhook($object)) {
                    throw new \RuntimeException('Stripe refund classification or dependencies are not ready yet.');
                }
            }

            if (in_array($type, ['charge.dispute.created', 'charge.dispute.updated', 'charge.dispute.closed'], true)) {
                $payments->handleDisputeWebhook($object);
            }

            if ($type === 'checkout.session.completed') {
                $familyBilling->handleCheckoutSessionCompletedWebhook($object);
            }

            if ($type === 'account.updated') {
                $connect->handleAccountUpdatedWebhook($object);
            }

            $inbox->forceFill([
                'status' => StripeWebhookEvent::STATUS_PROCESSED,
                'processed_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $e) {
            $inbox->forceFill([
                'status' => StripeWebhookEvent::STATUS_FAILED,
                'last_error' => $e->getMessage(),
            ])->save();
            Log::error('Stripe webhook handler failure', [
                'type' => $type,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Webhook processing failed'], 500);
        }

        return response()->json(['ok' => true]);
    }
}
