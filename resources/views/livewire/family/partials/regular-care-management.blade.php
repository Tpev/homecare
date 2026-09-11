    @if ($canManage)
        <section id="manage-regular-care" class="scroll-mt-24 rounded-lg border border-[#D8D0C5] bg-white">
            <div class="border-b border-[#E7E0D8] px-5 py-4 sm:px-7">
                <h2 class="font-display text-2xl font-semibold text-[#17313F]">Manage recurring care</h2>
                <p class="mt-1 text-lg text-[#526474]">Choose one change. We will explain what happens before you confirm.</p>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-2 sm:p-7 lg:grid-cols-4">
                <button type="button" wire:click="openManagePanel('extra')" class="min-h-14 rounded-md border border-[#B9CDC5] px-4 text-left text-lg font-semibold text-[#174C43] hover:bg-[#F1F7F4]">Add an extra visit</button>
                <button type="button" wire:click="openManagePanel('schedule')" class="min-h-14 rounded-md border border-[#B9CDC5] px-4 text-left text-lg font-semibold text-[#174C43] hover:bg-[#F1F7F4]">Change future schedule</button>
                @if ($isPaused || $plan->pause_starts_on)
                    <button type="button" wire:click="resumePlan" wire:loading.attr="disabled" class="min-h-14 rounded-md border border-[#B9CDC5] px-4 text-left text-lg font-semibold text-[#174C43] hover:bg-[#F1F7F4]">{{ $isPaused ? 'Resume recurring care' : 'Cancel scheduled pause' }}</button>
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
                    <x-input-error :messages="$errors->get('extraVisitDuration')" class="mt-2" /><x-input-error :messages="$errors->get('extraVisitNote')" class="mt-2" />
                    <div class="mt-5 flex flex-col gap-3 sm:flex-row"><x-button color="green" class="min-h-12 text-lg">Send request</x-button><button type="button" wire:click="openManagePanel('')" class="hc-secondary-button min-h-12 text-lg">Cancel</button></div>
                </form>
            @elseif ($managePanel === 'schedule')
                <form wire:submit="requestScheduleChange" class="border-t border-[#E7E0D8] bg-[#F8FAF8] p-5 sm:p-7">
                    <h3 class="font-display text-xl font-semibold text-[#17313F]">Change future schedule</h3>
                    <p class="mt-1 text-lg text-[#526474]">Current visits remain unchanged until {{ $plan->caregiver?->name }} accepts. After acceptance, scheduled visits from the effective date are cancelled and the new regular schedule is booked. This also cancels extra visits on or after that date. Earlier visits and their records stay unchanged.</p>
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
                    <x-input-error :messages="$errors->get('scheduleNote')" class="mt-2" />
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
                    @php($keptVisit = $upcomingVisits->first(fn ($booking) => $booking->status === \App\Models\CareBooking::STATUS_SCHEDULED && $booking->scheduled_start_at?->isFuture()))
                    <h3 class="font-display text-xl font-semibold text-[#612A22]">End recurring care?</h3><p class="mt-2 text-lg text-[#713C34]">No new visits will be created. All later scheduled visits will be cancelled. By default, the next future booked visit is kept. Past and current visits keep their hours, payment and reviews.</p>
                    @if($keptVisit)<p class="mt-4 rounded-xl border border-rose-300 bg-white p-4 text-[#612A22]"><strong>Kept by default:</strong> {{ $keptVisit->scheduled_start_at->copy()->setTimezone($timezone)->format('l, M j · g:i A') }} · Visit #{{ $keptVisit->id }}.</p>@else<p class="mt-4 text-[#612A22]">There is no future booked visit to keep.</p>@endif
                    <label class="mt-4 flex min-h-12 items-center gap-3 rounded-md border border-rose-200 bg-white px-4 text-lg text-[#612A22]"><input type="checkbox" wire:model="cancelNextWhenEnding" class="h-5 w-5">Also cancel the next confirmed visit</label>
                    <div class="mt-5 flex flex-col gap-3 sm:flex-row"><x-button color="red" wire:click="endPlan" wire:confirm="End recurring care?" class="min-h-12 text-lg">End recurring care</x-button><button type="button" wire:click="openManagePanel('')" class="hc-secondary-button min-h-12 text-lg">Keep recurring care</button></div>
                </div>
            @endif
        </section>
    @endif
