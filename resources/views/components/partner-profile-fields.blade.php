@props(['section', 'categories' => [], 'states' => [], 'selectedStates' => [], 'coverageType' => 'specific_areas', 'registration' => false])

@if ($section === 'business')
    <div class="grid gap-5 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <x-input label="Business name" wire:model="form.business_name" autocomplete="organization" placeholder="Your business or organization" required />
            <x-input-error :messages="$errors->get('form.business_name')" class="mt-2" />
        </div>
        <div class="sm:col-span-2">
            <x-input label="Website (optional)" wire:model="form.website" placeholder="yourbusiness.com" inputmode="url" autocomplete="url" />
            <x-input-error :messages="$errors->get('form.website')" class="mt-2" />
        </div>
        <div class="sm:col-span-2">
            <label for="partner-description" class="mb-2 block text-sm font-semibold text-[#344C45]">About your business <span class="font-normal text-[#66746D]">(optional)</span></label>
            <textarea id="partner-description" wire:model="form.description" rows="3" maxlength="1000" placeholder="A few words about what you do and the people you help." class="w-full rounded-xl border-[#D6CBBC] bg-[#FFFCF7] text-sm text-[#23483F] focus:border-[#3C7163] focus:ring-[#3C7163]"></textarea>
            <x-input-error :messages="$errors->get('form.description')" class="mt-2" />
        </div>
        <div class="sm:col-span-2 border-t border-[#E8DED1] pt-5">
            <p class="text-sm font-bold text-[#173F35]">Your main contact</p>
            <p class="mt-1 text-sm text-[#66746D]">Who should our team reach out to?</p>
        </div>
        <div class="sm:col-span-2">
            <x-input label="Contact name" wire:model="form.contact_name" autocomplete="name" required />
            <x-input-error :messages="$errors->get('form.contact_name')" class="mt-2" />
        </div>
        <div>
            <x-input type="email" label="Work email" wire:model="form.contact_email" autocomplete="email" required />
            <x-input-error :messages="$errors->get('form.contact_email')" class="mt-2" />
            @if ($registration)
                <p class="mt-2 text-xs leading-5 text-[#66746D]">You will use this email to sign in.</p>
            @else
                <p class="mt-2 text-xs leading-5 text-[#66746D]">Updating this contact email does not change your sign-in email.</p>
            @endif
        </div>
        <div>
            <x-input type="tel" label="Phone number" wire:model="form.contact_phone" autocomplete="tel" placeholder="(555) 123-4567" required />
            <x-input-error :messages="$errors->get('form.contact_phone')" class="mt-2" />
        </div>
    </div>
