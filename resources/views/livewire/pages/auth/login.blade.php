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

<div class="space-y-6">
    <div class="space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight">Welcome back</h1>
        <p class="text-sm text-slate-600">Sign in to continue to your LoLo dashboard.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if (\App\Support\FamilyQuickRequestDraft::has())
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            Your quick care request is saved. Sign in and we’ll open the final review so you can publish it.
        </div>
    @endif

    <form wire:submit="login" class="space-y-4">
        <div class="grid grid-cols-1 gap-4">
            <x-input wire:model="form.email" id="email" type="email" name="email" label="Email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
            <x-password wire:model="form.password" id="password" name="password" label="Password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <div class="block">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="grid grid-cols-1 gap-3 pt-2 sm:flex sm:items-center sm:justify-between">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-slate-600 hover:text-slate-900 rounded-md" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-button type="submit" color="blue" class="w-full justify-center sm:w-auto">{{ __('Log in') }}</x-button>
        </div>
    </form>
</div>
