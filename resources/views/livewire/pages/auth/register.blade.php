<?php

use App\Models\User;
use App\Support\FamilyQuickRequestDraft;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $accept_terms = false;

    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'min:7', 'max:30'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'accept_terms' => ['accepted'],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        unset($validated['accept_terms']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);
        session()->flash('google_ads_family_signup_conversion', true);

        if (FamilyQuickRequestDraft::has()) {
            session()->flash('status', 'Your request draft is waiting. Review it and publish from your new account.');
            $this->redirect(route('family.requests.create', absolute: false), navigate: true);

            return;
        }

        $this->redirect(route('family.requests.index', absolute: false), navigate: true);
    }
}; ?>

<div class="space-y-7">
    <div class="space-y-2">
        <p class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-[#B95745]">Family account</p>
        <h1 class="font-semibold tracking-tight">Create your family account</h1>
        <p class="text-sm leading-6 text-[#68756F]">Find care, coordinate visits, and keep your family informed from one place.</p>
    </div>

    <div class="auth-role-switch">
        <span aria-hidden="true">♥</span>
        <div>
            <strong class="block text-[#173F35]">Are you offering care?</strong>
            <a href="{{ route('caregiver.register') }}" class="underline" wire:navigate>Register as a caregiver →</a>
        </div>
    </div>

    @if (\App\Support\FamilyQuickRequestDraft::has())
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            Your quick care request is saved. Create your account now and we’ll take you straight to the final review before publishing.
        </div>
    @endif

    <form wire:submit="register" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <p class="text-sm font-bold text-[#173F35]">Your details</p>
                <p class="mt-1 text-xs text-[#77817C]">Use the contact information you want associated with care requests.</p>
            </div>
            <div class="md:col-span-2">
                <x-input label="Full name" wire:model="name" autocomplete="name" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <div class="md:col-span-2">
                <x-input type="email" label="Email" wire:model="email" autocomplete="email" required />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
            <div class="md:col-span-2">
                <x-input type="tel" label="Phone number" wire:model="phone" autocomplete="tel" required />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>
            <div class="md:col-span-2 mt-1 border-t border-[#E8DED1] pt-5">
                <p class="text-sm font-bold text-[#173F35]">Secure your account</p>
                <p class="mt-1 text-xs text-[#77817C]">Use at least 8 characters and keep your password private.</p>
            </div>
            <div>
                <x-password label="Password" wire:model="password" autocomplete="new-password" required />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>
            <div>
                <x-password label="Confirm password" wire:model="password_confirmation" autocomplete="new-password" required />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <div class="auth-consent space-y-1">
            <label class="flex cursor-pointer items-start gap-3 text-sm leading-6 text-[#56655F]">
                <input wire:model="accept_terms" id="accept_terms" type="checkbox" class="mt-1">
                <span>
                    I agree to the
                    <a href="{{ route('legal.show', ['slug' => 'platform-terms-of-service']) }}" class="underline hover:text-slate-900" target="_blank" rel="noopener noreferrer">Terms of Service</a>,
                    <a href="{{ route('legal.show', ['slug' => 'client-and-family-terms']) }}" class="underline hover:text-slate-900" target="_blank" rel="noopener noreferrer">Client & Family Terms</a>,
                    and
                    <a href="{{ route('legal.show', ['slug' => 'privacy-policy']) }}" class="underline hover:text-slate-900" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.
                </span>
            </label>
            <x-input-error :messages="$errors->get('accept_terms')" class="mt-2" />
        </div>

        <div class="grid grid-cols-1 gap-4 pt-1 sm:flex sm:items-center sm:justify-between">
            <a class="text-sm text-slate-600 underline" href="{{ route('login') }}" wire:navigate>
                {{ __('Already registered?') }}
            </a>
            <x-button type="submit" color="blue" class="auth-primary w-full justify-center sm:w-auto">{{ __('Create family account') }}</x-button>
        </div>
    </form>
</div>
