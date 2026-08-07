<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountInvitationService;
use App\Services\FamilyAccounts\FamilyAccountProvisioner;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class FamilyInvitationRegister extends Component
{
    public string $token;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $accept_terms = false;

    public function mount(string $token, FamilyAccountInvitationService $invitations): void
    {
        $invitation = $invitations->requireUsableToken($token);
        $this->token = $token;
        $this->email = $invitation->email_normalized;
    }

    public function register(FamilyAccountInvitationService $invitations, FamilyAccountProvisioner $provisioner): void
    {
        $invitation = $invitations->requireUsableToken($this->token);
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'accept_terms' => ['accepted'],
        ]);

        $user = DB::transaction(function () use ($validated, $invitation, $provisioner): User {
            $user = User::query()->create([
                'name' => trim($validated['name']),
                'email' => $invitation->email_normalized,
                'password' => Hash::make($validated['password']),
                'role' => 'family',
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $provisioner->provisionOwner($user, 'invitation_login_created');

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);
        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        $this->redirect(route('family.invitations.review', ['token' => $this->token], absolute: false), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.family-invitation-register');
    }
}
