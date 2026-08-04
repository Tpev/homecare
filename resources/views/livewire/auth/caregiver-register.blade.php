<div class="space-y-7">
    <div class="space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#B95745]">Caregiver account</p>
            <span class="rounded-full bg-[#EDF3ED] px-3 py-1 text-xs font-bold text-[#31584D]">Account setup · About 2 minutes</span>
        </div>
        <h1 class="font-semibold tracking-tight">Create your caregiver account</h1>
        <p class="text-sm leading-6 text-[#68756F]">Start with your account details, then build the profile families will review.</p>
    </div>

    <form wire:submit="register" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <p class="text-sm font-bold text-[#173F35]">Your details</p>
                    <p class="mt-1 text-xs text-[#77817C]">Tell us where you are based so we can set up your caregiver workspace.</p>
                </div>
                <div class="md:col-span-2">
                    <x-input label="Full name" wire:model="name" autocomplete="name" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div class="md:col-span-2">
                    <x-input type="email" label="Email" wire:model="email" autocomplete="email" required />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <x-input type="tel" label="Phone" wire:model="phone" autocomplete="tel" required />
                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                </div>

                <div>
                    <x-input label="City" wire:model="city" autocomplete="address-level2" required />
                    <x-input-error :messages="$errors->get('city')" class="mt-2" />
                </div>

                <div class="md:col-span-2">
                    <x-native-select-field
                        label="State"
                        wire:model="state"
                        :options="collect($usStates)->map(fn($label,$value)=>['label'=>$label,'value'=>$value])->values()->all()"
                    />
                    <x-input-error :messages="$errors->get('state')" class="mt-2" />
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

            <div class="auth-consent space-y-3">
                <label class="flex cursor-pointer items-start gap-3 text-sm leading-6 text-[#56655F]">
                    <input type="checkbox" wire:model="accept_terms" class="mt-1">
                    <span>
                        I agree to the
                        <a href="{{ route('legal.show', ['slug' => 'platform-terms-of-service']) }}" class="underline hover:text-slate-900" target="_blank" rel="noopener noreferrer">Terms of Service</a>
                        and
                        <a href="{{ route('legal.show', ['slug' => 'privacy-policy']) }}" class="underline hover:text-slate-900" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.
                    </span>
                </label>
                <x-input-error :messages="$errors->get('accept_terms')" class="mt-1" />

                <label class="flex cursor-pointer items-start gap-3 text-sm leading-6 text-[#56655F]">
                    <input type="checkbox" wire:model="accept_independent_contractor" class="mt-1">
                    <span>
                        I understand I am an independent contractor, not an employee, and I acknowledge the
                        <a href="{{ route('legal.show', ['slug' => 'caregiver-terms']) }}" class="underline hover:text-slate-900" target="_blank" rel="noopener noreferrer">Caregiver Terms</a>
                        and
                        <a href="{{ route('legal.show', ['slug' => 'platform-participation-acknowledgment']) }}" class="underline hover:text-slate-900" target="_blank" rel="noopener noreferrer">Platform Participation Acknowledgment</a>.
                    </span>
                </label>
                <x-input-error :messages="$errors->get('accept_independent_contractor')" class="mt-1" />
            </div>

            <div class="grid grid-cols-1 gap-4 pt-1 sm:flex sm:items-center sm:justify-between">
                <a href="{{ route('login') }}" wire:navigate class="text-sm text-slate-600 underline">
                    Already registered?
                </a>
                <x-button type="submit" color="blue" class="auth-primary w-full justify-center sm:w-auto">Create caregiver account</x-button>
            </div>
    </form>
</div>
