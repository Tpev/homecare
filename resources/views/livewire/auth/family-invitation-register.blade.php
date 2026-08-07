<div class="space-y-7">
    <div class="space-y-2">
        <p class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-[#B95745]">Family access</p>
        <h1 class="font-semibold tracking-tight">Create your login</h1>
        <p class="text-sm leading-6 text-[#68756F]">Use your own password. You will not need to share anyone else's login.</p>
    </div>

    <form wire:submit="register" class="space-y-6">
        <div class="space-y-5">
            <div>
                <x-input label="Name" wire:model="name" autocomplete="name" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <label class="mb-1 block text-sm font-semibold text-[#173F35]" for="invitation-email">Email</label>
                <input id="invitation-email" type="email" value="{{ $email }}" readonly aria-readonly="true" class="min-h-11 w-full rounded-xl border-[#D7CEC2] bg-[#F5F2ED] px-4 text-base text-[#526474]" />
                <p class="mt-1 text-xs text-[#68756F]">This invitation is private and belongs to this email address.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-password label="Password" wire:model="password" autocomplete="new-password" required />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
                <div>
                    <x-password label="Confirm password" wire:model="password_confirmation" autocomplete="new-password" required />
                </div>
            </div>
        </div>

        <label class="flex min-h-11 cursor-pointer items-start gap-3 text-sm leading-6 text-[#56655F]">
            <input wire:model="accept_terms" id="accept_terms" type="checkbox" class="mt-1 rounded border-[#B9AFA2] text-[#173F35] focus:ring-[#173F35]">
            <span>
                I agree to the
                <a href="{{ route('legal.show', ['slug' => 'platform-terms-of-service']) }}" class="underline" target="_blank" rel="noopener noreferrer">Terms of Service</a>,
                <a href="{{ route('legal.show', ['slug' => 'client-and-family-terms']) }}" class="underline" target="_blank" rel="noopener noreferrer">Client &amp; Family Terms</a>, and
                <a href="{{ route('legal.show', ['slug' => 'privacy-policy']) }}" class="underline" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.
            </span>
        </label>
        <x-input-error :messages="$errors->get('accept_terms')" class="mt-2" />

        <x-button type="submit" color="blue" class="auth-primary min-h-11 w-full justify-center">Create my login</x-button>
    </form>
</div>
