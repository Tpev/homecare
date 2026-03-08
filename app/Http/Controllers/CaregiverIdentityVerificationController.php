<?php

namespace App\Http\Controllers;

use App\Models\CaregiverProfile;
use App\Services\Didit\DiditSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('caregiver.identity-verification', [
            'profile' => $profile,
            'latestAttempt' => $profile->latestIdentityVerification,
            'recentAttempts' => $recentAttempts,
        ]);
    }

    public function store(Request $request, DiditSessionService $service): RedirectResponse|JsonResponse
    {
        $user = auth()->user();
        abort_unless($user && $user->role === 'caregiver', 403);

        try {
            $verification = $service->createForCaregiver($user);
        } catch (Throwable $exception) {
            report($exception);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unable to start identity verification right now.',
                ], 422);
            }

            return back()->withErrors([
                'verification' => 'Unable to start identity verification right now. Please try again.',
            ]);
        }

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
            ->route('caregiver.verification.show')
            ->with('status', 'Verification submitted. We will update your status shortly.');
    }
}