@else
    <div class="space-y-7">
        <fieldset>
            <legend class="text-sm font-bold text-[#173F35]">Which services are you interested in?</legend>
            <p class="mt-1 text-sm leading-6 text-[#66746D]">Select every category that fits your business.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($categories as $value => $label)
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-[#DED6CA] bg-[#FFFCF7] p-4 transition hover:border-[#628675] has-[:checked]:border-[#315B4D] has-[:checked]:bg-[#EDF3ED]">
                        <input type="checkbox" wire:model="form.categories" value="{{ $value }}" class="mt-0.5 h-5 w-5 shrink-0 rounded border-[#BFB4A5] text-[#23483F] focus:ring-[#3C7163]">
                        <span class="text-sm font-semibold leading-5 text-[#23483F]">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('form.categories')" class="mt-2" />
            <x-input-error :messages="$errors->first('form.categories.*')" class="mt-2" />
        </fieldset>

        <div class="border-t border-[#E8DED1] pt-6">
            <h3 class="text-lg font-semibold">Where do you serve customers?</h3>
            <p class="mt-1 text-sm leading-6 text-[#66746D]">Choose your states, then tell us how much of each area you cover.</p>
            <div class="mt-4" x-data="{ statesOpen: false, stateSearch: '' }">
                <label id="partner-states-label" class="mb-2 block text-sm font-semibold text-[#344C45]">States served</label>
                <button type="button" @click="statesOpen = !statesOpen" :aria-expanded="statesOpen" aria-controls="partner-state-options" aria-labelledby="partner-states-label partner-state-toggle" class="flex min-h-12 w-full items-center justify-between gap-3 rounded-xl border border-[#D6CBBC] bg-[#FFFCF7] px-4 py-3 text-left text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#3C7163]">
                    <span id="partner-state-toggle">{{ count($selectedStates) ? count($selectedStates).' state'.(count($selectedStates) === 1 ? '' : 's').' selected' : 'Choose one or more states' }}</span>
                    <span aria-hidden="true" class="text-lg" x-text="statesOpen ? '−' : '+'"></span>
                </button>
                <div id="partner-state-options" x-show="statesOpen" x-cloak class="mt-2 rounded-xl border border-[#D6CBBC] bg-[#FFFCF7] p-3">
                    <label for="partner-state-search" class="sr-only">Find a state</label>
                    <input id="partner-state-search" type="search" x-model="stateSearch" placeholder="Find a state" autocomplete="off" class="mb-3 w-full rounded-lg border-[#D6CBBC] bg-white px-3 py-2 text-sm focus:border-[#3C7163] focus:ring-[#3C7163]">
                    <fieldset class="grid max-h-52 gap-1 overflow-y-auto sm:grid-cols-2" aria-label="States served">
                        @foreach ($states as $code => $label)
                            <label x-show="!stateSearch || $el.textContent.toLowerCase().includes(stateSearch.toLowerCase())" class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-2 text-sm hover:bg-[#EDF3ED]">
                                <input type="checkbox" wire:model.live="form.coverage_states" value="{{ $code }}" class="h-4 w-4 rounded border-[#BFB4A5] text-[#23483F] focus:ring-[#3C7163]">
                                <span>{{ $label }} <span class="text-[#66746D]">({{ $code }})</span></span>
                            </label>
                        @endforeach
                    </fieldset>
                </div>
                @if ($selectedStates !== [])
                    <div class="mt-3 flex flex-wrap gap-2" aria-label="Selected states">
                        @foreach ($selectedStates as $code)
                            <span class="rounded-full bg-[#EDF3ED] px-3 py-1 text-xs font-semibold text-[#315B4D]">{{ $states[$code] ?? $code }}</span>
                        @endforeach
                    </div>
                @endif
                <x-input-error :messages="$errors->get('form.coverage_states')" class="mt-2" />
                <x-input-error :messages="$errors->first('form.coverage_states.*')" class="mt-2" />
            </div>

            <fieldset class="mt-5 space-y-3">
                <legend class="mb-3 text-sm font-semibold text-[#344C45]">Coverage within these states</legend>
                <label class="flex cursor-pointer items-start gap-3 text-sm text-[#344C45]">
                    <input type="radio" wire:model.live="form.coverage_type" value="specific_areas" class="mt-0.5 border-[#BFB4A5] text-[#23483F] focus:ring-[#3C7163]">
                    <span>Specific cities, counties or ZIP codes</span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 text-sm text-[#344C45]">
                    <input type="radio" wire:model.live="form.coverage_type" value="entire_states" class="mt-0.5 border-[#BFB4A5] text-[#23483F] focus:ring-[#3C7163]">
                    <span>All areas in the selected states</span>
                </label>
                <x-input-error :messages="$errors->get('form.coverage_type')" class="mt-2" />
            </fieldset>

            @if ($coverageType === 'specific_areas')
                <div class="mt-5">
                    <label for="partner-coverage-areas" class="mb-2 block text-sm font-semibold text-[#344C45]">Cities, counties or ZIP codes</label>
                    <textarea id="partner-coverage-areas" wire:model="form.coverage_areas" rows="3" maxlength="1000" placeholder="For example: Raleigh and Cary, NC; Wake County; 27601, 27513" class="w-full rounded-xl border-[#D6CBBC] bg-[#FFFCF7] text-sm text-[#23483F] focus:border-[#3C7163] focus:ring-[#3C7163]" required></textarea>
                    <x-input-error :messages="$errors->get('form.coverage_areas')" class="mt-2" />
                </div>
            @endif
        </div>

        <div>
            <label for="partner-customer-notes" class="mb-2 block text-sm font-semibold text-[#344C45]">Anything else we should know? <span class="font-normal text-[#66746D]">(optional)</span></label>
            <textarea id="partner-customer-notes" wire:model="form.customer_notes" rows="3" maxlength="1000" placeholder="Tell us about the customers you serve or any service requirements." class="w-full rounded-xl border-[#D6CBBC] bg-[#FFFCF7] text-sm text-[#23483F] focus:border-[#3C7163] focus:ring-[#3C7163]"></textarea>
            <x-input-error :messages="$errors->get('form.customer_notes')" class="mt-2" />
        </div>
    </div>
@endif
