<div class="max-w-3xl mx-auto space-y-6">
    <div class="space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight">Caregiver registration</h1>
        <p class="text-sm text-slate-600">Create your account, then complete onboarding to submit your profile for review.</p>
    </div>

    <x-card>
        <form wire:submit="register" class="space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <x-input label="Full name" wire:model="name" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div class="md:col-span-2">
                    <x-input type="email" label="Email" wire:model="email" required />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <x-input label="Phone" wire:model="phone" required />
                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                </div>

                <div>
                    <x-input label="City" wire:model="city" required />
                    <x-input-error :messages="$errors->get('city')" class="mt-2" />
                </div>

                <div class="md:col-span-2">
                    <x-select.styled
                        label="State"
                        wire:model="state"
                        :options="collect($usStates)->map(fn($label,$value)=>['label'=>$label,'value'=>$value])->values()->all()"
                    />
                    <x-input-error :messages="$errors->get('state')" class="mt-2" />
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

            <div class="space-y-2 pt-1">
                <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model="accept_terms" class="mt-1 rounded border-slate-300 text-cyan-700 focus:ring-cyan-500">
                    <span>
                        I agree to the
                        <a href="{{ route('legal.show', ['slug' => 'platform-terms-of-service']) }}" class="underline hover:text-slate-900" target="_blank" rel="noopener noreferrer">Terms of Service</a>
                        and
                        <a href="{{ route('legal.show', ['slug' => 'privacy-policy']) }}" class="underline hover:text-slate-900" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.
                    </span>
                </label>
                <x-input-error :messages="$errors->get('accept_terms')" class="mt-1" />

                <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model="accept_independent_contractor" class="mt-1 rounded border-slate-300 text-cyan-700 focus:ring-cyan-500">
                    <span>
                        I understand I am an independent contractor, not an employee, and I acknowledge the
                        <a href="{{ route('legal.show', ['slug' => 'caregiver-terms']) }}" class="underline hover:text-slate-900" target="_blank" rel="noopener noreferrer">Caregiver Terms</a>
                        and
                        <a href="{{ route('legal.show', ['slug' => 'platform-participation-acknowledgment']) }}" class="underline hover:text-slate-900" target="_blank" rel="noopener noreferrer">Platform Participation Acknowledgment</a>.
                    </span>
                </label>
                <x-input-error :messages="$errors->get('accept_independent_contractor')" class="mt-1" />
            </div>

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('login') }}" wire:navigate class="text-sm text-slate-600 underline">
                    Already registered?
                </a>
                <x-button type="submit" color="blue">Create caregiver account</x-button>
            </div>
        </form>
    </x-card>
</div>
