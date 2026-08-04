<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="space-y-5">
    <div class="space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight">Confirm your password</h1>
        <div class="text-sm text-slate-600">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
        </div>
    </div>

    <form wire:submit="confirmPassword" class="space-y-4">
        <div>
            <x-password wire:model="password" id="password" name="password" label="Password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="pt-2">
            <x-button color="blue" class="auth-primary w-full justify-center sm:w-auto">
                {{ __('Confirm') }}
            </x-button>
        </div>
    </form>
</div>
