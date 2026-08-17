<?php

namespace App\Http\Controllers;

use App\Exceptions\Payments\PaymentException;
use App\Services\AiSupport\AiSupportGuidedTaskService;
use App\Services\Payments\FamilyBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FamilyBillingController extends Controller
{
    public function show(
        Request $request,
        FamilyBillingService $billing,
        AiSupportGuidedTaskService $guidedTasks,
    ): View|RedirectResponse {
        $user = auth()->user();
        abort_unless($user && $user->role === 'family', 403);

        if ($request->query('checkout') === 'cancel') {
            try {
                $guidedTasks->paymentSetupCancelled($user);
            } catch (\Throwable $exception) {
                report($exception);
            }

            return redirect()
                ->route('family.billing.show')
                ->with('status', 'No payment-method changes were made.');
        }

        $sessionId = trim((string) $request->query('checkout_session_id', ''));
        if ($sessionId !== '') {
            try {
                $billing->syncSetupCheckoutSession($user, $sessionId);
            } catch (PaymentException $e) {
                try {
                    $guidedTasks->paymentSetupFailed($user, 'secure_checkout_verification_failed');
                } catch (\Throwable $guidedException) {
                    report($guidedException);
                }

                return redirect()
                    ->route('family.billing.show')
                    ->withErrors(['billing' => $e->userMessage]);
            }

            try {
                $guidedTasks->paymentSetupCompleted($user);
            } catch (\Throwable $exception) {
                report($exception);
            }

            return redirect()
                ->route('family.billing.show')
                ->with('status', 'Billing method updated successfully.');
        }

        $billingUnavailable = false;
        try {
            $summary = $billing->summaryFor($user);
        } catch (PaymentException $exception) {
            report($exception);
            $billingUnavailable = true;
            $summary = [
                'ready' => false,
                'customer_id' => null,
                'card' => null,
            ];
        }

        return view('family.billing', [
            'billing' => $summary,
            'billingUnavailable' => $billingUnavailable,
            'publishableKey' => (string) config('services.stripe.publishable_key', ''),
        ]);
    }

    public function createCheckout(
        Request $request,
        FamilyBillingService $billing,
        AiSupportGuidedTaskService $guidedTasks,
    ): RedirectResponse {
        $user = auth()->user();
        abort_unless($user && $user->role === 'family', 403);

        $successUrl = route('family.billing.show').'?checkout=success&checkout_session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = route('family.billing.show').'?checkout=cancel';

        try {
            try {
                $guidedTasks->markPaymentSetupStarted($user);
            } catch (\Throwable $guidedException) {
                report($guidedException);
            }
            $url = $billing->createSetupCheckoutUrl($user, $successUrl, $cancelUrl);
        } catch (PaymentException $e) {
            try {
                $guidedTasks->paymentSetupFailed($user, 'secure_checkout_start_failed');
            } catch (\Throwable $guidedException) {
                report($guidedException);
            }

            return back()->withErrors(['billing' => $e->userMessage]);
        }

        return redirect()->away($url);
    }
}
