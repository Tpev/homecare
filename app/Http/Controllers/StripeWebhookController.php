<?php

namespace App\Http\Controllers;

use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\CaregiverStripeConnectService;
use App\Services\Payments\FamilyBillingService;
use App\Services\Payments\StripeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $object = json_decode(json_encode($event->data->object), true);
        if (! is_array($object)) {
            $object = [];
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
                $payments->handleTransferReversalWebhook($object);
            }

            if ($type === 'charge.refunded') {
                $payments->handleChargeRefundWebhook($object);
            }

            if ($type === 'checkout.session.completed') {
                $familyBilling->handleCheckoutSessionCompletedWebhook($object);
            }

            if ($type === 'account.updated') {
                $connect->handleAccountUpdatedWebhook($object);
            }
        } catch (Throwable $e) {
            Log::error('Stripe webhook handler failure', [
                'type' => $type,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Webhook processing failed'], 500);
        }

        return response()->json(['ok' => true]);
    }
}
