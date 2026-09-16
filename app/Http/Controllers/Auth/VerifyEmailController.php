<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $destination = $request->user()->role === 'family'
            ? route('family.requests.index', absolute: false)
            : route('dashboard', absolute: false);

        if (app(\App\Services\Family\FamilyOnboardingService::class)->pending($request->user())) {
            $destination = route('family.onboarding', absolute: false);
            $intendedPath = (string) parse_url((string) $request->session()->get('url.intended', ''), PHP_URL_PATH);
            if (! str_starts_with($intendedPath, '/family/invitations/')) {
                $request->session()->forget('url.intended');
            }
        }

        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended($destination.'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended($destination.'?verified=1');
    }
}
