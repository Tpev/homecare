<?php

namespace App\Http\Controllers;

use App\Models\FamilyAccount;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FamilyAccountInvitationController extends Controller
{
    public function __invoke(Request $request, string $token, FamilyAccountInvitationService $invitations): RedirectResponse|View
    {
        $invitation = $invitations->findByToken($token);

        if (! $invitation || ! $invitation->isUsable()
            || $invitation->familyAccount?->status !== FamilyAccount::STATUS_ACTIVE) {
            $message = $invitation?->expires_at?->isPast() && ! $invitation->accepted_at && ! $invitation->canceled_at
                ? 'This invitation has expired. Ask the account owner to send a new one.'
                : 'This invitation is no longer available.';

            return view('family.invitation-unavailable', compact('message'));
        }

        if ($request->user()) {
            return redirect()->route('family.invitations.review', ['token' => $token]);
        }

        $reviewUrl = route('family.invitations.review', ['token' => $token]);
        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [$invitation->email_normalized])
            ->exists();

        if ($existingUser) {
            $request->session()->put('url.intended', $reviewUrl);

            return redirect()->route('login')->with('status', 'Sign in with the email address that received the invitation.');
        }

        return redirect()->route('family.invitations.register', ['token' => $token]);
    }
}
