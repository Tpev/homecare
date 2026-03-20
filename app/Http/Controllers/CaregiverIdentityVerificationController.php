<?php

namespace App\Http\Controllers;

use App\Models\CaregiverProfile;
use App\Services\Didit\DiditSessionService;
use App\Support\CaregiverOnboardingState;
use App\Support\FunnelTracker;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class CaregiverIdentityVerificationController extends Controller
{
    public function show(): View
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'caregiver', 403);

        $profile = CaregiverProfile::query()
            ->with('latestIdentityVerification')
            ->firstOrCreate(['user_id' => $user->id], ['status' => 'draft']);

        $recentAttempts = $profile->identityVerifications()
            ->latest('id')
            ->limit(5)
            ->get();

        $onboardingState = app(CaregiverOnboardingState::class);
        $onboarding = $onboardingState->build($user);
        $onboardingState->trackStepViewed($user, CaregiverOnboardingState::STEP_IDENTITY);

        return view('caregiver.identity-verification', [
            'profile' => $profile,
            'latestAttempt' => $profile->latestIdentityVerification,
            'recentAttempts' => $recentAttempts,
            'onboarding' => $onboarding,
        ]);
    }

    public function store(Request $request, DiditSessionService $service): RedirectResponse|JsonResponse
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'caregiver', 403);

        $preflightMessage = $this->preflightValidationMessage();
        if ($preflightMessage) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $preflightMessage], 422);
            }

            return back()->withErrors(['verification' => $preflightMessage]);
        }

        try {
            $verification = $service->createForCaregiver($user);
        } catch (Throwable $exception) {
            report($exception);
            Log::error('Didit session creation failed', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);
            app(CaregiverOnboardingState::class)->trackStepError($user, CaregiverOnboardingState::STEP_IDENTITY, [
                'verification' => [$exception->getMessage()],
            ]);

            $errorMessage = $this->formatUserError($exception);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $errorMessage,
                ], 422);
            }

            return back()->withErrors([
                'verification' => $errorMessage,
            ]);
        }

        FunnelTracker::track('caregiver_onboarding_identity_started', $user, $verification->caregiverProfile, [
            'didit_session_id' => $verification->didit_session_id,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'verification_url' => $verification->verification_url,
                'session_id' => $verification->didit_session_id,
                'status' => $verification->status,
            ]);
        }

        return redirect()->away((string) $verification->verification_url);
    }

    public function returned(): RedirectResponse
    {
        return redirect()
            ->route('caregiver.setup.index')
            ->with('status', 'Verification submitted. We will update your status shortly.');
    }

    private function preflightValidationMessage(): ?string
    {
        if ((bool) config('services.didit.bypass', false)) {
            return null;
        }

        if ((string) config('services.didit.api_key') === '') {
            return 'Didit is not configured: missing DIDIT_API_KEY.';
        }

        if ((string) config('services.didit.workflow_id') === '') {
            return 'Didit is not configured: missing DIDIT_WORKFLOW_ID.';
        }

        return null;
    }

    private function formatUserError(Throwable $exception): string
    {
        $base = 'Unable to start identity verification right now.';

        if ($exception instanceof RequestException) {
            $status = $exception->response?->status();
            $body = (string) $exception->response?->body();
            $hint = match ($status) {
                401 => 'Didit rejected API credentials. Check DIDIT_API_KEY.',
                403 => 'Didit access forbidden for this key/workflow.',
                404 => 'Didit endpoint or workflow not found.',
                422 => 'Didit rejected session payload (commonly callback URL or workflow mismatch).',
                default => 'Didit request failed.',
            };

            if (app()->isLocal()) {
                $safeBody = mb_substr($body, 0, 400);

                return $base.' '.$hint.' Response: '.$safeBody;
            }

            return $base.' '.$hint;
        }

        if (app()->isLocal()) {
            return $base.' '.$exception->getMessage();
        }

        return $base.' Please try again.';
    }
}
