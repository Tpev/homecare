<?php

use App\Support\FamilyQuickRequestDraft;
use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        if (FamilyQuickRequestDraft::has() && auth()->user()?->role === 'family') {
            session()->flash('status', 'Your quick request draft is ready. Finish review and publish it now.');
            $this->redirect(route('family.requests.create', absolute: false), navigate: true);
            return;
        }

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="space-y-7">
    <div class="space-y-2">
        <p class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-[#B95745]">Your LoLo account</p>
        <h1 class="font-semibold tracking-tight">Welcome back</h1>
        <p class="text-sm leading-6 text-[#68756F]">Sign in with the email and password you used to create your account.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if (\App\Support\FamilyQuickRequestDraft::has())
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            Your quick care request is saved. Sign in and we’ll open the final review so you can publish it.
        </div>
    @endif

    <form wire:submit="login" class="space-y-5">
        <div class="grid grid-cols-1 gap-5">
            <x-input wire:model="form.email" id="email" type="email" name="email" label="Email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
            <x-password wire:model="form.password" id="password" name="password" label="Password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label for="remember" class="inline-flex min-h-10 cursor-pointer items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" name="remember">
                <span class="ms-2 text-sm font-medium text-[#586861]">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="rounded-md text-sm underline" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <div class="pt-1">
            <x-button type="submit" color="blue" class="auth-primary w-full justify-center">{{ __('Log in') }}</x-button>
        </div>
    </form>
</div>
