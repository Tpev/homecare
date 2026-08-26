<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $default = Auth::user()->role === 'family'
                ? route('family.requests.index', absolute: false)
                : route('dashboard', absolute: false);
            $this->redirectIntended(default: $default, navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="space-y-5">
    <div class="space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight">Verify your email</h1>
        <div class="text-sm text-slate-600">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
        </div>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-3 pt-1 sm:flex sm:items-center sm:justify-between">
        <x-button color="blue" class="auth-primary w-full justify-center sm:w-auto" wire:click="sendVerification">
            {{ __('Resend Verification Email') }}
        </x-button>

        <button wire:click="logout" type="submit" class="rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-600 transition hover:border-slate-300 hover:text-slate-900">
            {{ __('Log Out') }}
        </button>
    </div>
</div>
