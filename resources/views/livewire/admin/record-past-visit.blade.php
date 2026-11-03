<div class="hc-page py-8 space-y-6" x-data x-on:past-visit-reviewed.window="$nextTick(() => window.scrollTo({ top: 0, behavior: 'auto' }))">
    @php
        $fieldClass = 'mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-cyan-600 focus:ring-cyan-600';
        $labelClass = 'block text-sm font-semibold text-slate-700';
        $money = fn ($cents) => '$'.number_format($cents / 100, 2);
    @endphp
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('admin.requests.index') }}" wire:navigate class="text-sm font-semibold text-cyan-700 hover:underline">← Care requests</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $visit ? 'Recorded visit' : 'Record a past visit' }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-600">Add care that already happened, complete the booking, and process the family’s saved payment method.</p>
        </div>
        @if($visit)
            <a href="{{ route('admin.requests.record-past') }}" wire:navigate class="rounded-lg border border-cyan-700 px-4 py-2 text-sm font-semibold text-cyan-800 hover:bg-cyan-50">Record another visit</a>
        @endif
    </div>

    @if($errors->any())
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Please check these details</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    @if($visit)
        @if($visit->payment_mode !== 'live')
            <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900">{{ $visit->payment_mode === 'simulated' ? 'Simulated payment — no real money moved.' : 'Stripe test payment — no real money moved.' }}</div>
        @endif
        <section class="rounded-2xl border {{ $settled ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} p-5 sm:p-6" aria-labelledby="result-heading">
            <p class="text-xs font-bold uppercase tracking-wider {{ $settled ? 'text-emerald-700' : 'text-amber-800' }}">Visit saved · Booking #{{ $visit->care_booking_id }}</p>
            <h2 id="result-heading" class="mt-2 text-xl font-bold text-slate-900">{{ $settled ? 'Visit completed and payment settled' : ($paid ? 'Family paid · caregiver settlement pending' : 'Visit completed · payment needs attention') }}</h2>
            <p class="mt-2 text-sm text-slate-700">{{ $visit->booking->family->name }} with {{ $visit->booking->caregiver->name }} · {{ $visit->input['date'] }} at {{ $visit->input['start_time'] }} · {{ $visit->input['timezone'] }}</p>
            @if(!$settled && $visit->last_error)<p class="mt-3 text-sm font-medium text-amber-900">{{ $visit->last_error }}</p>@endif
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ route('admin.requests.show', $visit->booking->care_request_id) }}" wire:navigate class="rounded-lg bg-cyan-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-cyan-800">Open request #{{ $visit->booking->care_request_id }}</a>
                @unless($settled)
                    <button type="button" wire:click="retryPayment" wire:loading.attr="disabled" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 disabled:opacity-50">{{ $paid ? 'Retry earnings settlement' : 'Retry payment · '.$money($visit->quote['total_charge_cents']) }}</button>
                @endunless
                <button type="button" wire:click="refreshPayment" wire:loading.attr="disabled" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-white/70">Refresh status</button>
            </div>
            <p wire:loading wire:target="retryPayment,refreshPayment" role="status" class="mt-3 text-sm text-slate-700">Checking this saved visit…</p>
        </section>
        <div class="grid gap-5 lg:grid-cols-2">
            <x-card>
                <h2 class="font-bold text-slate-900">Visit details</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Care recipient</dt><dd class="text-right font-medium">{{ $visit->input['recipient_name'] }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Worked time</dt><dd>{{ $visit->input['worked_minutes'] }} minutes</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Unpaid breaks</dt><dd>{{ $visit->input['break_minutes'] }} minutes</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Recorded by</dt><dd>{{ $visit->creator->name }}</dd></div>
                </dl>
                <p class="mt-5 border-t border-slate-100 pt-4 text-sm text-slate-600">{{ $visit->input['reason'] }}</p>
                @if($visit->input['overlaps_acknowledged'] ?? false)
                    <p class="mt-3 text-sm text-slate-600">Recorded as a separate visit after reviewing
                        @foreach($visit->input['reviewed_overlaps'] as $overlap)<a href="{{ route('admin.requests.show', $overlap['request_id']) }}" class="font-semibold text-cyan-700 hover:underline">booking #{{ $overlap['booking_id'] }}</a>{{ $loop->last ? '.' : ', ' }}@endforeach
                    </p>
                @endif
            </x-card>
            <x-card>
                <h2 class="font-bold text-slate-900">Payment summary</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Reviewed family charge</dt><dd class="font-bold">{{ $money($visit->quote['total_charge_cents']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Captured</dt><dd>{{ $money($payment?->amount_captured_cents ?? 0) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Caregiver gross</dt><dd>{{ $money($visit->quote['caregiver_gross_amount_cents']) }}</dd></div>
                    @if($paid && $payment->fee_finalization_status === 'finalized')
                        <div class="flex justify-between"><dt class="text-slate-500">Caregiver net</dt><dd class="font-bold">{{ $money($payment->caregiver_amount_cents) }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-slate-500">Payment status</dt><dd>{{ str_replace('_', ' ', $payment?->status ?? 'not started') }}</dd></div>
                </dl>
                <p class="mt-5 border-t border-slate-100 pt-4 text-xs text-slate-500">Earnings transfers go to the caregiver’s Stripe balance. Stripe’s bank payout schedule applies.</p>
            </x-card>
        </div>
    @elseif($review)
        @php($hasDuplicate = collect($review['overlaps'] ?? [])->contains('is_duplicate', true))
        <div class="flex items-center gap-2 text-sm"><span class="text-slate-500">1. Visit details</span><span aria-hidden="true" class="text-slate-300">/</span><span class="font-bold text-cyan-800">2. Review & charge</span></div>
        @if($review['payment_mode'] !== 'live')
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900">{{ $review['payment_mode'] === 'simulated' ? 'Simulation mode' : 'Stripe test mode' }} — this environment will not charge real money.</div>
        @endif
        <form wire:submit="createVisit" class="grid items-start gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                @if($review['overlaps'] ?? [])
                    <section class="rounded-xl border border-amber-200 bg-amber-50 p-4 sm:p-5" aria-labelledby="overlap-heading">
                        <h2 id="overlap-heading" class="text-lg font-bold text-slate-900">Review overlapping bookings</h2>
                        @if($hasDuplicate)
                            <p role="alert" class="mt-2 text-sm font-semibold text-red-800">An overlapping booking already records this exact visit. Open the existing request to finish or correct it; this visit cannot be charged again here.</p>
                        @else
                            <p class="mt-2 text-sm text-slate-700">These bookings share hours with the family or caregiver. Review the recorded care and payments before adding a separate visit.</p>
                        @endif
                        <div class="mt-4 space-y-4">
                            @foreach($review['overlaps'] as $overlap)
                                <div class="rounded-lg border border-amber-200 bg-white p-4 text-sm">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <p class="font-bold text-slate-900">Booking #{{ $overlap['booking_id'] }} · {{ str_replace('_', ' ', $overlap['status']) }}</p>
                                        <a href="{{ route('admin.requests.show', $overlap['request_id']) }}" target="_blank" rel="noopener" class="font-semibold text-cyan-700 hover:underline">Open request #{{ $overlap['request_id'] }} ↗</a>
                                    </div>
                                    <p class="mt-1 text-slate-700">{{ $overlap['family'] }} with {{ $overlap['caregiver'] }}</p>
                                    <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                                        <div><dt class="font-semibold text-slate-700">Scheduled</dt><dd class="mt-1 text-slate-600">{{ $overlap['scheduled_start'] ?? 'No scheduled start' }}<br>{{ $overlap['scheduled_end'] ?? 'No scheduled end' }}</dd></div>
                                        <div><dt class="font-semibold text-slate-700">Recorded care · {{ $overlap['worked_minutes'] }} minutes</dt><dd class="mt-1 text-slate-600">{{ $overlap['actual_start'] ?? 'No check-in recorded' }}<br>{{ $overlap['actual_end'] ?? 'No checkout recorded' }}</dd></div>
                                        <div><dt class="font-semibold text-slate-700">Payment · {{ str_replace('_', ' ', $overlap['payment_status'] ?? 'no payment record') }}</dt><dd class="mt-1 text-slate-600">Captured: {{ number_format($overlap['amount_captured_cents'] / 100, 2) }} {{ $overlap['currency'] }}
                                            @if($overlap['amount_refunded_cents'])<br>Refunded: {{ number_format($overlap['amount_refunded_cents'] / 100, 2) }} {{ $overlap['currency'] }}@endif
                                            @if($overlap['amount_authorized_cents'])<br>Authorized: {{ number_format($overlap['amount_authorized_cents'] / 100, 2) }} {{ $overlap['currency'] }}@endif
                                        </dd></div>
                                        @if($overlap['care_hours_status'])<div><dt class="font-semibold text-slate-700">Care hours · {{ str_replace('_', ' ', $overlap['care_hours_status']) }}</dt><dd class="mt-1 text-slate-600">{{ $overlap['care_hours_reserved_minutes'] }} minutes reserved</dd></div>@endif
                                    </dl>
                                </div>
                            @endforeach
                        </div>
                        @unless($hasDuplicate)
                            <label class="mt-4 flex items-start gap-3 text-sm text-slate-800"><input type="checkbox" wire:model="overlapsAcknowledged" class="mt-0.5 rounded border-slate-300 text-cyan-700 focus:ring-cyan-600"><span>I reviewed the overlapping bookings and confirm this is a separate visit requiring its own charge.</span></label>
                            <p class="mt-2 text-xs text-slate-600">Existing bookings and payments stay unchanged. This confirmation and your reason for recording the visit are saved in the audit trail.</p>
                        @endunless
                    </section>
                @endif
                <x-card>
                    <h2 class="text-lg font-bold text-slate-900">Check the completed visit</h2>
                    <dl class="mt-5 grid gap-5 text-sm sm:grid-cols-2">
                        <div><dt class="text-slate-500">Family / payer</dt><dd class="mt-1 font-semibold">{{ $review['family'] }}</dd><dd class="break-words text-slate-500">{{ $review['family_email'] }}</dd></div>
                        <div><dt class="text-slate-500">Caregiver</dt><dd class="mt-1 font-semibold">{{ $review['caregiver'] }}</dd></div>
                        <div><dt class="text-slate-500">Started</dt><dd class="mt-1 font-semibold">{{ $review['start_label'] }}</dd></div>
                        <div><dt class="text-slate-500">Finished</dt><dd class="mt-1 font-semibold">{{ $review['end_label'] }}</dd></div>
                        <div><dt class="text-slate-500">Worked time</dt><dd class="mt-1 font-semibold">{{ $review['input']['worked_minutes'] }} minutes</dd><dd class="text-slate-500">{{ $review['input']['duration_minutes'] }} minutes elapsed, less {{ $review['input']['break_minutes'] }} minutes of unpaid breaks</dd></div>
                        <div><dt class="text-slate-500">Local time zone</dt><dd class="mt-1 font-semibold">{{ $review['input']['timezone'] }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-slate-500">Care recipient & location</dt><dd class="mt-1 font-semibold">{{ $review['input']['recipient_name'] }} · {{ $review['input']['relationship'] }}</dd><dd>{{ $review['input']['address_line1'] }} {{ $review['input']['address_line2'] }}, {{ $review['input']['city'] }}, {{ $review['input']['state'] }} {{ $review['input']['zip'] }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-slate-500">Care provided</dt><dd class="mt-1 font-semibold">{{ $review['input']['title'] }}</dd><dd class="whitespace-pre-line text-slate-600">{{ $review['input']['scope_of_work'] }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-slate-500">Reason for recording</dt><dd class="mt-1 whitespace-pre-line">{{ $review['input']['reason'] }}</dd></div>
                    </dl>
                    <button type="button" wire:click="editVisit" wire:loading.attr="disabled" class="mt-5 text-sm font-semibold text-cyan-700 hover:underline">← Edit visit details</button>
                </x-card>
            </div>
            <x-card>
                <h2 class="font-bold text-slate-900">Charge summary</h2>
                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-600">Care</dt><dd>{{ $money($review['quote']['family_care_amount_cents']) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-600">Family processing fee</dt><dd>{{ $money($review['quote']['family_processing_fee_cents']) }}</dd></div>
                    <div class="flex justify-between gap-3 border-t border-slate-200 pt-4 text-lg font-bold"><dt>Total · USD</dt><dd>{{ $money($review['quote']['total_charge_cents']) }}</dd></div>
                </dl>
                <p class="mt-4 text-xs text-slate-500">Caregiver gross: {{ $money($review['quote']['caregiver_gross_amount_cents']) }}. Net earnings use the saved fee agreement and actual processing fees.</p>
                <p class="mt-3 text-xs text-slate-500">Uses the family’s saved pricing agreement, or current marketplace rates.</p>
                <p class="mt-3 text-xs text-slate-500">Payment uses the family’s saved payment method. If it needs attention, the completed visit stays saved here for retry.</p>
                @if($hasDuplicate)
                    <p class="mt-6 text-sm font-semibold text-red-800">Use the existing request linked above to manage this visit and its payment.</p>
                @else
                    <label class="mt-6 flex items-start gap-3 text-sm text-slate-700"><input type="checkbox" wire:model="acknowledged" class="mt-0.5 rounded border-slate-300 text-cyan-700 focus:ring-cyan-600"><span>I verified that this care happened and approve the hours and {{ $money($review['quote']['total_charge_cents']) }} family charge.</span></label>
                    <button type="submit" wire:loading.attr="disabled" class="mt-5 w-full rounded-lg bg-cyan-700 px-4 py-3 text-sm font-bold text-white hover:bg-cyan-800 disabled:opacity-50"><span wire:loading.remove wire:target="createVisit">Create visit & charge {{ $money($review['quote']['total_charge_cents']) }}</span><span wire:loading wire:target="createVisit">Saving & processing…</span></button>
                @endif
            </x-card>
        </form>
    @else
        <div class="flex items-center gap-2 text-sm"><span class="font-bold text-cyan-800">1. Visit details</span><span aria-hidden="true" class="text-slate-300">/</span><span class="text-slate-500">2. Review & charge</span></div>
        <form wire:submit="reviewVisit" class="space-y-5">
            <fieldset wire:loading.attr="disabled" wire:target="selectFamily,selectCaregiver,loadSourceRequest,reviewVisit" class="space-y-5">
                <x-card>
                    <h2 class="text-lg font-bold text-slate-900">Who was the visit for?</h2>
                    <div class="mt-5 grid gap-6 md:grid-cols-2">
                        <div>
                            <label for="past-family" class="{{ $labelClass }}">Family / payer</label>
                            @if($family)<div class="mt-2 rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm"><p class="font-bold text-cyan-950">{{ $family->owner->name }}</p><p class="break-words text-cyan-800">{{ $family->owner->email }} · Account #{{ $family->id }}</p></div>@endif
                            <input id="past-family" type="search" wire:model.live.debounce.300ms="familySearch" placeholder="Search name or email{{ $family ? ' to change family' : '' }}" autocomplete="off" class="{{ $fieldClass }}">
                            @if($families->isNotEmpty())
                                <div class="mt-2 max-h-48 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100">
                                    @foreach($families as $option)<button type="button" wire:key="past-family-{{ $option->id }}" wire:click="selectFamily({{ $option->id }})" class="block w-full px-3 py-2 text-left text-sm hover:bg-cyan-50 focus:bg-cyan-50"><span class="block font-semibold">{{ $option->owner->name }}</span><span class="block break-words text-xs text-slate-500">{{ $option->owner->email }}</span></button>@endforeach
                                </div>
                            @elseif(!$family || $familySearch !== '')<p class="mt-2 text-sm text-slate-500">No active families match your search.</p>@endif
                        </div>
                        <div>
                            <label for="past-caregiver" class="{{ $labelClass }}">Caregiver</label>
                            @if($caregiver)<div class="mt-2 rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm"><p class="font-bold text-cyan-950">{{ $caregiver->name }}</p><p class="break-words text-cyan-800">{{ $caregiver->email }}</p></div>@endif
                            <input id="past-caregiver" type="search" wire:model.live.debounce.300ms="caregiverSearch" placeholder="Search name or email{{ $caregiver ? ' to change caregiver' : '' }}" autocomplete="off" class="{{ $fieldClass }}">
                            @if($caregivers->isNotEmpty())
                                <div class="mt-2 max-h-48 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100">
                                    @foreach($caregivers as $option)<button type="button" wire:key="past-caregiver-{{ $option->id }}" wire:click="selectCaregiver({{ $option->id }})" class="block w-full px-3 py-2 text-left text-sm hover:bg-cyan-50 focus:bg-cyan-50"><span class="block font-semibold">{{ $option->name }}</span><span class="block break-words text-xs text-slate-500">{{ $option->email }}</span></button>@endforeach
                                </div>
                            @elseif(!$caregiver || $caregiverSearch !== '')<p class="mt-2 text-sm text-slate-500">No active caregivers match your search.</p>@endif
                        </div>
                    </div>
                </x-card>
                <x-card>
                    <h2 class="text-lg font-bold text-slate-900">When did the care happen?</h2>
                    <p class="mt-1 text-sm text-slate-500">Enter the local time at the care location. Overnight end times are calculated automatically.</p>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="{{ $labelClass }}">Visit date<input type="date" wire:model.blur="form.date" required class="{{ $fieldClass }}"></label>
                        <label class="{{ $labelClass }}">Start time<input type="time" wire:model.blur="form.start_time" required class="{{ $fieldClass }}"></label>
                        <div><label for="past-timezone" class="{{ $labelClass }}">Time zone</label><select id="past-timezone" wire:model="form.timezone" class="{{ $fieldClass }}">@foreach($timezones as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                        <label class="{{ $labelClass }}">Total duration (minutes)<input type="number" min="1" max="1440" step="1" wire:model.blur="form.duration_minutes" required class="{{ $fieldClass }}"><span class="mt-1 block text-xs font-normal text-slate-500">60 = 1 hour · 90 = 1½ hours · includes breaks</span></label>
                        <label class="{{ $labelClass }}">Unpaid breaks (minutes)<input type="number" min="0" step="1" wire:model.blur="form.break_minutes" required class="{{ $fieldClass }}"></label>
                        <div class="rounded-xl bg-slate-50 px-4 py-3"><p class="text-sm text-slate-500">Billable worked time</p><p class="mt-1 text-xl font-bold text-slate-900"><output aria-label="Billable worked minutes" x-text="Math.max(0, (Number($wire.form.duration_minutes) || 0) - (Number($wire.form.break_minutes) || 0))">{{ max(0, (int) $form['duration_minutes'] - (int) $form['break_minutes']) }}</output> <span class="text-sm font-medium">minutes</span></p></div>
                    </div>
                    <details class="mt-4 text-xs text-slate-500"><summary class="cursor-pointer">Visit started during a repeated daylight-saving hour?</summary><label class="mt-3 block max-w-sm text-sm">Which occurrence of the start time?<select wire:model="form.start_occurrence" class="{{ $fieldClass }}"><option value="first">First occurrence (before clocks move back)</option><option value="second">Second occurrence (after clocks move back)</option></select></label></details>
                </x-card>
                <x-card>
                    <details @if(!$form['source_request_id']) open @endif>
                        <summary class="cursor-pointer text-lg font-bold text-slate-900">Care details <span class="ml-2 text-sm font-normal text-slate-500">{{ $form['source_request_id'] ? 'Copied from request #'.$form['source_request_id'].' · review or edit' : 'Recipient, location & care provided' }}</span></summary>
                        @if($sources->isNotEmpty())
                            <label class="mt-5 {{ $labelClass }}">Copy details from a previous request<select wire:model="form.source_request_id" wire:change="loadSourceRequest" class="{{ $fieldClass }}"><option value="">Enter care details</option>@foreach($sources as $source)<option value="{{ $source->id }}">#{{ $source->id }} · {{ $source->recipient?->full_name }} · {{ $source->title }}</option>@endforeach</select></label>
                        @endif
                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <label class="{{ $labelClass }}">Care recipient<input type="text" wire:model="form.recipient_name" maxlength="255" class="{{ $fieldClass }}"></label>
                            <label class="{{ $labelClass }}">Relationship to payer<input type="text" wire:model="form.relationship" placeholder="Self, parent, spouse…" maxlength="100" class="{{ $fieldClass }}"></label>
                            <label class="{{ $labelClass }} sm:col-span-2">Visit title<input type="text" wire:model="form.title" maxlength="255" class="{{ $fieldClass }}"></label>
                            <label class="{{ $labelClass }}">Street address<input type="text" wire:model="form.address_line1" maxlength="255" class="{{ $fieldClass }}"></label>
                            <label class="{{ $labelClass }}">Apartment / suite (optional)<input type="text" wire:model="form.address_line2" maxlength="255" class="{{ $fieldClass }}"></label>
                            <label class="{{ $labelClass }}">City<input type="text" wire:model="form.city" maxlength="255" class="{{ $fieldClass }}"></label>
                            <div class="grid grid-cols-2 gap-4"><label class="{{ $labelClass }}">State<input type="text" wire:model="form.state" placeholder="NC" maxlength="2" class="{{ $fieldClass }}"></label><label class="{{ $labelClass }}">ZIP code<input type="text" wire:model="form.zip" maxlength="15" class="{{ $fieldClass }}"></label></div>
                            <label class="{{ $labelClass }} sm:col-span-2">Care provided (optional)<textarea wire:model="form.scope_of_work" rows="3" maxlength="5000" class="{{ $fieldClass }}"></textarea></label>
                        </div>
                        @if($tasks->isNotEmpty())<fieldset class="mt-5"><legend class="text-sm font-semibold text-slate-700">Care tasks (optional)</legend><div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@foreach($tasks as $task)<label class="flex items-start gap-2 text-sm text-slate-600"><input type="checkbox" wire:model="form.task_ids" value="{{ $task->id }}" class="mt-0.5 rounded border-slate-300 text-cyan-700 focus:ring-cyan-600">{{ $task->name }}</label>@endforeach</div></fieldset>@endif
                    </details>
                    @if($form['source_request_id'])<p class="mt-3 text-sm text-slate-600">{{ $form['recipient_name'] }} · {{ $form['address_line1'] }}, {{ $form['city'] }}, {{ $form['state'] }}</p>@endif
                </x-card>
                <x-card>
                    <label for="past-reason" class="{{ $labelClass }}">Why is this visit being recorded now?</label>
                    <textarea id="past-reason" wire:model="form.reason" rows="2" minlength="8" maxlength="2000" required placeholder="For example: Family and caregiver confirmed the visit happened, but it was never booked." class="{{ $fieldClass }}"></textarea>
                    <p class="mt-2 text-xs text-slate-500">Saved in the administrator audit trail with your name.</p>
                </x-card>
                <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between"><p class="text-sm text-slate-500">Reviewing does not create a booking or charge the family.</p><button type="submit" class="w-full rounded-lg bg-cyan-700 px-5 py-3 text-sm font-bold text-white hover:bg-cyan-800 disabled:opacity-50 sm:w-auto"><span wire:loading.remove wire:target="reviewVisit">Review visit & charge →</span><span wire:loading wire:target="reviewVisit">Checking visit…</span></button></div>
            </fieldset>
        </form>
    @endif

    @if($recentVisits->isNotEmpty())
        <section class="border-t border-slate-200 pt-6" aria-labelledby="recent-heading">
            <h2 id="recent-heading" class="text-base font-bold text-slate-900">Recently recorded visits</h2>
            <p class="mt-1 text-sm text-slate-500">Reopen a saved visit to check payment or resume settlement.</p>
            <div class="mt-4 divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
                @foreach($recentVisits as $recent)
                    <a href="{{ route('admin.requests.recorded-visit', $recent->id) }}" wire:navigate class="flex flex-col gap-2 px-4 py-3 text-sm hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between">
                        <div><p class="font-semibold text-slate-900">{{ $recent->booking->family->name }} · {{ $recent->booking->caregiver->name }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $recent->input['date'] }} · {{ $recent->input['worked_minutes'] }} worked minutes · Booking #{{ $recent->care_booking_id }}</p></div>
                        <span class="text-xs font-semibold text-cyan-800">{{ $recent->payment_mode !== 'live' ? 'Test · ' : '' }}{{ str_replace('_', ' ', $recent->booking->payment?->status ?? 'payment pending') }} →</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
