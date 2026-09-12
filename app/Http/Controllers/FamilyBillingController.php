<?php

namespace App\Http\Controllers;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareRequest;
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
        $hireContext = $this->hireReturnContext($request);
        $returnUrl = $this->returnUrl($hireContext);

        if ($request->query('checkout') === 'cancel') {
            try {
                $guidedTasks->paymentSetupCancelled($user);
            } catch (\Throwable $exception) {
                report($exception);
            }

            return redirect()
                ->to($returnUrl)
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
                    ->to($returnUrl)
                    ->withErrors(['billing' => $e->userMessage]);
            }

            try {
                $guidedTasks->paymentSetupCompleted($user);
            } catch (\Throwable $exception) {
                report($exception);
            }

            return redirect()
                ->to($returnUrl)
                ->with('status', $hireContext !== []
                    ? 'Payment method saved. Review the details below to confirm your hire.'
                    : 'Billing method updated successfully.');
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

        $hireContext = $this->hireReturnContext($request);
        $successUrl = route('family.billing.show', [...$hireContext, 'checkout' => 'success']).'&checkout_session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = route('family.billing.show', [...$hireContext, 'checkout' => 'cancel']);

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

            return redirect()->to($this->returnUrl($hireContext))->withErrors(['billing' => $e->userMessage]);
        }

        return redirect()->away($url);
    }

    /** Only accept an authorized request/application pair, never an arbitrary return URL. */
    private function hireReturnContext(Request $request): array
    {
        if (! $request->hasAny(['care_request_id', 'application_id'])) {
            return [];
        }

        $context = $request->validate([
            'care_request_id' => ['required', 'integer', 'min:1'],
            'application_id' => ['required', 'integer', 'min:1'],
        ]);
        $careRequest = CareRequest::query()->findOrFail($context['care_request_id']);
        abort_unless($request->user()->can('manageApplicants', $careRequest), 403);
        $application = $careRequest->applications()->findOrFail($context['application_id']);

        return ['care_request_id' => $careRequest->id, 'application_id' => $application->id];
    }

    private function returnUrl(array $hireContext): string
    {
        return $hireContext === [] ? route('family.billing.show') : route('family.requests.show', [
            'careRequest' => $hireContext['care_request_id'],
            'tab' => 'applicants',
            'review_hire' => $hireContext['application_id'],
        ]);
    }
}
