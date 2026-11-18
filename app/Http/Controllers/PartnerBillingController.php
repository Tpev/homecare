<?php

namespace App\Http\Controllers;

use App\Exceptions\Payments\PaymentException;
use App\Models\PartnerProfile;
use App\Services\Partners\PartnerBillingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartnerBillingController extends Controller
{
    private function profile(Request $request): PartnerProfile
    {
        abort_unless($request->user()?->role === 'partner' && $request->user()->access_revoked_at === null, 403);

        return PartnerProfile::where('user_id', $request->user()->id)->firstOrFail();
    }

    public function show(Request $request, PartnerBillingService $service)
    {
        $profile = $this->profile($request);
        if ($request->filled('checkout_session_id')) {
            try {
                if ($request->query('flow') === 'setup') {
                    $service->syncSetupCheckoutSession($profile, $request->string('checkout_session_id')->toString());
                    $message = 'Payment method saved securely.';
                } else {
                    $service->verifyCheckoutReturn($profile, $request->string('checkout_session_id')->toString());
                    $message = 'Checkout received. Your leads will appear in your balance once payment is confirmed.';
                }

                return redirect()->route('partner.billing.show')->with('status', $message);
            } catch (\Throwable $exception) {
                report($exception);

                return redirect()->route('partner.billing.show')->withErrors(['billing' => $exception instanceof PaymentException
                    ? $exception->userMessage : 'We could not verify checkout yet. Please try again shortly.']);
            }
        }
        $billingUnavailable = false;
        $invoicesUnavailable = false;
        try {
            $billing = $service->summaryFor($profile);
        } catch (\Throwable $exception) {
            report($exception);
            $billingUnavailable = true;
            $billing = ['ready' => false, 'card' => null];
        }
        try {
            $invoices = $service->invoicesFor($profile);
        } catch (\Throwable $exception) {
            report($exception);
            $invoicesUnavailable = true;
            $invoices = collect();
        }
        $subscription = $service->subscriptionFor($profile);

        return view('partner.billing', [
            'profile' => $profile, 'billing' => $billing, 'billingUnavailable' => $billingUnavailable,
            'invoices' => $invoices, 'invoicesUnavailable' => $invoicesUnavailable, 'subscription' => $subscription,
            'packageOptions' => $service->packageOptions(), 'unitAmountCents' => config('partners.unit_price_cents'),
            'currency' => config('partners.currency'), 'canPurchase' => $profile->status === PartnerProfile::STATUS_APPROVED,
            'canSubscribe' => $profile->status === PartnerProfile::STATUS_APPROVED && ! $subscription?->blocksNewCheckout(),
            'canCancel' => $subscription?->stripe_subscription_id && $subscription->blocksNewCheckout() && ! $subscription->cancel_at_period_end,
        ]);
    }

    public function setupCheckout(Request $request, PartnerBillingService $service)
    {
        return $this->checkoutResponse(fn () => $service->createSetupCheckoutUrl($this->profile($request),
            route('partner.billing.show', ['flow' => 'setup']).'&checkout_session_id={CHECKOUT_SESSION_ID}',
            route('partner.billing.show', ['checkout' => 'cancel'])));
    }

    public function subscribe(Request $request, PartnerBillingService $service)
    {
        $profile = $this->profile($request);
        $validated = $request->validate(['plan' => ['required', Rule::in(array_keys(config('partners.plans')))]]);

        return $this->checkoutResponse(fn () => $service->createCheckoutUrl($profile, $validated['plan'],
            route('partner.billing.show', ['flow' => 'purchase']).'&checkout_session_id={CHECKOUT_SESSION_ID}',
            route('partner.billing.show', ['checkout' => 'cancel'])));
    }

    public function cancel(Request $request, PartnerBillingService $service)
    {
        $profile = $this->profile($request);
        try {
            $service->cancelSubscription($profile);

            return redirect()->route('partner.billing.show')->with('status', 'Your subscription will stop renewing at the end of the current billing period. Unused purchased leads remain available.');
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('partner.billing.show')->withErrors(['billing' => $exception instanceof PaymentException
                ? $exception->userMessage : 'We could not update your subscription. Please try again.']);
        }
    }

    private function checkoutResponse(\Closure $start)
    {
        try {
            return redirect()->away($start());
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('partner.billing.show')->withErrors(['billing' => $exception instanceof PaymentException
                ? $exception->userMessage : 'Secure checkout is temporarily unavailable. Please try again.']);
        }
    }
}
