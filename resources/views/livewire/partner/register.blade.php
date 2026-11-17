<div class="space-y-7" x-data x-on:partner-step-changed.window="$nextTick(() => { $refs.stepHeading.focus(); $refs.stepHeading.scrollIntoView({ block: 'nearest' }); })">
    <div class="space-y-3">
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#B95745]">LoLo partner network</p>
        <h1 x-ref="stepHeading" tabindex="-1" class="font-semibold tracking-tight focus:outline-none">{{ ['Tell us about your business', 'Your services and coverage', 'Ready to join LoLo?'][$step - 1] }}</h1>
        <p class="text-sm leading-6 text-[#68756F]">{{ ['Let’s get to know your team and how to reach you.', 'Help us understand where your business can make a difference.', 'Review your details and create your partner account.'][$step - 1] }}</p>
    </div>

    <ol class="grid grid-cols-3 gap-2" aria-label="Registration progress">
        @foreach ([1 => 'Business', 2 => 'Services & area', 3 => 'Review & join'] as $number => $label)
            <li @if($step === $number) aria-current="step" @endif class="border-t-[3px] pt-3 {{ $step >= $number ? 'border-[#315B4D]' : 'border-[#E8DED1]' }}">
                <span class="block text-[11px] font-semibold uppercase tracking-wide {{ $step >= $number ? 'text-[#315B4D]' : 'text-[#77817C]' }}">Step {{ $number }}</span>
                <span class="mt-1 block text-xs font-semibold sm:text-sm {{ $step >= $number ? 'text-[#173F35]' : 'text-[#77817C]' }}">{{ $label }}</span>
            </li>
        @endforeach
    </ol>

    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Please check your details.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            @if ($step === 3)
                <p class="mt-2">Use Back to update your business, services or coverage details.</p>
            @endif
        </div>
    @endif

    <form wire:submit="{{ $step < 3 ? 'nextStep' : 'register' }}" class="space-y-6">
        @if ($step === 1)
            <x-partner-profile-fields section="business" :registration="true" />
        @elseif ($step === 2)
            <x-partner-profile-fields section="services" :categories="$categories" :states="$states" :selected-states="$form->coverage_states" :coverage-type="$form->coverage_type" />
        @else
            <div class="rounded-2xl border border-[#DCE5DC] bg-[#F1F5EF] p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#66746D]">Your partner profile</p>
                        <h2 class="mt-2 break-words text-xl font-semibold">{{ $form->business_name }}</h2>
                        <p class="mt-1 break-words text-sm text-[#52665B]">{{ $form->contact_name }} · {{ $form->contact_email }}</p>
                        <p class="mt-1 text-sm text-[#52665B]">{{ $form->contact_phone }}</p>
                    </div>
                    <button type="button" wire:click="previousStep" class="shrink-0 text-sm font-semibold text-[#315B4D] underline underline-offset-4">Edit details</button>
                </div>
                @if ($form->website)
                    <p class="mt-3 break-all text-sm text-[#52665B]">{{ $form->website }}</p>
                @endif
                @if ($form->description)
                    <p class="mt-3 whitespace-pre-line break-words text-sm leading-6 text-[#52665B]">{{ $form->description }}</p>
                @endif
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($form->categories as $category)
                        <span class="rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-[#23483F]">{{ $categories[$category] ?? $category }}</span>
                    @endforeach
                </div>
                <div class="mt-4 border-t border-[#DCE5DC] pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#66746D]">Service area</p>
                    <p class="mt-1 text-sm font-semibold text-[#23483F]">{{ collect($form->coverage_states)->map(fn ($code) => $states[$code] ?? $code)->implode(', ') }}</p>
                    <p class="mt-1 whitespace-pre-line break-words text-sm leading-6 text-[#52665B]">{{ $form->coverage_type === 'entire_states' ? 'All areas in the selected states' : $form->coverage_areas }}</p>
                </div>
                @if ($form->customer_notes)
                    <div class="mt-4 border-t border-[#DCE5DC] pt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#66746D]">Additional details</p>
                        <p class="mt-1 whitespace-pre-line break-words text-sm leading-6 text-[#52665B]">{{ $form->customer_notes }}</p>
                    </div>
                @endif
            </div>

            <div>
                <h2 class="text-lg font-semibold">Secure your account</h2>
                <p class="mt-1 break-words text-sm leading-6 text-[#66746D]">Your sign-in email is <strong>{{ $form->contact_email }}</strong>.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-password label="Password" wire:model="password" autocomplete="new-password" required />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>
                    <div>
                        <x-password label="Confirm password" wire:model="password_confirmation" autocomplete="new-password" required />
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                    </div>
                </div>
                <p class="mt-2 text-xs text-[#66746D]">Use at least 8 characters.</p>
            </div>

            <div class="auth-consent">
                <label class="flex cursor-pointer items-start gap-3 text-sm leading-6 text-[#56655F]">
                    <input type="checkbox" wire:model="accept_terms" class="mt-1" required>
                    <span>I agree to the <a href="{{ route('legal.show', ['slug' => 'platform-terms-of-service']) }}" target="_blank" rel="noopener noreferrer" class="underline">Terms of Service</a> and <a href="{{ route('legal.show', ['slug' => 'privacy-policy']) }}" target="_blank" rel="noopener noreferrer" class="underline">Privacy Policy</a>.</span>
                </label>
                <x-input-error :messages="$errors->get('accept_terms')" class="mt-2" />
            </div>
        @endif

        <div class="flex flex-col-reverse gap-4 border-t border-[#E8DED1] pt-5 sm:flex-row sm:items-center sm:justify-between">
            @if ($step === 1)
                <a href="{{ route('partner.login') }}" wire:navigate class="text-center text-sm underline sm:text-left">Already registered? Sign in</a>
            @else
                <button type="button" wire:click="previousStep" wire:loading.attr="disabled" class="min-h-12 rounded-xl px-3 text-sm font-semibold text-[#52665B] underline underline-offset-4 disabled:opacity-50">Back</button>
            @endif
            <button type="submit" wire:loading.attr="disabled" class="auth-primary min-h-12 w-full rounded-xl px-6 text-sm disabled:cursor-wait disabled:opacity-60 sm:w-auto">
                <span wire:loading.remove wire:target="nextStep,register">{{ $step < 3 ? 'Continue' : 'Create partner account' }}</span>
                <span wire:loading wire:target="nextStep,register">{{ $step < 3 ? 'Checking details…' : 'Creating your account…' }}</span>
            </button>
        </div>
    </form>
</div>
