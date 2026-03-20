<?php

namespace App\Http\Controllers;

use App\Exceptions\Payments\PaymentException;
use App\Services\Payments\CaregiverStripeConnectService;
use App\Support\CaregiverOnboardingState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CaregiverStripeConnectController extends Controller
{
    public function show(Request $request, CaregiverStripeConnectService $connect): View|RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'caregiver', 403);
        $onboardingState = app(CaregiverOnboardingState::class);
        $onboardingState->trackStepViewed($user, CaregiverOnboardingState::STEP_PAYOUT);

        if ($request->boolean('sync', false)) {
            try {
                $connect->syncStatus($user);
            } catch (PaymentException $e) {
                return redirect()
                    ->route('caregiver.payouts.connect.show')
                    ->withErrors(['payouts' => $e->userMessage]);
            }

            return redirect()
                ->route('caregiver.payouts.connect.show')
                ->with('status', 'Payout status refreshed.');
        }

        $profile = $connect->profileFor($user);
        if ($profile->stripeConnectIsReady()) {
            $onboardingState->trackStepCompleted($user, CaregiverOnboardingState::STEP_PAYOUT);
        }

        return view('caregiver.payout-connect', [
            'profile' => $profile,
        ]);
    }

    public function start(CaregiverStripeConnectService $connect): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'caregiver', 403);

        $refreshUrl = route('caregiver.payouts.connect.show', ['sync' => 1]);
        $returnUrl = route('caregiver.payouts.connect.return');

        try {
            $url = $connect->createOnboardingUrl($user, $refreshUrl, $returnUrl);
        } catch (PaymentException $e) {
            return back()->withErrors(['payouts' => $e->userMessage]);
        }

        return redirect()->away($url);
    }

    public function returned(CaregiverStripeConnectService $connect): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'caregiver', 403);

        try {
            $connect->syncStatus($user);
        } catch (PaymentException $e) {
            return redirect()
                ->route('caregiver.payouts.connect.show')
                ->withErrors(['payouts' => $e->userMessage]);
        }

        return redirect()
            ->route('caregiver.payouts.connect.show')
            ->with('status', 'Stripe Connect onboarding refreshed.');
    }
}
