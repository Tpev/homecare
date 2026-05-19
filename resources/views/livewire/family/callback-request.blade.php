<div class="lolo-callback-card">
    @if ($submitted)
        <div class="lolo-callback-success">
            <p class="lolo-callback-kicker">Request received</p>
            <h2>Thanks. We have what we need.</h2>
            <p>LoLo will review your request and call you back using the phone number you shared.</p>
            <a href="{{ route('landing') }}" class="lolo-callback-secondary">Back to homepage</a>
        </div>
    @else
        <form wire:submit="submit" class="lolo-callback-form">
            <div>
                <p class="lolo-callback-kicker">Care callback</p>
                <h2>Tell us what kind of support you need.</h2>
                <p class="lolo-callback-intro">Share a few details and we will call you back to help plan the next step.</p>
            </div>

            <div class="lolo-callback-grid">
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

            <div class="lolo-callback-grid">
                <label>
                    <span>Email, optional</span>
                    <input type="email" wire:model.blur="email" autocomplete="email" placeholder="you@example.com">
                    @error('email') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>ZIP code</span>
                    <input type="text" wire:model.blur="zip" inputmode="numeric" autocomplete="postal-code" placeholder="27601">
                    @error('zip') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="lolo-callback-grid">
                <label>
                    <span>Care need</span>
                    <select wire:model="service_type">
                        @foreach ($serviceOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                    @error('service_type') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Best time to call</span>
                    <select wire:model="callback_time">
                        @foreach ($callbackOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('callback_time') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <label>
                <span>Anything we should know?</span>
                <textarea wire:model.blur="notes" rows="4" placeholder="Example: My mom needs companionship twice a week and help with light meals."></textarea>
                @error('notes') <small>{{ $message }}</small> @enderror
            </label>

            <button type="submit" class="lolo-callback-submit" wire:loading.attr="disabled">
                <span wire:loading.remove>Request a callback</span>
                <span wire:loading>Sending request...</span>
            </button>

            <p class="lolo-callback-note">Care starts at $30/hr. No long-term commitment.</p>
        </form>
    @endif
</div>
