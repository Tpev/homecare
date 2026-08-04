<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div class="space-y-7">
    <div class="space-y-2">
        <p class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-[#B95745]">Account recovery</p>
        <h1 class="font-semibold tracking-tight">Reset your password</h1>
        <p class="text-sm leading-6 text-[#68756F]">Enter the email associated with your LoLo account. We’ll send you a secure link to choose a new password.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="space-y-5">
        <div>
            <x-input wire:model="email" id="email" type="email" name="email" label="Email" required autofocus autocomplete="email" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="pt-1">
            <x-button color="blue" class="auth-primary w-full justify-center">
                {{ __('Send reset link') }}
            </x-button>
        </div>
    </form>

    <a href="{{ route('login') }}" wire:navigate class="inline-flex text-sm underline">← Back to sign in</a>
</div>
