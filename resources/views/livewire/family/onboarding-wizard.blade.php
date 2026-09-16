<section class="wizard-card" aria-label="Family onboarding"
    x-data="{}"
    x-on:onboarding-step-changed.window="$nextTick(() => { $el.querySelector('h1')?.focus(); window.scrollTo({top: 0}); })"
    x-on:onboarding-validation-failed.window="$nextTick(() => $el.querySelector('[role=alert]')?.focus())">
    <div class="card-topline"><span>STEP 0{{ $step }} OF 05</span><span wire:loading role="status">Saving…</span></div>
    <div class="progress-track" role="progressbar" aria-label="Onboarding progress" aria-valuemin="0" aria-valuemax="5" aria-valuenow="{{ $step }}"><span style="width:{{ $step * 20 }}%"></span></div>

    @if($errors->any())
        <div class="error-summary" role="alert" tabindex="-1">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            @if($errors->has('conflict'))<button type="button" wire:click="reloadSaved">Reload saved answers</button>@endif
        </div>
    @endif

    <form wire:submit="next" novalidate>
        <div class="wizard-step" wire:key="onboarding-step-{{ $step }}">
            @if($step === 1)
                <div class="step-heading"><h1 tabindex="-1">Who is receiving care?</h1></div>
                <fieldset class="choice-grid"><legend class="sr-only">Who is receiving care?</legend>
                    @foreach(['me' => 'Me', 'family' => 'A family member'] as $value => $label)
                        <label class="choice-card"><input type="radio" wire:model.live="form.care_for" name="care_for" value="{{ $value }}"><span class="choice-icon" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24"><circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3"/>@if($value === 'family')<path d="M17 4a3 3 0 0 1 0 6m1 4a5 5 0 0 1 3 5v2"/>@endif</svg></span><span class="choice-title">{{ $label }}</span><span class="radio-mark" aria-hidden="true"></span></label>
                    @endforeach
                </fieldset>
                @if(($form['care_for'] ?? '') === 'family')
                    <div class="conditional-fields">
                        <div class="field"><label for="recipient_name">Their full name</label><input id="recipient_name" wire:model="form.recipient_name" maxlength="100" autocomplete="off" placeholder="e.g. Susan Taylor"></div>
                        <div class="field"><label for="relationship">Their relationship to you</label><select id="relationship" wire:model="form.relationship"><option value="">Select a relationship</option>@foreach($relationships as $relationship)<option value="{{ $relationship }}">{{ $relationship }}</option>@endforeach</select></div>
                    </div>
                @endif
            @elseif($step === 2)
                <div class="step-heading"><h1 tabindex="-1">Where will care happen?</h1><p>We'll use this address to help you find care nearby.</p></div>
                <div class="field"><label for="address_line1">Street address</label><input id="address_line1" wire:model="form.address_line1" maxlength="200" autocomplete="section-care address-line1" placeholder="Street number and street name"></div>
                <div class="field"><label for="address_line2">Apartment, suite, etc. <span class="optional">(optional)</span></label><input id="address_line2" wire:model="form.address_line2" maxlength="100" autocomplete="section-care address-line2" placeholder="Apartment or unit number"></div>
                <div class="address-row">
                    <div class="field"><label for="city">City</label><input id="city" wire:model="form.city" maxlength="100" autocomplete="section-care address-level2" placeholder="City"></div>
                    <div class="field"><label for="state">State</label><input id="state" wire:model="form.state" maxlength="2" autocomplete="section-care address-level1" placeholder="e.g. NC"></div>
                    <div class="field"><label for="zip">ZIP code</label><input id="zip" wire:model="form.zip" maxlength="10" autocomplete="section-care postal-code" inputmode="numeric" placeholder="e.g. 27601"></div>
                </div>
            @elseif($step === 3)
                <div class="step-heading"><span class="optional-badge">OPTIONAL</span><h1 tabindex="-1">Want to tell caregivers a little about {{ ($form['care_for'] ?? '') === 'me' ? 'yourself' : 'this person' }}?</h1></div>
                <div class="field"><label for="care_notes">What should a caregiver know?</label><textarea id="care_notes" wire:model="form.care_notes" rows="3" maxlength="2000" placeholder="Personality, daily routine, or the support needed."></textarea></div>
                <div class="field"><label for="care_preferences">What helps care go well?</label><textarea id="care_preferences" wire:model="form.care_preferences" rows="3" maxlength="2000" placeholder="Familiar comforts, preferences, or things that help."></textarea></div>
            @elseif($step === 4)
                <div class="step-heading"><h1 tabindex="-1">A free visit to get started</h1></div>
                <div class="welcome-visit"><div><div class="visit-title"><h3>Meet a member of the LoLo team</h3></div><p>We'll answer your questions and guide you through getting started.</p><div class="visit-meta"><span>1 hour</span><span>At your care address</span></div></div></div>
                <fieldset class="visit-choices"><legend>Would you like a welcome visit?</legend>
                    <label class="inline-choice"><input type="radio" wire:model.live="form.welcome_visit" name="welcome_visit" value="yes"><span class="radio-mark" aria-hidden="true"></span><span><strong>Yes, I'd like a visit</strong><small>Choose a date and time range below</small></span></label>
                    <label class="inline-choice"><input type="radio" wire:model.live="form.welcome_visit" name="welcome_visit" value="no"><span class="radio-mark" aria-hidden="true"></span><span><strong>No thanks</strong><small>Continue to your first request</small></span></label>
                </fieldset>
                @if(($form['welcome_visit'] ?? '') === 'yes')
                    <div id="visit-scheduling" class="conditional-fields">
                        <div class="schedule-heading"><h3>When works for you?</h3></div>
                        <p class="field-hint" id="schedule-hint">Choose a range that starts at least 24 hours from now.</p>
                        <div class="field"><label for="visit_date">Preferred date</label><input id="visit_date" wire:model="form.visit_date" type="date" min="{{ $minimumDate }}" aria-describedby="schedule-hint"></div>
                        <fieldset class="time-range-field"><legend>Preferred time</legend><div class="time-range-options">
                            @foreach($ranges as $value => $range)
                                <label class="time-range-choice"><input type="radio" wire:model="form.visit_time" name="visit_time" value="{{ $value }}"><strong>{{ $range['label'] }}</strong><span>{{ $range['hours'] }}</span></label>
                            @endforeach
                        </div></fieldset>
                        <p class="timezone-note">{{ $timezone === 'America/New_York' ? 'Eastern Time' : $timezone }}, at your care address.</p>
                        <p class="visit-confirmation">Our team will confirm the final time by text.</p>
                        <div class="field phone-field"><label for="phone">Phone number for confirmation</label><input id="phone" wire:model="form.phone" type="tel" autocomplete="tel" maxlength="30"></div>
                    </div>
                @endif
            @else
                <div class="step-heading"><h1 tabindex="-1">Let's post your first request</h1></div>
                <ol class="care-journey" aria-label="How finding care works">
                    @foreach([['Post a request', 'Choose the help you need and when.'], ['Review caregivers', 'Compare profiles and applications.'], ['Hire your caregiver', "Choose who you'd like to work with."], ['Your first care visit', 'Your caregiver arrives at the agreed time.']] as [$title, $description])
                        <li><span class="journey-icon" aria-hidden="true">{{ $loop->iteration }}</span><div><h3>{{ $title }}</h3><p>{{ $description }}</p></div>@if($loop->first)<span class="next-badge">UP NEXT</span>@endif</li>
                    @endforeach
                </ol>
                @if(($form['welcome_visit'] ?? '') === 'yes')
                    <div class="visit-summary"><div><strong>Your preferred welcome visit</strong><p>{{ $form['visit_date'] }} · {{ $ranges[$form['visit_time']]['label'] ?? '' }} ({{ $ranges[$form['visit_time']]['hours'] ?? '' }}) · {{ $timezone === 'America/New_York' ? 'ET' : $timezone }} · 1 hour · Free</p><p>Our team will confirm the final time by text.</p></div><button type="button" wire:click="back" wire:loading.attr="disabled">Edit</button></div>
                @endif
            @endif
        </div>
        <footer class="wizard-footer">
            @if($step > 1)<button class="back-button" type="button" wire:click="back" wire:loading.attr="disabled">← Back</button>@endif
            <button class="continue-button" type="submit" wire:loading.attr="disabled"><span>{{ $step === 5 ? 'Create my first request' : 'Continue' }}</span><span aria-hidden="true">→</span></button>
        </footer>
    </form>
</section>
