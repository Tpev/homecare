<div class="lolo-wizard-card" aria-live="polite">
    @if ($submitted)
        <div class="lolo-wizard-success">
            <p class="lolo-wizard-eyebrow">Request received</p>
            <h2>Thanks. A LoLo coordinator will call you back.</h2>
            <p>We saved your care preferences and contact details, so the callback can start with the right context.</p>
            <a href="{{ route('landing') }}" class="lolo-wizard-secondary">Back to LoLo</a>
        </div>
    @else
        <div class="lolo-wizard-top">
            <div>
                <p class="lolo-wizard-step-label">Step {{ $step }} of {{ $contactStep }}</p>
                <div class="lolo-wizard-progress" aria-hidden="true">
                    <span style="width: {{ $progressPercent }}%"></span>
                </div>
            </div>
            <p class="lolo-wizard-time">Takes about 2 minutes</p>
        </div>

        @if ($currentQuestion)
            <section class="lolo-wizard-step" wire:key="wizard-question-{{ $step }}">
                <p class="lolo-wizard-eyebrow">{{ $currentQuestion['eyebrow'] }}</p>
                <h2>{{ $currentQuestion['title'] }}</h2>
                <p class="lolo-wizard-intro">{{ $currentQuestion['body'] }}</p>

                <div class="lolo-wizard-options">
                    @foreach ($currentQuestion['options'] as $option)
                        @php($isSelected = $this->{$currentQuestion['field']} === $option['value'])
                        <button
                            type="button"
                            class="lolo-wizard-option {{ $isSelected ? 'is-selected' : '' }}"
                            wire:click="choose(@js($currentQuestion['field']), @js($option['value']))"
                        >
                            <span class="lolo-wizard-option-title">{{ $option['title'] }}</span>
                            <span class="lolo-wizard-option-body">{{ $option['body'] }}</span>
                        </button>
                    @endforeach
                </div>

                @error($currentQuestion['field'])
                    <small class="lolo-wizard-error">{{ $message }}</small>
                @enderror
            </section>
        @else
            <form wire:submit="submit" class="lolo-wizard-contact" wire:key="wizard-contact">
                <div>
                    <p class="lolo-wizard-eyebrow">Final step</p>
                    <h2>Where should we call you?</h2>
                    <p class="lolo-wizard-intro">Add your contact details and anything useful for the first conversation.</p>
                </div>

                <div class="lolo-wizard-field-grid">
                    <label>
                        <span>Your name</span>
                        <input type="text" wire:model.blur="full_name" autocomplete="name" placeholder="Sarah Martin">
                        @error('full_name') <small>{{ $message }}</small> @enderror
                    </label>

                    <label>
                        <span>Phone number</span>
                        <input type="tel" wire:model.blur="phone" autocomplete="tel" placeholder="(984) 400-4008">
                        @error('phone') <small>{{ $message }}</small> @enderror
                    </label>
                </div>

                <div class="lolo-wizard-field-grid">
                    <label>
                        <span>Email, optional</span>
                        <input type="email" wire:model.blur="email" autocomplete="email" placeholder="you@example.com">
                        @error('email') <small>{{ $message }}</small> @enderror
                    </label>

                    <label>
                        <span>Care ZIP code</span>
                        <input type="text" wire:model.blur="zip" inputmode="numeric" autocomplete="postal-code" placeholder="27601">
                        @error('zip') <small>{{ $message }}</small> @enderror
                    </label>
                </div>

                <label>
                    <span>Anything we should know before calling?</span>
                    <textarea wire:model.blur="notes" rows="4" placeholder="Example: We are looking for companionship twice a week and help with light meals."></textarea>
                    @error('notes') <small>{{ $message }}</small> @enderror
                </label>

                <div class="lolo-wizard-consent-wrap">
                    <div class="lolo-wizard-consent">
                        <input id="lolo-callback-sms-opt-in" class="lolo-wizard-consent-input" type="checkbox" wire:model.live="sms_opt_in">
                        <label for="lolo-callback-sms-opt-in" class="lolo-wizard-consent-label">
                            Optional: I agree LoLo may text me about this care request. Message and data rates may apply. I can reply STOP to texts.
                            Leave this unchecked if you prefer only a phone callback.
                            <a href="{{ route('legal.show', ['slug' => 'privacy-policy']) }}" target="_blank" rel="noopener noreferrer">Privacy policy</a>
                        </label>
                    </div>
                </div>

                <div class="lolo-wizard-actions">
                    <button type="button" class="lolo-wizard-back" wire:click="back">Back</button>
                    <button type="submit" class="lolo-wizard-submit" wire:loading.attr="disabled">
                        <span wire:loading.remove>Request my callback</span>
                        <span wire:loading>Sending...</span>
                    </button>
                </div>

                <p class="lolo-wizard-note">Care starts at $30/hr. LoLo provides non-medical support and is not for emergencies.</p>
            </form>
        @endif

        @if ($summary !== [])
            <aside class="lolo-wizard-summary" aria-label="Care request summary">
                <p>Your care snapshot</p>
                <div>
                    @foreach ($summary as $item)
                        <span><strong>{{ $item['label'] }}:</strong> {{ $item['value'] }}</span>
                    @endforeach
                </div>
            </aside>
        @endif

        @if ($step > 1 && $step < $contactStep)
            <button type="button" class="lolo-wizard-back lolo-wizard-back-inline" wire:click="back">Back</button>
        @endif
    @endif
</div>
