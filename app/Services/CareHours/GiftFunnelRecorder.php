<?php

namespace App\Services\CareHours;

use App\Jobs\SendGiftOpsNotification;
use App\Models\CareHourGiftEvent;
use App\Models\CareHourPurchase;
use App\Services\Ops\SlackOpsNotificationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class GiftFunnelRecorder
{
    public const LABELS = [
        'landing_view' => 'Gift landing page views',
        'shop_view' => 'Gift shop page views',
        'verification_requested' => 'Verification codes requested',
        'verification_completed' => 'Email verifications completed',
        'checkout_submitted' => 'Checkout submissions accepted',
        'checkout_started' => 'Checkout sessions created',
        'payment_confirmed' => 'Gift purchases confirmed',
        'payment_failed' => 'Purchases with a payment failure',
        'checkout_expired' => 'Checkout sessions expired',
        'gift_claimed' => 'Gift cards claimed',
    ];

    public const ALERTS = ['checkout_started', 'payment_confirmed', 'payment_failed', 'gift_claimed'];

    public function visitor(Request $request): ?string
    {
        if (! $request->hasSession() || $this->excluded($request)) {
            return null;
        }
        $id = $request->session()->get('care_hours.gift_funnel_visitor');
        if (! is_string($id) || ! Str::isUuid($id)) {
            $id = (string) Str::uuid();
            $request->session()->put('care_hours.gift_funnel_visitor', $id);
        }

        return $id;
    }

    public function page(Request $request, string $event): void
    {
        if (! $request->isMethod('GET') || ! in_array($event, ['landing_view', 'shop_view'], true)) {
            return;
        }
        $this->safely(function () use ($request, $event): void {
            if ($visitor = $this->visitor($request)) {
                $this->store($event, 'page:'.Str::uuid(), ['visitor_id' => $visitor]);
            }
        });
    }

    public function verification(Request $request, string $event, string $verificationId): void
    {
        if (! in_array($event, ['verification_requested', 'verification_completed'], true)) {
            return;
        }
        $this->safely(function () use ($request, $event, $verificationId): void {
            if ($visitor = $this->visitor($request)) {
                $this->store($event, $event.':'.$verificationId, ['visitor_id' => $visitor, 'verification_id' => $verificationId]);
            }
        });
    }

    public function purchase(string $event, CareHourPurchase $purchase, ?string $visitorId = null): void
    {
        if (! in_array($event, ['checkout_submitted', 'checkout_started', 'payment_confirmed', 'payment_failed', 'checkout_expired', 'gift_claimed'], true)) {
            return;
        }
        $this->safely(function () use ($event, $purchase, $visitorId): void {
            if (! $purchase->gift()->exists()) {
                return;
            }
            // The acquisition identity stays with the buyer even when another person claims the gift.
            $visitor = CareHourGiftEvent::where('care_hour_purchase_id', $purchase->id)
                ->whereNotNull('visitor_id')->orderBy('id')->value('visitor_id');
            $visitor ??= $visitorId && Str::isUuid($visitorId) ? $visitorId : null;
            $this->store($event, $event.':purchase:'.$purchase->id, [
                'visitor_id' => $visitor, 'care_hour_purchase_id' => $purchase->id,
                'amount_cents' => $purchase->amount_cents, 'hours' => intdiv($purchase->paid_minutes, 60),
                'currency' => $purchase->currency,
            ]);
        });
    }

    public function recoveredCheckout(CareHourPurchase $purchase): void
    {
        $this->safely(function () use ($purchase): void {
            // Do not turn an old receipt refresh into a newly acquired checkout.
            if (CareHourGiftEvent::where('care_hour_purchase_id', $purchase->id)->where('event', 'checkout_submitted')->exists()) {
                $this->purchase('checkout_started', $purchase);
            }
        });
    }

    private function store(string $event, string $key, array $attributes): void
    {
        $notify = in_array($event, self::ALERTS, true);
        $entry = CareHourGiftEvent::firstOrCreate(['dedupe_key' => hash('sha256', $key)], $attributes + [
            'event' => $event, 'occurred_at' => now('UTC')->format('Y-m-d H:i:s'),
            'slack_status' => $notify ? (app(SlackOpsNotificationService::class)->enabled() ? 'pending' : 'disabled') : null,
            'available_at' => $notify ? now() : null,
        ]);
        if ($entry->wasRecentlyCreated && $entry->slack_status === 'pending') {
            DB::afterCommit(function () use ($entry): void {
                try {
                    SendGiftOpsNotification::dispatch($entry->id);
                } catch (Throwable) {
                    // The existing scheduler recovers pending deliveries via ops:gifts:dispatch.
                    Log::warning('Gift Ops dispatch deferred.', ['event_id' => $entry->id]);
                }
            });
        }
    }

    private function excluded(Request $request): bool
    {
        return in_array($request->user()?->role, ['admin', 'sdr', 'sales', 'customer_success'], true)
            || preg_match('/bot|crawler|spider|preview|headless/i', (string) $request->userAgent()) === 1
            || str_contains(strtolower($request->header('Purpose', '').' '.$request->header('Sec-Purpose', '')), 'prefetch');
    }

    private function safely(Closure $callback): void
    {
        try {
            if (Schema::hasTable('care_hour_gift_events')) {
                // A savepoint isolates analytics failures from financial transactions.
                DB::transaction($callback);
            }
        } catch (Throwable $exception) {
            Log::warning('Gift analytics could not be recorded.', ['exception_type' => $exception::class]);
        }
    }
}
