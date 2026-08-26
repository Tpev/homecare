<section id="quick-request" class="hub-home-request">
    <div class="hub-request-shell">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="hub-request-kicker">Book care</p>
                <h2 class="hub-request-title">Tell us what you need</h2>
                <p class="hub-request-price">Companionship starts at <strong>$30/hr*</strong> <span class="text-xs">+ $1/hr processing fee</span></p>
            </div>

            <span class="hub-request-heart" aria-hidden="true">
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path d="M10 17s-5.6-3.5-7.8-6.5A4.6 4.6 0 0110 4a4.6 4.6 0 017.8 6.5C15.6 13.5 10 17 10 17z"/></svg>
            </span>
        </div>

        <div class="mt-5 space-y-4">
            <div class="hub-request-field">
                <label for="homepage_service_type" class="hub-request-label">Service type</label>
                <select id="homepage_service_type" wire:model.live="service_type" class="hub-select">
                    @foreach ($serviceOptions as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                @error('service_type') <p class="hub-error">{{ $message }}</p> @enderror
            </div>

            <div class="hub-request-grid-two">
                <div class="hub-request-field">
                    <label for="homepage_zip" class="hub-request-label">ZIP</label>
                    <input id="homepage_zip" type="text" wire:model.blur="zip" inputmode="numeric" class="hub-input" placeholder="27601">
                    @error('zip') <p class="hub-error">{{ $message }}</p> @enderror
                </div>

                <div class="hub-request-field">
                    <label for="homepage_time_preference" class="hub-request-label">Time</label>
                    <select id="homepage_time_preference" wire:model.live="time_preference" class="hub-select">
                        @foreach ($timeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('time_preference') <p class="hub-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <button type="button" wire:click="continueToCallback" class="hub-request-submit mt-5">
            Continue request
        </button>

        <div class="hub-request-meta">
            <span>Clear hourly care. No long-term commitment.</span>
        </div>
    </div>
</section>
