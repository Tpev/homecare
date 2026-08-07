<?php

namespace App\Livewire\Family;

use App\Services\FamilyAccounts\FamilyAccountInvitationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class AcceptFamilyInvitation extends Component
{
    public string $token;

    public string $ownerFirstName = '';

    public bool $emailMatches = false;

    public function mount(string $token, FamilyAccountInvitationService $invitations): void
    {
        $invitation = $invitations->requireUsableToken($token);
        $this->token = $token;
        $this->emailMatches = Str::lower(trim((string) auth()->user()?->email)) === $invitation->email_normalized;

        if ($this->emailMatches) {
            $this->ownerFirstName = Str::of((string) $invitation->familyAccount?->owner?->name)->trim()->before(' ')->value() ?: 'your family';
        }
    }

    public function join(FamilyAccountInvitationService $invitations): void
    {
        $membership = $invitations->accept(auth()->user(), $this->token);
        $ownerName = $membership->familyAccount->owner->name;

        session()->flash('status', 'You can now help manage care with '.$ownerName.'.');
        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }

    public function notNow(): void
    {
        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }

    public function signInWithAnotherAccount(): void
    {
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        request()->session()->put('url.intended', route('family.invitations.review', ['token' => $this->token]));
        $this->redirect(route('login', absolute: false), navigate: true);
    }

    public function render()
    {
        return view('livewire.family.accept-family-invitation');
    }
}
