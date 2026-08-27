<div class="hc-page space-y-6 pb-28 pt-6 sm:pb-8 sm:pt-8">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @php
        $dayOptions = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
        $next = $plan->nextBooking;
        $nextPayment = $next?->payment;
        $paymentNeedsAction = $nextPayment?->requiresFamilyAction() ?? false;
        $paymentProtected = $nextPayment && in_array($nextPayment->status, [
            \App\Models\CareBookingPayment::STATUS_AUTHORIZED,
            \App\Models\CareBookingPayment::STATUS_CAPTURED,
            \App\Models\CareBookingPayment::STATUS_TRANSFERRED,
        ], true);
        $address = $plan->address_snapshot ?? [];
        $tasks = collect($plan->task_snapshot ?? []);
        $isPaused = $plan->status === \App\Models\CarePlan::STATUS_PAUSED;
        $isEnded = in_array($plan->status, [\App\Models\CarePlan::STATUS_ENDED, \App\Models\CarePlan::STATUS_CANCELLED], true);
    @endphp

    <header data-ai-target="family.regular_care.attention" tabindex="-1" class="flex flex-col gap-4 outline-none sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('family.care.index') }}" wire:navigate class="text-lg font-semibold text-[#0F5B52] underline underline-offset-4">Back to your care</a>
            <h1 class="mt-3 font-display text-3xl font-semibold leading-tight text-[#17313F]">Recurring care with {{ $plan->caregiver?->name }}</h1>
            <p class="mt-2 text-lg text-[#526474]">{{ $scheduleLabel }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('family.care.journey', ['resourceType' => 'regular', 'resourceId' => $plan->id]) }}" wire:navigate><x-button color="white" light>Care story</x-button></a>
            <a href="{{ route('family.care.history', ['plan' => $plan->id]) }}" wire:navigate><x-button color="white" light>View past visits</x-button></a>
            @if ($plan->source_care_request_id)
                <a href="{{ route('family.requests.show', $plan->source_care_request_id) }}" wire:navigate><x-button color="blue" light>Message caregiver</x-button></a>
            @endif
            <span class="inline-flex min-h-11 items-center self-start rounded-full px-4 text-sm font-semibold {{ $isEnded ? 'bg-slate-100 text-slate-700' : ($isPaused ? 'bg-sky-100 text-sky-800' : 'bg-emerald-100 text-emerald-800') }}">
                {{ $isEnded ? 'Recurring care ended' : ($isPaused ? 'Care is paused' : 'Recurring care is active') }}
            </span>
        </div>
    </header>

    @if ($careProfileSnapshot)
        <x-care-recipient-profile-summary :snapshot="$careProfileSnapshot" />
    @endif

    @include('livewire.family.partials.completed-extra-visits')

    @if ($pendingTimeCorrections->isNotEmpty())
        <section class="rounded-3xl border-2 border-amber-300 bg-amber-50 p-5" aria-labelledby="regular-time-corrections-heading">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-800">Your review is needed</p>
            <h2 id="regular-time-corrections-heading" class="mt-1 font-display text-2xl font-semibold text-[#17313F]">{{ $pendingTimeCorrections->count() }} recurring care visit{{ $pendingTimeCorrections->count() === 1 ? '' : 's' }} need{{ $pendingTimeCorrections->count() === 1 ? 's' : '' }} attention</h2>
            <div class="mt-4 grid gap-3">
                @foreach ($pendingTimeCorrections as $correction)
                    <a href="{{ route('family.requests.show', ['careRequest' => $correction->booking->care_request_id, 'tab' => 'shift']) }}" wire:navigate class="flex min-h-12 flex-col justify-center rounded-2xl border border-amber-200 bg-white px-4 py-3 text-[#17313F] transition hover:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-600 sm:flex-row sm:items-center sm:justify-between">
                        <span class="font-semibold">Visit #{{ $correction->care_booking_id }} · {{ $correction->booking->scheduled_start_at?->format('M j') }} · {{ $correction->durationLabel() }}</span>
                        <span class="mt-1 text-sm font-semibold text-[#0F6B62] sm:mt-0">{{ $correction->status === \App\Models\CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED ? 'Confirm payment' : 'Review corrected hours' }} →</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($next)
        <section class="overflow-hidden rounded-lg border border-[#CFC5B8] bg-white shadow-sm">
            <div class="border-b border-[#E7E0D8] bg-[#F3F8F5] px-5 py-4 sm:px-7">
                <p class="text-sm font-bold uppercase tracking-wide text-[#0F6B5B]">Next visit</p>
                <h2 class="mt-1 font-display text-2xl font-semibold text-[#17313F]">{{ $next->scheduled_start_at?->format('l, F j') }}</h2>
                <p class="mt-1 text-xl font-semibold text-[#324457]">{{ $next->scheduled_start_at?->format('g:i A') }} to {{ $next->scheduled_end_at?->format('g:i A') }}</p>
            </div>
            <div class="grid gap-5 px-5 py-5 sm:grid-cols-2 sm:px-7 lg:grid-cols-3">
                <div>
                    <p class="text-sm font-semibold text-[#6A7784]">Who is coming</p>
                    <p class="mt-1 text-lg font-semibold text-[#17313F]">{{ $plan->caregiver?->name }}</p>
                    <p class="text-lg text-[#526474]">For {{ $plan->recipientName() }}</p>
                </div>
                <div>
                    <p class="text-sm font-semibold text-[#6A7784]">Where</p>
                    <p class="mt-1 text-lg font-semibold text-[#17313F]">{{ data_get($address, 'address_line1') }}</p>
                    <p class="text-lg text-[#526474]">{{ data_get($address, 'city') }}, {{ data_get($address, 'state') }} {{ data_get($address, 'zip') }}</p>
                </div>
                <div>
                    <p class="text-sm font-semibold text-[#6A7784]">Payment</p>
                    @if ($paymentNeedsAction)
                        <p class="mt-1 text-lg font-semibold text-amber-800">Payment needs attention</p>
                        <a href="{{ route('family.requests.show', $next->care_request_id) }}" wire:navigate class="mt-2 inline-flex min-h-12 items-center rounded-md bg-amber-600 px-4 text-lg font-semibold text-white">Confirm payment</a>
                    @elseif ($paymentProtected)
                        <p class="mt-1 text-lg font-semibold text-emerald-700">Payment confirmed</p>
                        <p class="text-lg text-[#526474]">No action needed.</p>
                    @else
                        <p class="mt-1 text-lg font-semibold text-[#17313F]">Card saved</p>
                        <p class="text-lg text-[#526474]">Payment is confirmed closer to the visit.</p>
                    @endif
                </div>
            </div>
            <div class="flex flex-col gap-3 border-t border-[#E7E0D8] px-5 py-4 sm:flex-row sm:px-7">
                <a href="{{ route('family.requests.show', $next->care_request_id) }}" wire:navigate class="hc-primary-button min-h-12 text-lg">View visit details</a>
                @if (!$isEnded)
                    <button type="button" wire:click="skipVisit({{ $next->id }})" wire:confirm="{{ $next->scheduled_start_at?->lte(now()->addHours(24)) ? 'This visit is inside the 24-hour cancellation window. Skip it anyway?' : 'Skip this visit? Your other regular visits will continue.' }}" class="hc-secondary-button min-h-12 text-lg">Skip this visit</button>
                @endif
            </div>
        </section>
    @elseif (!$isEnded)
        <x-alert color="blue">Your regular schedule is active. The next visit is being prepared.</x-alert>
    @endif

    @if ($plan->pendingScheduleChanges->isNotEmpty())
        <section class="rounded-lg border border-amber-300 bg-amber-50 p-5">
            <h2 class="font-display text-xl font-semibold text-amber-950">Waiting for {{ $plan->caregiver?->name }}</h2>
            <p class="mt-1 text-lg text-amber-900">{{ $plan->pendingScheduleChanges->count() }} change request{{ $plan->pendingScheduleChanges->count() === 1 ? '' : 's' }} waiting for a response. Your current visits remain scheduled.</p>
        </section>
    @endif

    <section class="rounded-lg border border-[#D8D0C5] bg-white">
        <div class="border-b border-[#E7E0D8] px-5 py-4 sm:px-7">
            <h2 class="font-display text-2xl font-semibold text-[#17313F]">Later visits</h2>
            <p class="mt-1 text-lg text-[#526474]">After your next visit, these are the visits already on the calendar.</p>
        </div>
        <div class="divide-y divide-[#E7E0D8]">
            @forelse ($laterVisits as $visit)
                @php
                    $booking = $visit['booking'];
                    $payment = $booking?->payment;
                    $needsPayment = $payment?->requiresFamilyAction() ?? false;
                @endphp
                <article class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                    <div>
                        <p class="text-lg font-semibold text-[#17313F]">{{ $visit['start']->format('l, F j') }}</p>
                        <p class="mt-1 text-lg text-[#526474]">{{ $visit['start']->format('g:i A') }} to {{ $visit['end']->format('g:i A') }} with {{ $plan->caregiver?->name }}</p>
                        <p class="mt-2 text-sm font-semibold {{ $needsPayment ? 'text-amber-800' : 'text-emerald-700' }}">
                            {{ $needsPayment ? 'Payment needs attention' : ($payment ? 'Payment confirmed' : 'Payment checked 48 hours before') }}
                            @if ($booking?->plan_visit_kind === 'extra') - Extra visit @endif
                        </p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        @if ($booking)
                            <a href="{{ route('family.requests.show', $booking->care_request_id) }}" wire:navigate class="{{ $needsPayment ? 'inline-flex min-h-12 items-center justify-center rounded-md bg-amber-600 px-4 text-lg font-semibold text-white' : 'hc-secondary-button min-h-12 text-lg' }}">{{ $needsPayment ? 'Fix payment' : 'View details' }}</a>
                        @endif
                        @if ($booking && !$isEnded && $booking->status === \App\Models\CareBooking::STATUS_SCHEDULED)
                            <button type="button" wire:click="skipVisit({{ $booking->id }})" wire:confirm="{{ $booking->scheduled_start_at?->lte(now()->addHours(24)) ? 'This visit is inside the 24-hour cancellation window. Skip it anyway?' : 'Skip this visit? Your regular schedule will continue.' }}" class="min-h-12 rounded-md border border-[#B85C4A] px-4 text-lg font-semibold text-[#9C3E30]">Skip</button>
                        @endif
                    </div>
                </article>
            @empty
                <p class="px-5 py-8 text-lg text-[#526474] sm:px-7">No later visits are scheduled yet.</p>
            @endforelse
        </div>
    </section>

    @if (!$isEnded)
        <section id="manage-regular-care" class="scroll-mt-24 rounded-lg border border-[#D8D0C5] bg-white">
            <div class="border-b border-[#E7E0D8] px-5 py-4 sm:px-7">
                <h2 class="font-display text-2xl font-semibold text-[#17313F]">Manage recurring care</h2>
                <p class="mt-1 text-lg text-[#526474]">Choose one change. We will explain what happens before you confirm.</p>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-2 sm:p-7 lg:grid-cols-4">
                <button type="button" wire:click="openManagePanel('extra')" class="min-h-14 rounded-md border border-[#B9CDC5] px-4 text-left text-lg font-semibold text-[#174C43] hover:bg-[#F1F7F4]">Add an extra visit</button>
                <button type="button" wire:click="openManagePanel('schedule')" class="min-h-14 rounded-md border border-[#B9CDC5] px-4 text-left text-lg font-semibold text-[#174C43] hover:bg-[#F1F7F4]">Change future schedule</button>
                @if ($isPaused)
                    <button type="button" wire:click="resumePlan" class="min-h-14 rounded-md border border-[#B9CDC5] px-4 text-left text-lg font-semibold text-[#174C43] hover:bg-[#F1F7F4]">Resume recurring care</button>
                @else
                    <button type="button" wire:click="openManagePanel('pause')" class="min-h-14 rounded-md border border-[#B9CDC5] px-4 text-left text-lg font-semibold text-[#174C43] hover:bg-[#F1F7F4]">Pause recurring care</button>
                @endif
                <button type="button" wire:click="openManagePanel('end')" class="min-h-14 rounded-md border border-[#D9AEA5] px-4 text-left text-lg font-semibold text-[#913E31] hover:bg-rose-50">End recurring care</button>
            </div>

            @if ($managePanel === 'extra')
                <form wire:submit="requestExtraVisit" class="border-t border-[#E7E0D8] bg-[#F8FAF8] p-5 sm:p-7">
                    <h3 class="font-display text-xl font-semibold text-[#17313F]">Ask for one extra visit</h3>
                    <p class="mt-1 text-lg text-[#526474]">{{ $plan->caregiver?->name }} must accept before this becomes a booked visit.</p>
                    <div class="mt-5 grid gap-4 md:grid-cols-3">
                        <label class="text-lg font-semibold text-[#263C48]">Day<input type="date" wire:model="extraVisitDate" class="mt-2 min-h-12 w-full rounded-md border-[#BFC8CE] text-lg"></label>
                        <label class="text-lg font-semibold text-[#263C48]">Start time<input type="time" wire:model="extraVisitTime" class="mt-2 min-h-12 w-full rounded-md border-[#BFC8CE] text-lg"></label>
                        <label class="text-lg font-semibold text-[#263C48]">How long<select wire:model="extraVisitDuration" class="mt-2 min-h-12 w-full rounded-md border-[#BFC8CE] text-lg">@foreach ([60,90,120,150,180,240,300,360,420,480] as $minutes)<option value="{{ $minutes }}">{{ intdiv($minutes, 60) }}{{ $minutes % 60 ? ' hr 30 min' : ($minutes === 60 ? ' hour' : ' hours') }}</option>@endforeach</select></label>
                    </div>
                    <label class="mt-4 block text-lg font-semibold text-[#263C48]">Optional note<textarea wire:model="extraVisitNote" rows="3" class="mt-2 w-full rounded-md border-[#BFC8CE] text-lg"></textarea></label>
                    <x-input-error :messages="$errors->get('extraVisitDate')" class="mt-2" /><x-input-error :messages="$errors->get('extraVisitTime')" class="mt-2" />
                    <div class="mt-5 flex flex-col gap-3 sm:flex-row"><x-button color="green" class="min-h-12 text-lg">Send request</x-button><button type="button" wire:click="openManagePanel('')" class="hc-secondary-button min-h-12 text-lg">Cancel</button></div>
                </form>
            @elseif ($managePanel === 'schedule')
                <form wire:submit="requestScheduleChange" class="border-t border-[#E7E0D8] bg-[#F8FAF8] p-5 sm:p-7">
                    <h3 class="font-display text-xl font-semibold text-[#17313F]">Change future schedule</h3>
                    <p class="mt-1 text-lg text-[#526474]">Current visits remain unchanged until {{ $plan->caregiver?->name }} accepts.</p>
                    <fieldset class="mt-5"><legend class="text-lg font-semibold text-[#263C48]">New care days</legend><div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">@foreach ($dayOptions as $value => $label)<label class="flex min-h-12 items-center gap-2 rounded-md border border-[#C9D1D4] bg-white px-3 text-lg"><input type="checkbox" wire:model.live="scheduleDays" value="{{ $value }}" class="h-5 w-5">{{ substr($label, 0, 3) }}</label>@endforeach</div></fieldset>
                    <div class="mt-4 space-y-3">
                        @foreach (collect($scheduleDays)->map(fn ($day) => (int) $day)->unique()->sort() as $day)
                            <div wire:key="change-schedule-day-{{ $day }}" class="rounded-xl border border-[#C9D1D4] bg-white p-4">
                                <div class="grid gap-4 md:grid-cols-[8rem_1fr_1fr] md:items-end">
                                    <p class="font-display text-lg font-semibold text-[#17313F]">{{ $dayOptions[$day] ?? 'Day' }}</p>
                                    <label class="text-lg font-semibold text-[#263C48]">Starts at<input type="time" wire:model="scheduleSlots.{{ $day }}.start_time" class="mt-2 min-h-12 w-full rounded-md border-[#BFC8CE] text-lg"></label>
                                    <label class="text-lg font-semibold text-[#263C48]">Ends at<input type="time" wire:model="scheduleSlots.{{ $day }}.end_time" class="mt-2 min-h-12 w-full rounded-md border-[#BFC8CE] text-lg"></label>
                                </div>
                                <x-input-error :messages="$errors->get('scheduleSlots.'.$day.'.start_time')" class="mt-2" />
                                <x-input-error :messages="$errors->get('scheduleSlots.'.$day.'.end_time')" class="mt-2" />
                            </div>
                        @endforeach
                    </div>
                    <label class="mt-4 block text-lg font-semibold text-[#263C48]">Start new schedule on<input type="date" wire:model="scheduleEffectiveOn" class="mt-2 min-h-12 w-full rounded-md border-[#BFC8CE] text-lg"></label>
                    <label class="mt-4 block text-lg font-semibold text-[#263C48]">Optional note<textarea wire:model="scheduleNote" rows="3" class="mt-2 w-full rounded-md border-[#BFC8CE] text-lg"></textarea></label>
                    <x-input-error :messages="$errors->get('scheduleDays')" class="mt-2" /><x-input-error :messages="$errors->get('scheduleEffectiveOn')" class="mt-2" />
                    <div class="mt-5 flex flex-col gap-3 sm:flex-row"><x-button color="green" class="min-h-12 text-lg">Send schedule change</x-button><button type="button" wire:click="openManagePanel('')" class="hc-secondary-button min-h-12 text-lg">Cancel</button></div>
                </form>
            @elseif ($managePanel === 'pause')
                <form wire:submit="pausePlan" class="border-t border-[#E7E0D8] bg-[#F8FAF8] p-5 sm:p-7">
                    <h3 class="font-display text-xl font-semibold text-[#17313F]">Pause recurring care</h3><p class="mt-1 text-lg text-[#526474]">Visits during the pause will be cancelled. You can choose a return date or resume later.</p>
                    <div class="mt-5 grid gap-4 md:grid-cols-2"><label class="text-lg font-semibold text-[#263C48]">Pause starting<input type="date" wire:model="pauseFrom" class="mt-2 min-h-12 w-full rounded-md border-[#BFC8CE] text-lg"></label><label class="text-lg font-semibold text-[#263C48]">Return date (optional)<input type="date" wire:model="resumeOn" class="mt-2 min-h-12 w-full rounded-md border-[#BFC8CE] text-lg"></label></div>
                    <x-input-error :messages="$errors->get('pauseFrom')" class="mt-2" /><x-input-error :messages="$errors->get('resumeOn')" class="mt-2" />
                    <div class="mt-5 flex flex-col gap-3 sm:flex-row"><x-button color="green" class="min-h-12 text-lg">Pause care</x-button><button type="button" wire:click="openManagePanel('')" class="hc-secondary-button min-h-12 text-lg">Cancel</button></div>
                </form>
            @elseif ($managePanel === 'end')
                <div class="border-t border-[#E7E0D8] bg-rose-50 p-5 sm:p-7">
                    <h3 class="font-display text-xl font-semibold text-[#612A22]">End recurring care?</h3><p class="mt-2 text-lg text-[#713C34]">No new visits will be created. By default, your next confirmed visit still happens.</p>
                    <label class="mt-4 flex min-h-12 items-center gap-3 rounded-md border border-rose-200 bg-white px-4 text-lg text-[#612A22]"><input type="checkbox" wire:model="cancelNextWhenEnding" class="h-5 w-5">Also cancel the next confirmed visit</label>
                    <div class="mt-5 flex flex-col gap-3 sm:flex-row"><x-button color="red" wire:click="endPlan" wire:confirm="End recurring care?" class="min-h-12 text-lg">End recurring care</x-button><button type="button" wire:click="openManagePanel('')" class="hc-secondary-button min-h-12 text-lg">Keep recurring care</button></div>
                </div>
            @endif
        </section>
    @endif

    <details class="rounded-lg border border-[#D8D0C5] bg-white p-5 sm:p-7">
        <summary class="cursor-pointer text-lg font-semibold text-[#17313F]">Care details and instructions</summary>
        <div class="mt-5 grid gap-6 md:grid-cols-2">
            <div><h3 class="text-lg font-semibold text-[#17313F]">Care location</h3><p class="mt-1 text-lg text-[#526474]">{{ data_get($address, 'address_line1') }}{{ data_get($address, 'address_line2') ? ', '.data_get($address, 'address_line2') : '' }}<br>{{ data_get($address, 'city') }}, {{ data_get($address, 'state') }} {{ data_get($address, 'zip') }}</p></div>
            <div><h3 class="text-lg font-semibold text-[#17313F]">Care notes</h3><p class="mt-1 text-lg text-[#526474]">{{ $plan->care_notes ?: 'No extra notes.' }}</p></div>
            <div class="md:col-span-2"><h3 class="text-lg font-semibold text-[#17313F]">Tasks</h3><ul class="mt-2 grid gap-2 sm:grid-cols-2">@forelse ($tasks as $task)<li class="rounded-md bg-[#F5F2ED] px-4 py-3 text-lg text-[#324457]">{{ $task['name'] ?? 'Care task' }}</li>@empty<li class="text-lg text-[#526474]">No task list added.</li>@endforelse</ul></div>
        </div>
    </details>

    <div class="hc-mobile-primary-bar fixed inset-x-0 bottom-0 z-40 border-t border-[#D8D0C5] bg-[#FFFCF8]/95 p-3 shadow-[0_-8px_24px_rgba(23,49,63,0.12)] backdrop-blur sm:hidden">
        @if ($pendingTimeCorrections->isNotEmpty())
            @php $mobileCorrection = $pendingTimeCorrections->first(); @endphp
            <a href="{{ route('family.requests.show', ['careRequest' => $mobileCorrection->booking->care_request_id, 'tab' => 'shift']) }}" wire:navigate class="hc-primary-button w-full">{{ $mobileCorrection->status === \App\Models\CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED ? 'Confirm visit payment' : 'Review reported hours' }}</a>
        @elseif ($paymentNeedsAction && $next?->care_request_id)
            <a href="{{ route('family.requests.show', $next->care_request_id) }}" wire:navigate class="hc-primary-button w-full">Fix payment to protect next visit</a>
        @elseif (! $isEnded)
            <a href="#manage-regular-care" class="hc-primary-button w-full">Manage recurring care</a>
        @else
            <a href="{{ route('family.care.journey', ['resourceType' => 'regular', 'resourceId' => $plan->id]) }}" wire:navigate class="hc-primary-button w-full">View care story</a>
        @endif
    </div>
</div>
