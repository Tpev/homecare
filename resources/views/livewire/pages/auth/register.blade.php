<?php

use App\Models\User;
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
    public string $password = '';
    public string $password_confirmation = '';

    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="space-y-6">
    <div class="space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight">Create your account</h1>
        <p class="text-sm text-slate-600">Start as a family account or continue as a caregiver.</p>
    </div>

    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
        Registering as caregiver?
        <a href="{{ route('caregiver.register') }}" class="font-medium underline" wire:navigate>Use caregiver onboarding</a>.
    </div>

    <form wire:submit="register" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <x-input label="Full name" wire:model="name" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <div class="md:col-span-2">
                <x-input type="email" label="Email" wire:model="email" required />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
            <div>
                <x-password label="Password" wire:model="password" required />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>
            <div>
                <x-password label="Confirm password" wire:model="password_confirmation" required />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <div class="flex items-center justify-between pt-2">
            <a class="text-sm text-slate-600 underline" href="{{ route('login') }}" wire:navigate>
                {{ __('Already registered?') }}
            </a>
            <x-button type="submit" color="blue">{{ __('Create account') }}</x-button>
        </div>
    </form>
</div>
