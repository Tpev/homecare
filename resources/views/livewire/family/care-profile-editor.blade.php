<div class="hc-page py-8 sm:py-12">
    <div class="mx-auto max-w-4xl space-y-6">
        <header>
            <a href="{{ route('family.care-profiles.index') }}" wire:navigate class="inline-flex min-h-11 items-center text-sm font-semibold text-[#2F6F62] underline underline-offset-4">Back to care profiles</a>
            <p class="mt-3 text-xs font-bold uppercase tracking-[0.18em] text-[#B95745]">Care profile</p>
            <h1 class="mt-2 font-display text-3xl font-semibold text-[#17313F]">{{ $profile ? 'About '.$profile->displayName() : 'Tell caregivers about the person' }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-[#607080]">This is optional. Skip anything you do not want to share, and come back whenever you like.</p>
            @if ($profile?->updatedBy)
                <p class="mt-2 text-xs text-[#6A7784]">Last updated by {{ $profile->updatedBy->name }} on {{ $profile->updated_at->format('F j, Y') }}</p>
            @endif
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status" aria-live="polite">{{ session('status') }}</div>
        @endif
        @if ($errors->has('profile'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">{{ $errors->first('profile') }}</div>
        @endif

        <nav aria-label="Care profile progress" class="rounded-2xl border border-[#E4DDD3] bg-white p-3 shadow-sm">
            <ol class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ([1 => 'About them', 2 => 'How to help', 3 => 'Routine', 4 => 'Safety'] as $number => $label)
                    <li class="rounded-xl px-3 py-2 text-center text-xs font-bold {{ $step === $number ? 'bg-[#17313F] text-white' : ($step > $number ? 'bg-[#DDEBE4] text-[#17313F]' : 'bg-[#F5F1EB] text-[#6A7784]') }}">
                        <span class="block">Step {{ $number }}</span><span class="mt-0.5 block font-medium">{{ $label }}</span>
                    </li>
                @endforeach
            </ol>
            @if ($step === 5)<p class="mt-3 text-center text-sm font-semibold text-[#17313F]">Preview: what caregivers will see</p>@endif
        </nav>

        <form data-ai-target="family.care_profile.editor" tabindex="-1" wire:submit="{{ $step === 5 ? 'saveReady' : 'continue' }}" class="space-y-5 outline-none">
            @if ($step === 1)
                <section class="rounded-2xl border border-[#DCCFBE] bg-white p-5 shadow-sm" aria-labelledby="about-step">
                    <h2 id="about-step" class="font-display text-2xl font-semibold text-[#17313F]">About the person</h2>
                    <p class="mt-2 text-sm text-[#607080]">Start with the name they want a caregiver to use and a few things that make them who they are.</p>
                    <div class="mt-6 space-y-5">
                        <div><x-input label="Preferred name" wire:model="preferredName" placeholder="Charles" required />@error('preferredName')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                        <fieldset>
                            <legend class="text-sm font-semibold text-[#324457]">Who is arranging care?</legend>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border border-[#DED6CA] p-3"><input type="radio" wire:model.live="recipientIsRequester" value="0" class="text-[#17313F]"> <span>Family or friend is arranging care</span></label>
                                <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border border-[#DED6CA] p-3"><input type="radio" wire:model.live="recipientIsRequester" value="1" class="text-[#17313F]"> <span>I am receiving care</span></label>
                            </div>
                        </fieldset>
                        @if (! $recipientIsRequester)
                            <div><x-input label="Relationship to your family" wire:model="relationshipToFamily" placeholder="Mother, father, spouse, friend" />@error('relationshipToFamily')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                        @endif
                        <div><x-textarea label="What would you like a caregiver to know about {{ $preferredName ?: 'them' }}?" wire:model="aboutThem" rows="4" placeholder="Charles enjoys baseball, quiet conversation, and choosing his own clothes." />@error('aboutThem')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div><x-textarea label="What do they enjoy or find comforting?" wire:model="interestsAndComforts" rows="3" />@error('interestsAndComforts')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                            <div><x-textarea label="What usually makes a visit go well?" wire:model="goodVisitNotes" rows="3" />@error('goodVisitNotes')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                        </div>
                        <details class="rounded-xl border border-[#E4DDD3] bg-[#FFFCF8] p-4">
                            <summary class="cursor-pointer font-semibold text-[#17313F]">Add more details</summary>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div><x-input label="Full name" wire:model="fullName" />@error('fullName')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                                <div><x-input type="date" label="Date of birth (family only)" wire:model="dateOfBirth" />@error('dateOfBirth')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                                <label class="block text-sm font-medium text-[#324457]">Age range
                                    <select wire:model="ageRange" class="mt-1 min-h-12 w-full rounded-xl border-[#D7CEC2] text-base focus:border-[#17313F] focus:ring-[#17313F]">
                                        <option value="">Prefer not to add</option>@foreach($ageOptions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                                    </select>
                                </label>
                                <div><x-input label="Pronouns (optional)" wire:model="pronouns" placeholder="She/her, he/him, they/them" />@error('pronouns')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                            </div>
                        </details>
                    </div>
                </section>
            @elseif ($step === 2)
                <section class="rounded-2xl border border-[#DCCFBE] bg-white p-5 shadow-sm" aria-labelledby="support-step">
                    <h2 id="support-step" class="font-display text-2xl font-semibold text-[#17313F]">How can a caregiver best support {{ $preferredName }}?</h2>
                    <p class="mt-2 text-sm text-[#607080]">Choose only what helps a caregiver understand whether they are a good fit.</p>
                    <div class="mt-6 space-y-6">
                        <fieldset><legend class="font-semibold text-[#324457]">Communication</legend><div class="mt-3 grid gap-2 sm:grid-cols-2">@foreach($communicationOptions as $key=>$label)<label class="flex min-h-12 cursor-pointer items-start gap-3 rounded-xl border border-[#DED6CA] p-3 text-sm"><input type="checkbox" value="{{ $key }}" wire:model="communicationPreferences" class="mt-0.5 rounded text-[#17313F]"><span>{{ $label }}</span></label>@endforeach</div>@error('communicationPreferences.*')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</fieldset>
                        <div><x-textarea label="What is the best way to communicate with {{ $preferredName }}?" wire:model="communicationNotes" rows="3" />@error('communicationNotes')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                        <div><x-textarea label="Are there health or memory conditions that affect everyday support?" wire:model="everydayHealthContext" rows="3" placeholder="Share only what is useful for non-medical care." />@error('everydayHealthContext')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                        <fieldset><legend class="font-semibold text-[#324457]">Everyday support</legend><div class="mt-3 grid gap-2 sm:grid-cols-2">@foreach($supportOptions as $key=>$label)<div class="rounded-xl border border-[#DED6CA] p-3"><label class="flex min-h-8 cursor-pointer items-start gap-3 text-sm"><input type="checkbox" value="{{ $key }}" wire:model.live="supportAreas" class="mt-0.5 rounded text-[#17313F]"><span>{{ $label }}</span></label>@if(in_array($key,$supportAreas,true))<input type="text" wire:model="supportDetails.{{ $key }}" maxlength="300" placeholder="Optional helpful detail" class="mt-2 min-h-11 w-full rounded-xl border-[#D7CEC2] text-base">@endif</div>@endforeach</div></fieldset>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block text-sm font-medium text-[#324457]">Mobility
                                <select wire:model="mobilityLevel" class="mt-1 min-h-12 w-full rounded-xl border-[#D7CEC2] text-base focus:border-[#17313F] focus:ring-[#17313F]"><option value="">Skip this question</option>@foreach($mobilityOptions as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                            </label>
                            <div><x-textarea label="Anything helpful about mobility?" wire:model="mobilityNotes" rows="3" />@error('mobilityNotes')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                        </div>
                    </div>
                </section>
            @elseif ($step === 3)
                <section class="rounded-2xl border border-[#DCCFBE] bg-white p-5 shadow-sm" aria-labelledby="routine-step">
                    <h2 id="routine-step" class="font-display text-2xl font-semibold text-[#17313F]">Routine and comfort</h2>
                    <p class="mt-2 text-sm text-[#607080]">Small details can help a new caregiver make the day feel familiar and respectful.</p>
                    <div class="mt-6 space-y-5">
                        <div><x-textarea label="What parts of {{ $preferredName }}'s usual routine are helpful to know?" wire:model="routineNotes" rows="4" />@error('routineNotes')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div><x-textarea label="Food preferences, texture needs, or allergies" wire:model="foodAndDrinkNotes" rows="3" />@error('foodAndDrinkNotes')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                            <div><x-textarea label="What helps personal care feel respectful?" wire:model="personalCarePreferences" rows="3" />@error('personalCarePreferences')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                        </div>
                        <div><x-textarea label="What is helpful to know at night?" wire:model="sleepOvernightNotes" rows="3" />@error('sleepOvernightNotes')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                        <fieldset><legend class="font-semibold text-[#324457]">Comfort and reassurance</legend><div class="mt-3 grid gap-2 sm:grid-cols-2">@foreach($comfortOptions as $key=>$label)<label class="flex min-h-12 cursor-pointer items-start gap-3 rounded-xl border border-[#DED6CA] p-3 text-sm"><input type="checkbox" value="{{ $key }}" wire:model="comfortNeeds" class="mt-0.5 rounded text-[#17313F]"><span>{{ $label }}</span></label>@endforeach</div></fieldset>
                        <div class="grid gap-4 sm:grid-cols-2"><div><x-textarea label="What can make {{ $preferredName }} feel worried or uncomfortable?" wire:model="distressTriggers" rows="3" /></div><div><x-textarea label="What usually helps them feel calm and safe?" wire:model="calmingApproaches" rows="3" /></div></div>
                    </div>
                </section>
            @elseif ($step === 4)
                <section class="rounded-2xl border border-[#DCCFBE] bg-white p-5 shadow-sm" aria-labelledby="safety-step">
                    <h2 id="safety-step" class="font-display text-2xl font-semibold text-[#17313F]">Safety and expectations</h2>
                    <p class="mt-2 text-sm leading-6 text-[#607080]">Share only what a caregiver needs to decide whether they can safely provide this non-medical care. For urgent medical instructions, contact the appropriate medical professional.</p>
                    <div class="mt-6 space-y-6">
                        <fieldset><legend class="font-semibold text-[#324457]">Important for safety</legend><div class="mt-3 grid gap-2 sm:grid-cols-2">@foreach($safetyOptions as $key=>$label)<label class="flex min-h-12 cursor-pointer items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm"><input type="checkbox" value="{{ $key }}" wire:model="safetyItems" class="mt-0.5 rounded text-amber-700"><span>{{ $label }}</span></label>@endforeach</div></fieldset>
                        <div><x-textarea label="Anything else important for safety?" wire:model="safetyNotes" rows="4" />@error('safetyNotes')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div>
                        <fieldset><legend class="font-semibold text-[#324457]">Most important caregiver qualities (choose up to five)</legend><div class="mt-3 grid gap-2 sm:grid-cols-2">@foreach($qualityOptions as $key=>$label)<label class="flex min-h-12 cursor-pointer items-start gap-3 rounded-xl border border-[#DED6CA] p-3 text-sm"><input type="checkbox" value="{{ $key }}" wire:model="caregiverQualityPreferences" class="mt-0.5 rounded text-[#17313F]"><span>{{ $label }}</span></label>@endforeach</div>@error('caregiverQualityPreferences')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</fieldset>
                        <div class="grid gap-4 sm:grid-cols-2"><div><x-textarea label="What should a caregiver do consistently?" wire:model="caregiverDoNotes" rows="3" /></div><div><x-textarea label="Is there anything a caregiver should avoid?" wire:model="caregiverAvoidNotes" rows="3" /></div></div>
                        <details class="rounded-xl border border-[#E4DDD3] bg-[#FFFCF8] p-4" @if($includeAdditionalContact) open @endif>
                            <summary class="cursor-pointer font-semibold text-[#17313F]">Add details for a confirmed caregiver</summary>
                            <p class="mt-2 text-sm text-[#607080]">These details are hidden from potential caregivers and appear only after care is confirmed.</p>
                            <label class="mt-4 flex min-h-12 cursor-pointer items-start gap-3 rounded-xl border border-[#DED6CA] bg-white p-3"><input type="checkbox" wire:model.live="includeAdditionalContact" class="mt-0.5 rounded text-[#17313F]"><span><span class="block font-semibold">Add another contact</span><span class="block text-sm text-[#607080]">A family member or helper who should be reachable.</span></span></label>
                            @if($includeAdditionalContact)<div class="mt-4 grid gap-4 sm:grid-cols-2"><div><x-input label="Contact name" wire:model="additionalContactName" />@error('additionalContactName')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div><div><x-input label="Relationship" wire:model="additionalContactRelationship" /></div><div><x-input label="Phone" wire:model="additionalContactPhone" /></div><div><x-input type="email" label="Email" wire:model="additionalContactEmail" />@error('additionalContactEmail')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror</div></div>@endif
                            <div class="mt-4"><x-textarea label="Care coordination or escalation note" wire:model="assignedEscalationNotes" rows="3" /></div>
                        </details>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">Do not enter medication dosages, financial details, passwords, door codes, or urgent medical instructions in the care profile.</div>
                    </div>
                </section>
            @else
                <section class="space-y-5" aria-labelledby="preview-step">
                    <div class="rounded-2xl border border-[#DCCFBE] bg-white p-5 shadow-sm">
                        <h2 id="preview-step" class="font-display text-2xl font-semibold text-[#17313F]">What caregivers will see</h2>
                        <p class="mt-2 text-sm leading-6 text-[#607080]">This information is shared only with caregivers who can view care for {{ $preferredName }}. Contact details, the exact address, and date of birth are not shown before care is confirmed.</p>
                    </div>

                    <section aria-labelledby="before-confirmed">
                        <h3 id="before-confirmed" class="mb-2 font-display text-lg font-semibold text-[#17313F]">Before care is confirmed</h3>
                        <x-care-recipient-profile-summary :snapshot="$candidatePreview" />
                    </section>

                    <details class="rounded-2xl border border-[#DCCFBE] bg-[#F8F5F0] p-4">
                        <summary class="cursor-pointer font-display text-lg font-semibold text-[#17313F]">After care is confirmed</summary>
                        <p class="mt-2 text-sm text-[#607080]">A confirmed caregiver can also see the care-coordination details below.</p>
                        <x-care-recipient-profile-summary :snapshot="$assignedPreview" :show-sharing-footer="false" class="mt-4" />
                    </details>

                    @if (! $hasAcknowledged)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#CFE1D8] bg-[#F2F8F4] p-4 text-sm leading-6 text-[#17313F]"><input type="checkbox" wire:model="sharingAcknowledged" class="mt-1 rounded text-[#17313F]"><span>I understand that this profile may include personal care information and will be shared with eligible caregivers when I use it for care.</span></label>
                        @error('sharingAcknowledged')<p class="text-sm text-rose-700">{{ $message }}</p>@enderror
                    @endif

                    @if ($savedReady)
                        @if ($affectedCare !== [])
                            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5" aria-labelledby="update-care-heading">
                                <h3 id="update-care-heading" class="font-display text-xl font-semibold text-amber-950">Use this update for current care?</h3>
                                <p class="mt-2 text-sm leading-6 text-amber-900">New care will use the updated profile automatically. {{ count($affectedCare) }} current care arrangement{{ count($affectedCare) === 1 ? ' is' : 's are' }} still using previous information.</p>
                                <ul class="mt-3 space-y-2 text-sm text-amber-950">@foreach($affectedCare as $item)<li class="rounded-lg bg-white/70 px-3 py-2"><span class="font-semibold">{{ $item['title'] }}</span> &middot; {{ $item['type'] === 'coverage' ? 'Continuous Coverage' : ($item['type'] === 'regular' ? 'Regular care' : 'Care request') }}</li>@endforeach</ul>
                                <div class="mt-5 grid gap-3 sm:grid-cols-2"><button type="button" wire:click="updateCurrentCare" class="min-h-12 rounded-xl bg-[#17313F] px-5 font-semibold text-white">Update current care</button><button type="button" wire:click="finishWithoutUpdating" class="min-h-12 rounded-xl border border-amber-300 bg-white px-5 font-semibold text-amber-950">Not now</button></div>
                            </section>
                        @else
                            <a href="{{ route('family.care-profiles.index') }}" wire:navigate class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-[#17313F] px-5 font-semibold text-white sm:w-auto">Done</a>
                        @endif
                    @endif
                </section>
            @endif

            @if (! $savedReady)
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex gap-3">
                        @if ($step > 1)<button type="button" wire:click="back" class="min-h-12 rounded-xl border border-[#D7CEC2] bg-white px-5 font-semibold text-[#17313F]">Back</button>@endif
                        <button type="button" wire:click="saveAndFinishLater" class="min-h-12 rounded-xl border border-[#D7CEC2] bg-white px-5 font-semibold text-[#17313F]">Save and finish later</button>
                    </div>
                    <button type="submit" class="min-h-12 rounded-xl bg-[#17313F] px-6 font-semibold text-white shadow-sm hover:bg-[#23483F]">{{ $step === 5 ? 'Save care profile' : ($step === 4 ? 'Preview what caregivers see' : 'Continue') }}</button>
                </div>
            @endif
        </form>
    </div>
</div>
