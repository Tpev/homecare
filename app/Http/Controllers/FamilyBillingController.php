<?php

namespace App\Http\Controllers;

use App\Exceptions\Payments\PaymentException;
use App\Services\Payments\FamilyBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FamilyBillingController extends Controller
{
    public function show(Request $request, FamilyBillingService $billing): View|RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'family', 403);

        $sessionId = trim((string) $request->query('checkout_session_id', ''));
        if ($sessionId !== '') {
            try {
                $billing->syncSetupCheckoutSession($user, $sessionId);
            } catch (PaymentException $e) {
                return redirect()
                    ->route('family.billing.show')
                    ->withErrors(['billing' => $e->userMessage]);
            }

            return redirect()
                ->route('family.billing.show')
                ->with('status', 'Billing method updated successfully.');
        }

        return view('family.billing', [
            'billing' => $billing->summaryFor($user),
            'publishableKey' => (string) config('services.stripe.publishable_key', ''),
        ]);
    }

    public function createCheckout(Request $request, FamilyBillingService $billing): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'family', 403);

        $successUrl = route('family.billing.show').'?checkout=success&checkout_session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = route('family.billing.show').'?checkout=cancel';

        try {
            $url = $billing->createSetupCheckoutUrl($user, $successUrl, $cancelUrl);
        } catch (PaymentException $e) {
            return back()->withErrors(['billing' => $e->userMessage]);
        }

        return redirect()->away($url);
    }
}
