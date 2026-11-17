<?php

namespace App\Livewire\Partner;

use App\Livewire\Forms\PartnerProfileForm;
use App\Models\PartnerProfile;
use App\Models\User;
use App\Support\PartnerOptions;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.guest')]
class Register extends Component
{
    public PartnerProfileForm $form;

    #[Locked]
    public int $step = 1;

    public string $password = '';

    public string $password_confirmation = '';

    public bool $accept_terms = false;

    public function boot(): void
    {
        abort_if(Auth::check(), 403);
    }

    public function nextStep(): void
    {
        if ($this->step >= 3) {
            return;
        }

        $this->form->validateStep($this->step);
        if ($this->step === 1) {
            $this->validateAvailableEmail();
        }

        $this->resetValidation();
        $this->step++;
        $this->dispatch('partner-step-changed');
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
        $this->resetValidation();
        $this->dispatch('partner-step-changed');
    }

    public function register(): void
    {
        $limiterKey = 'partner-register:'.request()->ip();
        if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
            throw ValidationException::withMessages([
                'registration' => 'Please wait a minute before trying again.',
            ]);
        }
        RateLimiter::hit($limiterKey, 60);

        // Always validate the complete submission, even if a client skips a step.
        $profileData = $this->form->normalizedData();
        $this->validateAvailableEmail();
        $this->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'accept_terms' => ['accepted'],
        ]);

        try {
            $user = DB::transaction(function () use ($profileData) {
                $user = User::create([
                    'name' => $profileData['contact_name'],
                    'email' => $profileData['contact_email'],
                    'phone' => $profileData['contact_phone'],
                    'password' => Hash::make($this->password),
                    'role' => 'partner',
                    'onboarding_completed_at' => now(),
                ]);

                PartnerProfile::create($profileData + [
                    'user_id' => $user->id,
                    'status' => PartnerProfile::STATUS_NEW,
                    'submitted_at' => now(),
                    'terms_accepted_at' => now(),
                ]);

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $this->validateAvailableEmail();
            throw $exception;
        }

        Auth::login($user);
        Session::regenerate();
        $this->password = '';
        $this->password_confirmation = '';

        session()->flash('partner-registered', true);
        $this->redirect(route('partner.dashboard', absolute: false), navigate: true);
    }

    private function validateAvailableEmail(): void
    {
        if (User::query()->whereRaw('LOWER(email) = ?', [strtolower(trim($this->form->contact_email))])->exists()) {
            throw ValidationException::withMessages([
                'form.contact_email' => 'An account already uses this email. Sign in or use another business email.',
            ]);
        }
    }

    public function render()
    {
        return view('livewire.partner.register', [
            'categories' => PartnerOptions::categories(),
            'states' => PartnerOptions::states(),
        ]);
    }
}
