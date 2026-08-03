<div class="hc-page space-y-5 py-5 sm:space-y-6 sm:py-8">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @php
        $statusMeta = [
            'uncovered' => ['Caregiver needed', 'bg-rose-100 text-rose-800 border-rose-200', '○'],
            'offer_pending' => ['Offer pending', 'bg-amber-100 text-amber-800 border-amber-200', '◷'],
            'awaiting_family_confirmation' => ['Your confirmation', 'bg-violet-100 text-violet-800 border-violet-200', '!'],
            'confirmed' => ['Confirmed', 'bg-emerald-100 text-emerald-800 border-emerald-200', '✓'],
            'in_progress' => ['In progress', 'bg-sky-100 text-sky-800 border-sky-200', '▶'],
            'completed' => ['Completed', 'bg-slate-100 text-slate-700 border-slate-200', '✓'],
            'replacement_needed' => ['Replacement needed', 'bg-rose-100 text-rose-800 border-rose-200', '!'],
            'cancelled' => ['Cancelled', 'bg-slate-100 text-slate-600 border-slate-200', '×'],
            'payment_attention' => ['Payment attention', 'bg-amber-100 text-amber-800 border-amber-200', '!'],
            'replaced' => ['Replaced', 'bg-violet-100 text-violet-800 border-violet-200', '↻'],
            'missed' => ['Missed', 'bg-rose-100 text-rose-800 border-rose-200', '!'],
            'disputed' => ['Disputed', 'bg-amber-100 text-amber-800 border-amber-200', '!'],
        ];
        $dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    @endphp

    <section class="hc-brand-panel">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <a href="{{ route('family.continuous-coverage.index') }}" wire:navigate class="text-sm font-semibold text-white/85 underline underline-offset-4">Back to Continuous Coverage</a>
                <p class="mt-5 hc-brand-kicker text-[#E8E0FF]">{{ $plan->coverage_pattern === '24_7' ? '24/7 Continuous Coverage' : 'Continuous Coverage' }}</p>
                <h1 class="mt-1 font-display text-3xl font-semibold text-white sm:text-4xl">{{ $plan->title }}</h1>
                <p class="mt-2 text-base text-[#F7F1E8]/82">Care for {{ $plan->recipientName() }} · {{ $plan->timezone }}</p>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-white/20 bg-white/10 p-3"><p class="text-xs text-white/70">Covered</p><p class="mt-1 text-xl font-semibold">{{ number_format($summary['covered_minutes']/60, 0) }}/{{ number_format($summary['required_minutes']/60, 0) }}h</p></div>
                <div class="rounded-2xl border border-white/20 bg-white/10 p-3"><p class="text-xs text-white/70">Needs attention</p><p class="mt-1 text-xl font-semibold">{{ $attention }}</p></div>
                <div class="col-span-2 rounded-2xl border border-white/20 bg-white/10 p-3 sm:col-span-1"><p class="text-xs text-white/70">Next</p><p class="mt-1 text-sm font-semibold">{{ $nextShift?->scheduled_start_at?->copy()->setTimezone($plan->timezone)->format('D g:i A') ?: 'None' }}</p></div>
            </div>
        </div>
    </section>

    <nav class="overflow-x-auto rounded-2xl border border-[#DED6CA] bg-white p-1" aria-label="Coverage plan sections">
        <div class="flex min-w-max gap-1">
            @foreach(['overview'=>'Overview','calendar'=>'Calendar','team'=>'Care team','history'=>'History','billing'=>'Billing','settings'=>'Plan settings'] as $key => $label)
                <button type="button" wire:click="setTab('{{ $key }}')" class="min-h-11 rounded-xl px-4 text-sm font-semibold transition {{ $tab === $key ? 'bg-[#0F3D3E] text-white' : 'text-[#526474] hover:bg-[#F7F2EA]' }}" @if($tab === $key) aria-current="page" @endif>
                    {{ $label }}
                    @if($key === 'team' && ($applicants->count() + $pendingLaneRequests->count()) > 0)
                        <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-900">{{ $applicants->count() + $pendingLaneRequests->count() }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    </nav>

    @if ($tab === 'overview')
        <section class="grid gap-5 lg:grid-cols-3">
            <div class="rounded-3xl border border-[#DED6CA] bg-white p-5 lg:col-span-2">
                @php
                    $hasRequiredCoverage = $summary['required_minutes'] > 0;
                    $coverageHeadline = ! $hasRequiredCoverage
                        ? 'No coverage scheduled this week'
                        : ($summary['overlap_minutes'] > 0
                            ? 'Schedule overlap needs review'
                            : ($summary['percent'] === 100 ? 'Coverage is filled' : 'Coverage still has gaps'));
                    $coverageHealthy = $hasRequiredCoverage && $summary['percent'] === 100 && $summary['overlap_minutes'] === 0;
                @endphp
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-[#2F6F62]">This week</p>
                        <h2 class="mt-1 font-display text-2xl font-semibold text-[#17313F]">{{ $coverageHeadline }}</h2>
                    </div>
                    <span class="text-3xl font-semibold {{ ! $hasRequiredCoverage ? 'text-slate-500' : ($coverageHealthy ? 'text-emerald-700' : 'text-rose-700') }}">{{ $summary['percent'] }}%</span>
                </div>
                <div class="mt-4 h-3 overflow-hidden rounded-full bg-[#F0E9E1]" role="progressbar" aria-valuenow="{{ $summary['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $hasRequiredCoverage ? 'Weekly coverage '.$summary['percent'].' percent' : 'No required coverage scheduled this week' }}"><div class="h-full {{ ! $hasRequiredCoverage ? 'bg-slate-300' : ($coverageHealthy ? 'bg-emerald-500' : 'bg-amber-500') }}" style="width: {{ $summary['percent'] }}%"></div></div>
                <div class="mt-5 grid gap-3 sm:grid-cols-3"><div class="rounded-2xl bg-[#F7F2EA] p-4"><p class="text-sm text-[#607080]">Required</p><p class="mt-1 text-xl font-semibold">{{ number_format($summary['required_minutes']/60, 1) }}h</p></div><div class="rounded-2xl bg-[#EAF6F2] p-4"><p class="text-sm text-[#607080]">Confirmed</p><p class="mt-1 text-xl font-semibold text-emerald-800">{{ number_format($summary['covered_minutes']/60, 1) }}h</p></div><div class="rounded-2xl bg-[#FFF0ED] p-4"><p class="text-sm text-[#607080]">Still open</p><p class="mt-1 text-xl font-semibold text-rose-800">{{ number_format($summary['uncovered_minutes']/60, 1) }}h</p></div></div>
                @if($summary['overlap_minutes'] > 0)<div role="alert" class="mt-4 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950"><span class="font-semibold">Overlapping coverage:</span> {{ number_format($summary['overlap_minutes']/60, 1) }} hours contain more than one scheduled shift. Open the calendar to review the handoff.</div>@endif
                <button type="button" wire:click="setTab('calendar')" class="mt-5 min-h-12 rounded-xl bg-[#0F3D3E] px-5 font-semibold text-white">Open coverage calendar</button>
            </div>
            <aside class="space-y-5">
                <div class="rounded-3xl border border-[#DED6CA] bg-white p-5"><p class="text-sm font-semibold uppercase tracking-wide text-[#7B8794]">Next shift</p><p class="mt-2 font-display text-xl font-semibold">{{ $nextShift?->scheduled_start_at?->copy()->setTimezone($plan->timezone)->format('l, F j') ?: 'No upcoming shift' }}</p><p class="mt-1 text-[#607080]">{{ $nextShift?->scheduled_start_at?->copy()->setTimezone($plan->timezone)->format('g:i A') }}{{ $nextShift?->scheduled_end_at ? ' – '.$nextShift->scheduled_end_at->copy()->setTimezone($plan->timezone)->format('g:i A') : '' }}</p><p class="mt-3 font-semibold {{ $nextShift?->assignedCaregiver ? 'text-[#17313F]' : 'text-rose-700' }}">{{ $nextShift?->assignedCaregiver?->name ?: 'Caregiver needed' }}</p></div>
                <div class="rounded-3xl border border-[#DED6CA] bg-white p-5"><p class="font-display text-xl font-semibold">Family-approved team</p><p class="mt-2 text-sm text-[#607080]">{{ $activeRoster->count() }} active caregiver{{ $activeRoster->count() === 1 ? '' : 's' }}</p><button type="button" wire:click="setTab('team')" class="mt-4 min-h-11 w-full rounded-xl border border-[#2F6F62] font-semibold text-[#2F6F62]">Manage care team</button></div>
            </aside>
        </section>

        @if ($attention > 0)
            <section class="rounded-3xl border border-amber-200 bg-amber-50 p-5"><h2 class="font-display text-xl font-semibold text-amber-950">{{ $attention }} item{{ $attention === 1 ? '' : 's' }} need attention</h2><p class="mt-1 text-amber-900">Open the calendar to fill uncovered periods, confirm an accepted replacement, or review payment attention.</p><button wire:click="setTab('calendar')" class="mt-4 min-h-11 rounded-xl bg-amber-900 px-4 font-semibold text-white">Review calendar</button></section>
        @endif
    @elseif ($tab === 'calendar')
        <section class="space-y-4">
            <div class="rounded-2xl border border-[#DED6CA] bg-white p-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-display text-2xl font-semibold">Coverage calendar</h2>
                        <p class="text-sm text-[#607080]">{{ $weekStartLocal->format('M j') }} – {{ $weekEndLocal->copy()->subDay()->format('M j, Y') }} · {{ number_format($summary['covered_minutes'] / 60, 1) }} of {{ number_format($summary['required_minutes'] / 60, 1) }} hours confirmed</p>
                        @if($summary['overlap_minutes'] > 0)<p role="alert" class="mt-2 text-sm font-semibold text-amber-800">{{ number_format($summary['overlap_minutes']/60, 1) }} overlapping hours need review.</p>@endif
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <button wire:click="previousWeek" class="min-h-11 rounded-xl border border-[#CFC4B5] px-3 font-semibold" aria-label="Previous week">Previous</button>
                        <button wire:click="currentWeek" class="min-h-11 rounded-xl border border-[#CFC4B5] px-3 font-semibold">Today</button>
                        <button wire:click="nextWeek" class="min-h-11 rounded-xl border border-[#CFC4B5] px-3 font-semibold" aria-label="Next week">Next</button>
                    </div>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                    <label><span class="text-xs font-semibold text-[#526474]">Status</span><select wire:model.live="calendarStatus" class="mt-1 min-h-11 w-full rounded-xl border-[#CFC4B5]"><option value="">All statuses</option>@foreach($statusMeta as $value => $status)<option value="{{ $value }}">{{ $status[0] }}</option>@endforeach</select></label>
                    <label><span class="text-xs font-semibold text-[#526474]">Caregiver</span><select wire:model.live="calendarCaregiver" class="mt-1 min-h-11 w-full rounded-xl border-[#CFC4B5]"><option value="">All caregivers</option>@foreach($roster as $member)<option value="{{ $member->caregiver_user_id }}">{{ $member->caregiver?->name }}</option>@endforeach</select></label>
                    <button wire:click="clearCalendarFilters" class="min-h-11 self-end rounded-xl border border-[#CFC4B5] px-4 text-sm font-semibold">Clear filters</button>
                </div>
            </div>

            <div class="hidden grid-cols-7 overflow-hidden rounded-3xl border border-[#D8D0C5] bg-white lg:grid">
                @foreach($days as $calendarDay)
                    <section class="min-w-0 border-r border-[#E7E0D8] last:border-r-0" aria-label="{{ $calendarDay['date']->format('l, F j') }}">
                        <header class="border-b border-[#E7E0D8] bg-[#F7F2EA] px-3 py-3 text-center"><p class="text-xs font-semibold uppercase tracking-wide text-[#607080]">{{ $calendarDay['date']->format('D') }}</p><p class="mt-1 text-lg font-semibold">{{ $calendarDay['date']->format('j') }}</p></header>
                        <div class="relative h-[48rem] p-1" style="background-image: repeating-linear-gradient(to bottom, transparent 0, transparent calc(8.333% - 1px), #eee7df calc(8.333% - 1px), #eee7df 8.333%);">
                            @foreach($calendarDay['shifts'] as $shift)
                                @php
                                    $meta = $statusMeta[$shift->status] ?? [ucfirst(str_replace('_', ' ', $shift->status)), 'bg-slate-100 text-slate-700 border-slate-200', '•'];
                                    $localStart = $shift->scheduled_start_at->copy()->setTimezone($plan->timezone);
                                    $localEnd = $shift->scheduled_end_at->copy()->setTimezone($plan->timezone);
                                    $dayStartsAt = $calendarDay['date']->copy()->startOfDay();
                                    $dayEndsAt = $dayStartsAt->copy()->addDay();
                                    $segmentStart = $localStart->copy()->max($dayStartsAt);
                                    $segmentEnd = $localEnd->copy()->min($dayEndsAt);
                                    $startMinute = ($segmentStart->hour * 60) + $segmentStart->minute;
                                    $visibleMinutes = max(1, $segmentStart->diffInMinutes($segmentEnd, false));
                                    $topPercent = ($startMinute / 1440) * 100;
                                    $heightPercent = max(3.5, ($visibleMinutes / 1440) * 100);
                                @endphp
                                <button type="button" wire:click="openShift({{ $shift->id }})" class="absolute inset-x-1 overflow-hidden rounded-lg border p-2 text-left shadow-sm focus:z-20 focus:outline-none focus:ring-2 focus:ring-[#5E46A9] {{ $meta[1] }}" style="top: {{ $topPercent }}%; height: {{ $heightPercent }}%;" aria-label="Open {{ $meta[0] }} shift from {{ $localStart->format('g:i A') }} to {{ $shift->scheduled_end_at->copy()->setTimezone($plan->timezone)->format('g:i A') }}">
                                    <span class="block text-[11px] font-semibold">{{ $meta[2] }} {{ $meta[0] }}</span>
                                    <span class="mt-0.5 block text-xs font-semibold text-[#17313F]">{{ $segmentStart->format('g:i A') }}–{{ $segmentEnd->isSameDay($segmentStart) ? $segmentEnd->format('g:i A') : 'midnight' }}</span>
                                    @if($segmentStart->gt($localStart))<span class="block text-[10px] text-[#526474]">Continues from prior day</span>@elseif($segmentEnd->lt($localEnd))<span class="block text-[10px] text-[#526474]">Continues next day</span>@endif
                                    <span class="mt-0.5 block truncate text-[11px] text-[#526474]">{{ $shift->assignedCaregiver?->name ?: 'Caregiver needed' }}</span>
                                </button>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            <div class="space-y-4 lg:hidden">
                <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1" aria-label="Choose a date">
                    @foreach($days as $calendarDay)
                        <button wire:click="selectDay('{{ $calendarDay['date']->toDateString() }}')" class="min-h-14 min-w-[4.4rem] rounded-2xl border px-3 text-center {{ $selectedDay['date']->isSameDay($calendarDay['date']) ? 'border-[#0F3D3E] bg-[#0F3D3E] text-white' : 'border-[#DED6CA] bg-white text-[#526474]' }}" @if($selectedDay['date']->isSameDay($calendarDay['date'])) aria-current="date" @endif><span class="block text-xs font-semibold uppercase">{{ $calendarDay['date']->format('D') }}</span><span class="block text-lg font-semibold">{{ $calendarDay['date']->format('j') }}</span></button>
                    @endforeach
                </div>
                <section class="rounded-3xl border border-[#DED6CA] bg-white p-4" aria-label="Coverage for {{ $selectedDay['date']->format('l, F j') }}">
                    <div class="flex items-center justify-between gap-3"><h3 class="font-display text-xl font-semibold">{{ $selectedDay['date']->format('l, F j') }}</h3><span class="text-sm text-[#607080]">{{ $selectedDay['shifts']->count() }} shifts</span></div>
                    <div class="mt-4 space-y-3">
                        @forelse($selectedDay['shifts'] as $shift)
                            @php $meta = $statusMeta[$shift->status] ?? [ucfirst(str_replace('_', ' ', $shift->status)), 'bg-slate-100 text-slate-700 border-slate-200', '•']; $mobileDayStart=$selectedDay['date']->copy()->startOfDay(); $mobileDayEnd=$mobileDayStart->copy()->addDay(); $mobileLocalStart=$shift->scheduled_start_at->copy()->setTimezone($plan->timezone); $mobileLocalEnd=$shift->scheduled_end_at->copy()->setTimezone($plan->timezone); $mobileSegmentStart=$mobileLocalStart->copy()->max($mobileDayStart); $mobileSegmentEnd=$mobileLocalEnd->copy()->min($mobileDayEnd); @endphp
                            <article class="rounded-2xl border p-4 {{ $meta[1] }}">
                                <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wide">{{ $meta[2] }} {{ $meta[0] }}</p><p class="mt-2 text-lg font-semibold text-[#17313F]">{{ $mobileSegmentStart->format('g:i A') }}–{{ $mobileSegmentEnd->isSameDay($mobileSegmentStart) ? $mobileSegmentEnd->format('g:i A') : 'midnight' }}</p>@if($mobileSegmentStart->gt($mobileLocalStart))<p class="text-xs font-semibold text-[#607080]">Continues from the prior day</p>@elseif($mobileSegmentEnd->lt($mobileLocalEnd))<p class="text-xs font-semibold text-[#607080]">Continues into the next day</p>@endif<p class="mt-1 text-sm text-[#526474]">{{ $shift->assignedCaregiver?->name ?: 'Caregiver needed' }}</p></div><span class="text-sm font-semibold">{{ number_format($shift->scheduled_minutes / 60, 1) }}h total</span></div>
                                <div class="mt-3 grid gap-2"><button wire:click="openShift({{ $shift->id }})" class="min-h-12 rounded-xl border border-current px-3 font-semibold">View shift details</button>@if($shift->status === 'awaiting_family_confirmation' && $shift->replacementCase)<div class="grid grid-cols-2 gap-2"><button wire:click="confirmReplacement({{ $shift->replacementCase->id }})" wire:loading.attr="disabled" class="min-h-12 rounded-xl bg-violet-800 px-3 font-semibold text-white disabled:opacity-60">Confirm {{ $shift->replacementCase->winningOffer?->caregiver?->name ?: 'replacement' }}</button><button wire:click="declineReplacement({{ $shift->replacementCase->id }})" wire:confirm="Do not select this caregiver and continue looking among eligible approved backups?" wire:loading.attr="disabled" class="min-h-12 rounded-xl border border-violet-300 px-3 font-semibold text-violet-900 disabled:opacity-60">Choose another</button></div>@endif</div>
                            </article>
                        @empty
                            <p class="py-6 text-center text-sm text-[#607080]">No coverage scheduled for this day.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </section>
    @elseif ($tab === 'team')
        <section class="grid gap-5">
            <div class="space-y-5">
                @if($pendingLaneRequests->isNotEmpty())
                    <section id="coverage-lane-requests" class="scroll-mt-24 rounded-3xl border border-sky-200 bg-sky-50 p-5" aria-labelledby="coverage-lane-requests-title">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-sky-800">Caregiver requests</p>
                                <h2 id="coverage-lane-requests-title" class="mt-1 font-display text-2xl font-semibold">Recurring lanes awaiting your approval</h2>
                                <p class="mt-1 text-sm text-[#526474]">These caregivers are already on your approved care team and volunteered for these lanes. Nothing is assigned until you approve it.</p>
                            </div>
                            <span class="w-fit rounded-full bg-sky-900 px-3 py-1 text-sm font-semibold text-white">{{ $pendingLaneRequests->count() }}</span>
                        </div>
                        <div class="mt-4 space-y-4">
                            @foreach($pendingLaneRequests->groupBy('roster_member_id') as $memberId => $memberRequests)
                                @php $requestingCaregiver = $memberRequests->first()->caregiver; @endphp
                                <article class="rounded-2xl border border-sky-200 bg-white p-4" wire:key="coverage-lane-request-member-{{ $memberId }}">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <h3 class="font-display text-xl font-semibold">{{ $requestingCaregiver?->name }}</h3>
                                            <p class="mt-1 text-sm text-[#607080]">Requested {{ $memberRequests->count() }} recurring lane{{ $memberRequests->count() === 1 ? '' : 's' }}. Approval confirms both the caregiver’s request and your family’s decision.</p>
                                        </div>
                                        @if($memberRequests->count() > 1)
                                            <button type="button" wire:click="approveLaneRequestsForMember({{ $memberId }})" wire:confirm="Approve every still-available lane requested by {{ $requestingCaregiver?->name }}?" wire:loading.attr="disabled" class="min-h-11 shrink-0 rounded-xl bg-sky-900 px-4 text-sm font-semibold text-white disabled:opacity-60">Approve all available</button>
                                        @endif
                                    </div>
                                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                                        @foreach($memberRequests as $laneRequest)
                                            <div class="rounded-xl border border-[#DED6CA] bg-[#FBF8F3] p-3" wire:key="coverage-lane-request-{{ $laneRequest->id }}">
                                                <p class="font-semibold text-[#17313F]">{{ $dayNames[(int) $laneRequest->template->day_of_week] }} · {{ \Illuminate\Support\Carbon::parse($laneRequest->template->starts_at)->format('g:i A') }}–{{ \Illuminate\Support\Carbon::parse($laneRequest->template->ends_at)->format('g:i A') }}</p>
                                                <p class="mt-1 text-sm text-[#607080]">{{ number_format($laneRequest->template->duration_minutes / 60, 1) }} hours weekly · requested {{ $laneRequest->requested_at->diffForHumans() }}</p>
                                                <div class="mt-3 grid grid-cols-2 gap-2">
                                                    <button type="button" wire:click="approveLaneRequest({{ $laneRequest->id }})" wire:loading.attr="disabled" class="min-h-11 rounded-xl bg-[#0F3D3E] px-3 text-sm font-semibold text-white disabled:opacity-60">Approve lane</button>
                                                    <button type="button" wire:click="declineLaneRequest({{ $laneRequest->id }})" wire:confirm="Decline this recurring lane request? The caregiver will not be assigned." wire:loading.attr="disabled" class="min-h-11 rounded-xl border border-rose-200 px-3 text-sm font-semibold text-rose-700 disabled:opacity-60">Decline</button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
                @if($applicants->isNotEmpty())
                    <section class="rounded-3xl border border-violet-200 bg-violet-50 p-5" aria-labelledby="coverage-applicants-title">
                        <div class="flex items-start justify-between gap-3">
                            <div><p class="text-xs font-semibold uppercase tracking-wide text-violet-700">Your decision</p><h2 id="coverage-applicants-title" class="mt-1 font-display text-2xl font-semibold">Caregiver applications</h2><p class="mt-1 text-sm text-[#526474]">Applying does not add someone to your team or assign care. Review each profile and decide explicitly.</p></div>
                            <span class="rounded-full bg-violet-800 px-3 py-1 text-sm font-semibold text-white">{{ $applicants->count() }}</span>
                        </div>
                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            @foreach($applicants as $applicant)
                                <article id="coverage-applicant-{{ $applicant->id }}" class="scroll-mt-24 rounded-2xl border border-violet-200 bg-white p-4" wire:key="coverage-applicant-{{ $applicant->id }}">
                                    <h3 class="font-semibold text-[#17313F]">{{ $applicant->caregiver?->name }}</h3>
                                    <p class="mt-1 text-sm text-[#607080]">{{ collect([$applicant->caregiver?->city, $applicant->caregiver?->state])->filter()->implode(', ') ?: 'Location on profile' }}@if($applicant->caregiver?->caregiverProfile?->years_experience) · {{ $applicant->caregiver->caregiverProfile->years_experience }} years experience @endif</p>
                                    @if($applicant->caregiver?->caregiverProfile?->slug)<a href="{{ route('caregivers.show', $applicant->caregiver->caregiverProfile->slug) }}" class="mt-2 inline-flex min-h-11 items-center font-semibold text-[#2F6F62] underline">Review caregiver profile</a>@endif
                                    <div class="mt-3 grid grid-cols-2 gap-2"><button wire:click="approveApplicant({{ $applicant->id }})" wire:loading.attr="disabled" class="min-h-12 rounded-xl bg-violet-800 px-3 font-semibold text-white disabled:opacity-60">Approve & invite</button><button wire:click="declineApplicant({{ $applicant->id }})" wire:confirm="Decline this application? No care assignment has been created." wire:loading.attr="disabled" class="min-h-12 rounded-xl border border-violet-300 px-3 font-semibold text-violet-900 disabled:opacity-60">Decline</button></div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
                <div class="rounded-3xl border border-[#DED6CA] bg-white p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="font-display text-2xl font-semibold">Family-approved care team</h2>
                            <p class="mt-1 text-sm text-[#607080]">Approval lets a caregiver review your coverage invitations. It does not assign them to any shift. These preferences control future offers only.</p>
                        </div>
                        <button type="button" wire:click="openCaregiverSearchModal" class="min-h-12 shrink-0 rounded-xl bg-[#0F3D3E] px-5 font-semibold text-white hover:bg-[#17313F] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF] focus:ring-offset-2">
                            Find & invite caregiver
                        </button>
                    </div>
                    <div class="mt-5 space-y-4">
                        @forelse($roster as $member)
                            @php
                                $memberDayValues = (array) data_get($memberPreferences, $member->id.'.eligible_days', []);
                                $memberTypeValues = (array) data_get($memberPreferences, $member->id.'.eligible_shift_types', []);
                                $memberDaysSummary = count($memberDayValues) === 7
                                    ? 'Every day'
                                    : collect($memberDayValues)->map(fn ($day) => substr($dayNames[(int) $day] ?? '', 0, 3))->filter()->implode(', ');
                                $memberTypeLabels = ['daytime'=>'Daytime','overnight'=>'Overnight','6_hour'=>'6 hours','8_hour'=>'8 hours','12_hour'=>'12 hours'];
                                $memberTypesSummary = collect($memberTypeValues)->map(fn ($type) => $memberTypeLabels[$type] ?? null)->filter()->implode(', ') ?: 'Any shift type';
                                $hasMemberPreferenceErrors = collect($errors->keys())->contains(fn (string $key) => str_starts_with($key, 'memberPreferences.'.$member->id.'.'));
                            @endphp
                            <article x-data="{ preferencesOpen: @js($hasMemberPreferenceErrors) }" class="rounded-2xl border border-[#E4DDD3] p-4" wire:key="coverage-member-{{ $member->id }}">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p class="font-semibold text-[#17313F]">{{ $member->caregiver?->name }}</p>
                                        <p class="text-sm text-[#607080]">{{ ucfirst($member->role) }} · {{ ucfirst(str_replace('_',' ',$member->status)) }}</p>
                                        <p class="mt-1 text-xs text-[#7B8794]">{{ $member->caregiver_accepted_at ? 'Caregiver accepted '.$member->caregiver_accepted_at->diffForHumans() : 'Waiting for caregiver to accept' }}</p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @if(in_array($member->status, ['family_approved','active','paused']))
                                            <button type="button" @click="preferencesOpen = ! preferencesOpen" :aria-expanded="preferencesOpen" aria-controls="coverage-member-preferences-{{ $member->id }}" class="min-h-11 rounded-xl border border-[#CFC4B5] px-3 text-sm font-semibold text-[#344754]" x-text="preferencesOpen ? 'Hide preferences' : 'Edit preferences'">Edit preferences</button>
                                        @endif
                                        @if($member->status === 'active')
                                            <button wire:click="pauseMember({{ $member->id }})" wire:loading.attr="disabled" class="min-h-11 rounded-xl border border-amber-300 px-3 text-sm font-semibold text-amber-800 disabled:opacity-60">Pause</button>
                                        @elseif($member->status === 'paused')
                                            <button wire:click="resumeMember({{ $member->id }})" wire:loading.attr="disabled" class="min-h-11 rounded-xl border border-emerald-300 px-3 text-sm font-semibold text-emerald-800 disabled:opacity-60">Resume</button>
                                        @endif
                                        @if($member->status !== 'removed')
                                            <button wire:click="removeMember({{ $member->id }})" wire:confirm="Remove this caregiver from future offers? Existing shifts and history remain." wire:loading.attr="disabled" class="min-h-11 rounded-xl border border-rose-200 px-3 text-sm font-semibold text-rose-700 disabled:opacity-60">Remove</button>
                                        @endif
                                    </div>
                                </div>

                                @if(in_array($member->status, ['family_approved','active','paused']))
                                    <p class="mt-3 text-sm text-[#607080]">{{ $memberDaysSummary ?: 'No eligible days' }} · {{ $memberTypesSummary }} · {{ data_get($memberPreferences, $member->id.'.replacement_opt_in') ? 'Backup offers on' : 'Backup offers off' }}</p>
                                    <div id="coverage-member-preferences-{{ $member->id }}" x-cloak x-show="preferencesOpen" class="mt-4 border-t border-[#EEE8DF] pt-4">
                                    <div class="grid gap-4 lg:grid-cols-2">
                                        <label>
                                            <span class="text-xs font-semibold text-[#526474]">Coverage role</span>
                                            <select wire:model="memberPreferences.{{ $member->id }}.role" class="mt-1 min-h-11 w-full rounded-xl border-[#CFC4B5]">
                                                <option value="primary">Primary</option>
                                                <option value="backup">Backup</option>
                                            </select>
                                        </label>
                                        <label class="flex min-h-11 items-center gap-3 rounded-xl bg-[#F7F2EA] px-3 py-2 text-sm font-semibold text-[#344754]">
                                            <input type="checkbox" wire:model="memberPreferences.{{ $member->id }}.replacement_opt_in" class="rounded border-[#AFA79B] text-[#2F6F62] focus:ring-[#2F6F62]">
                                            May receive matching replacement offers
                                        </label>
                                    </div>
                                    <fieldset class="mt-4">
                                        <legend class="text-xs font-semibold text-[#526474]">Eligible days</legend>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach($dayNames as $dayIndex => $dayName)
                                                <label class="flex min-h-11 items-center gap-2 rounded-xl border border-[#DED6CA] px-3 text-sm">
                                                    <input type="checkbox" value="{{ $dayIndex }}" wire:model="memberPreferences.{{ $member->id }}.eligible_days" class="rounded border-[#AFA79B] text-[#2F6F62] focus:ring-[#2F6F62]">
                                                    {{ substr($dayName, 0, 3) }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                    <fieldset class="mt-4">
                                        <legend class="text-xs font-semibold text-[#526474]">Eligible shift types <span class="font-normal">(leave all unchecked for any type)</span></legend>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach(['daytime'=>'Daytime','overnight'=>'Overnight','6_hour'=>'6 hours','8_hour'=>'8 hours','12_hour'=>'12 hours'] as $type => $label)
                                                <label class="flex min-h-11 items-center gap-2 rounded-xl border border-[#DED6CA] px-3 text-sm">
                                                    <input type="checkbox" value="{{ $type }}" wire:model="memberPreferences.{{ $member->id }}.eligible_shift_types" class="rounded border-[#AFA79B] text-[#2F6F62] focus:ring-[#2F6F62]">
                                                    {{ $label }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                    @error('memberPreferences.'.$member->id.'.eligible_days') <p class="mt-2 text-sm font-semibold text-rose-700">{{ $message }}</p> @enderror
                                    <button wire:click="saveMemberPreferences({{ $member->id }})" wire:loading.attr="disabled" wire:target="saveMemberPreferences({{ $member->id }})" class="mt-4 min-h-11 rounded-xl bg-[#0F3D3E] px-4 font-semibold text-white disabled:opacity-60">Save future-offer preferences</button>
                                    </div>
                                @endif
                            </article>
                        @empty
                            <p class="rounded-2xl bg-[#F7F2EA] p-5 text-sm text-[#607080]">No caregivers approved yet. Use “Find & invite caregiver” to add the people your family wants on this care team.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-3xl border border-[#DED6CA] bg-white p-5">
                    <h2 class="font-display text-2xl font-semibold">Recurring coverage lanes</h2>
                    <p class="mt-1 text-sm text-[#607080]">Open a day to offer its lanes to caregivers who already accepted your care-team invitation.</p>
                    <div class="mt-5 space-y-3">
                        @forelse($templates->groupBy('day_of_week') as $dayIndex => $dayTemplates)
                            @php
                                $filledLaneCount = $dayTemplates->where('status', 'active')->count();
                                $dayLaneCount = $dayTemplates->count();
                            @endphp
                            <details class="group overflow-hidden rounded-2xl border border-[#E4DDD3]" @if((int) $dayIndex === now($plan->timezone)->dayOfWeek) open @endif>
                                <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 bg-[#FBF8F3] px-4 py-3 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F6F62]">
                                    <span>
                                        <span class="block font-semibold text-[#17313F]">{{ $dayNames[(int) $dayIndex] }}</span>
                                        <span class="block text-sm text-[#607080]">{{ $filledLaneCount }} of {{ $dayLaneCount }} lane{{ $dayLaneCount === 1 ? '' : 's' }} filled</span>
                                    </span>
                                    <span class="inline-flex items-center gap-2 text-sm font-semibold {{ $filledLaneCount === $dayLaneCount ? 'text-emerald-700' : 'text-amber-800' }}">
                                        {{ $filledLaneCount === $dayLaneCount ? 'Covered' : 'Review' }}
                                        <span class="transition group-open:rotate-180" aria-hidden="true">⌄</span>
                                    </span>
                                </summary>
                                <div class="grid gap-3 border-t border-[#E4DDD3] p-3 md:grid-cols-2">
                                    @foreach($dayTemplates as $template)
                                        @php $eligibleMembers = $eligibleRosterByTemplate->get($template->id, collect()); $laneRequests = $pendingLaneRequestsByTemplate->get($template->id, collect()); @endphp
                                        <article class="rounded-2xl border border-[#E4DDD3] bg-white p-4" wire:key="coverage-lane-{{ $template->id }}">
                                            <p class="text-lg font-semibold">{{ \Illuminate\Support\Carbon::parse($template->starts_at)->format('g:i A') }} – {{ \Illuminate\Support\Carbon::parse($template->ends_at)->format('g:i A') }}</p>
                                            <p class="mt-1 text-sm text-[#607080]">{{ number_format($template->duration_minutes / 60, 1) }} hours · {{ ucfirst($template->status) }}</p>
                                            @if($template->rosterMember)
                                                <p class="mt-2 font-semibold">{{ $template->rosterMember->caregiver?->name }}</p>
                                            @endif
                                            @if($laneRequests->isNotEmpty())
                                                <p class="mt-2 rounded-xl bg-sky-50 p-2 text-sm font-semibold text-sky-900">{{ $laneRequests->pluck('caregiver.name')->filter()->implode(', ') }} requested this lane.</p>
                                            @endif
                                            @if(in_array($template->status, ['uncovered','declined','expired']))
                                                <div class="mt-3 space-y-2">
                                                    <label class="block">
                                                        <span class="sr-only">Caregiver for {{ $dayNames[(int) $dayIndex] }} {{ \Illuminate\Support\Carbon::parse($template->starts_at)->format('g:i A') }}</span>
                                                        <select wire:model="laneSelections.{{ $template->id }}" @disabled($eligibleMembers->isEmpty()) class="min-h-11 w-full rounded-xl border-[#CFC4B5] disabled:bg-slate-100">
                                                            <option value="">{{ $eligibleMembers->isEmpty() ? 'No eligible caregiver for this lane' : 'Choose approved caregiver' }}</option>
                                                            @foreach($eligibleMembers as $member)
                                                                <option value="{{ $member->id }}">{{ $member->caregiver?->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                    @if($eligibleMembers->isEmpty())
                                                        <p class="text-xs text-amber-800">Adjust a care-team member’s eligible days or shift types first.</p>
                                                    @endif
                                                    <button wire:click="offerLane({{ $template->id }})" wire:loading.attr="disabled" wire:target="offerLane({{ $template->id }})" @disabled($eligibleMembers->isEmpty()) class="min-h-11 w-full rounded-xl bg-[#0F3D3E] px-3 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Offer recurring lane</button>
                                                </div>
                                            @elseif($template->status === 'offered')
                                                <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm font-semibold text-amber-800">Waiting for the caregiver’s decision until {{ $template->offer_expires_at?->setTimezone($plan->timezone)->format('M j, g:i A') }}.</p>
                                            @else
                                                <p class="mt-3 rounded-xl bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">Accepted and filling future shifts.</p>
                                            @endif
                                        </article>
                                    @endforeach
                                </div>
                            </details>
                        @empty
                            <p class="rounded-2xl bg-[#F7F2EA] p-5 text-sm text-[#607080]">No recurring lanes are available for this schedule yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

        </section>
        @if($showCaregiverSearchModal)
            @include('livewire.family.partials.continuous-coverage-caregiver-modal')
        @endif
    @elseif ($tab === 'history')
        <section class="rounded-3xl border border-[#DED6CA] bg-white p-5">
            <div><h2 class="font-display text-2xl font-semibold">Coverage history</h2><p class="mt-1 text-sm text-[#607080]">Past, released, replaced, cancelled, missed, disputed, and completed shifts stay accessible.</p></div>
            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <label><span class="text-xs font-semibold text-[#526474]">From</span><input type="date" wire:model.change="historyFrom" class="mt-1 min-h-11 w-full rounded-xl border-[#CFC4B5]"></label>
                <label><span class="text-xs font-semibold text-[#526474]">Through</span><input type="date" wire:model.change="historyThrough" class="mt-1 min-h-11 w-full rounded-xl border-[#CFC4B5]"></label>
                <label><span class="text-xs font-semibold text-[#526474]">Caregiver</span><select wire:model.live="historyCaregiver" class="mt-1 min-h-11 w-full rounded-xl border-[#CFC4B5]"><option value="">All caregivers</option>@foreach($roster as $member)<option value="{{ $member->caregiver_user_id }}">{{ $member->caregiver?->name }}</option>@endforeach</select></label>
                <label><span class="text-xs font-semibold text-[#526474]">Status</span><select wire:model.live="historyStatus" class="mt-1 min-h-11 w-full rounded-xl border-[#CFC4B5]"><option value="">All statuses</option>@foreach($statusMeta as $value => $status)<option value="{{ $value }}">{{ $status[0] }}</option>@endforeach</select></label>
                <label><span class="text-xs font-semibold text-[#526474]">Billing</span><select wire:model.live="historyBillingStatus" class="mt-1 min-h-11 w-full rounded-xl border-[#CFC4B5]"><option value="">All billing</option><option value="authorized">Authorization hold</option><option value="captured">Finalized</option><option value="transferred">Finalized & transferred</option><option value="partially_refunded">Partially adjusted</option><option value="refunded">Fully adjusted</option><option value="failed">Needs attention</option></select></label>
                <button wire:click="clearHistoryFilters" class="min-h-11 self-end rounded-xl border border-[#CFC4B5] px-3 text-sm font-semibold">Clear filters</button>
            </div>
            <div class="mt-5 space-y-3">
                @forelse($history as $shift)
                    @php $historyState = ($shift->booking?->status === 'disputed' || $shift->booking?->dispute_opened_at) ? 'disputed' : ($shift->booking?->no_show_flag ? 'missed' : ($shift->replacementCase?->status === 'resolved' ? 'replaced' : $shift->status)); $meta = $statusMeta[$historyState] ?? [ucfirst(str_replace('_', ' ', $historyState)), 'bg-slate-100 text-slate-700 border-slate-200', '•']; $payment = $shift->booking?->payment; $net = max(0, (int) ($payment?->amount_captured_cents ?? 0) - (int) ($payment?->amount_refunded_cents ?? 0)); @endphp
                    <article class="grid gap-3 rounded-2xl border border-[#E4DDD3] p-4 md:grid-cols-[1.2fr_1fr_1fr_auto] md:items-center">
                        <div><p class="font-semibold">{{ $shift->scheduled_start_at->copy()->setTimezone($plan->timezone)->format('D, M j, Y') }}</p><p class="text-sm text-[#607080]">{{ $shift->scheduled_start_at->copy()->setTimezone($plan->timezone)->format('g:i A') }}–{{ $shift->scheduled_end_at->copy()->setTimezone($plan->timezone)->format('g:i A') }}</p></div>
                        <div><p class="text-xs text-[#7B8794]">Caregiver</p><p class="font-semibold">{{ $shift->assignedCaregiver?->name ?: $shift->replacementCase?->originalCaregiver?->name ?: 'Unassigned' }}</p></div>
                        <div><p class="text-xs text-[#7B8794]">Approved time and billing</p><p class="font-semibold">{{ number_format(($shift->booking?->worked_minutes ?: $shift->scheduled_minutes) / 60, 1) }}h · ${{ number_format($net / 100, 2) }} net billed</p></div>
                        <div class="flex flex-col items-start gap-2"><span class="w-fit rounded-full border px-3 py-1 text-xs font-semibold {{ $meta[1] }}">{{ $meta[2] }} {{ $meta[0] }}</span><button wire:click="openShift({{ $shift->id }})" class="min-h-10 rounded-xl border border-[#2F6F62] px-3 text-sm font-semibold text-[#2F6F62]">View details</button></div>
                    </article>
                @empty
                    <p class="rounded-2xl bg-[#F7F2EA] p-6 text-center text-[#607080]">No coverage history matches these filters.</p>
                @endforelse
            </div>
            <div class="mt-5">{{ $history->links() }}</div>
        </section>
    @elseif ($tab === 'billing')
        <section class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-3xl bg-[#0F3D3E] p-5 text-white"><p class="text-sm text-white/75">Net billed</p><p class="mt-2 font-display text-4xl font-semibold">${{ number_format($netBilledCents / 100, 2) }}</p><p class="mt-2 text-sm text-white/75">Finalized care charges after applicable adjustments.</p></div>
                <div class="rounded-3xl border border-[#DED6CA] bg-white p-5"><p class="text-sm text-[#607080]">Next 7 days</p><p class="mt-2 font-display text-3xl font-semibold">${{ number_format($upcomingEstimate, 2) }}</p><p class="mt-2 text-sm text-[#607080]">Estimate for confirmed scheduled hours using the normal booking price. This is not a charge.</p></div>
                <div class="rounded-3xl border border-[#DED6CA] bg-white p-5"><p class="text-sm text-[#607080]">Care rate</p><p class="mt-2 font-display text-3xl font-semibold">${{ number_format((float) $plan->hourly_rate, 2) }}/hr</p><p class="mt-2 text-sm text-[#607080]">Before the applicable platform fee. Existing booking rules determine the final charge.</p></div>
            </div>
            <div class="rounded-3xl border border-[#DED6CA] bg-white p-5">
                <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div><h2 class="font-display text-2xl font-semibold">Coverage receipts</h2><p class="mt-1 text-sm text-[#607080]">Temporary authorization holds are labeled as holds and are not included in net billed.</p></div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label><span class="text-xs font-semibold text-[#526474]">Period</span><select wire:model.live="billingPeriod" class="mt-1 min-h-11 w-full rounded-xl border-[#CFC4B5]"><option value="week">This week</option><option value="month">This month</option><option value="all">All time</option></select></label>
                        <label><span class="text-xs font-semibold text-[#526474]">Billing status</span><select wire:model.live="billingStatus" class="mt-1 min-h-11 w-full rounded-xl border-[#CFC4B5]"><option value="">All statuses</option><option value="authorized">Authorization hold</option><option value="captured">Finalized</option><option value="transferred">Finalized & transferred</option><option value="failed">Needs attention</option><option value="authorization_required">Card confirmation needed</option><option value="partially_refunded">Partially adjusted</option><option value="refunded">Fully adjusted</option></select></label>
                    </div>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse($billingShifts as $shift)
                        @php $payment = $shift->booking?->payment; $net = max(0, (int) ($payment?->amount_captured_cents ?? 0) - (int) ($payment?->amount_refunded_cents ?? 0)); $billingLabel = $payment?->status === 'authorized' ? 'Temporary authorization hold' : ucfirst(str_replace('_', ' ', $payment?->status ?? 'not prepared')); @endphp
                        <article class="grid gap-3 rounded-2xl border border-[#E4DDD3] p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-center">
                            <div><p class="font-semibold">{{ $shift->scheduled_start_at->copy()->setTimezone($plan->timezone)->format('M j, Y · g:i A') }}</p><p class="text-sm text-[#607080]">Visit #{{ $shift->care_booking_id }}</p></div>
                            <div><p class="text-sm text-[#607080]">{{ $billingLabel }}</p><p class="text-sm">{{ number_format(($shift->booking?->worked_minutes ?: $shift->scheduled_minutes) / 60, 1) }} hours</p></div>
                            <div class="flex items-center justify-between gap-3 sm:flex-col sm:items-end"><p class="text-lg font-semibold">${{ number_format($net / 100, 2) }}</p><button wire:click="openShift({{ $shift->id }})" class="min-h-10 rounded-xl border border-[#2F6F62] px-3 text-sm font-semibold text-[#2F6F62]">Receipt details</button></div>
                        </article>
                    @empty
                        <p class="rounded-2xl bg-[#F7F2EA] p-6 text-center text-[#607080]">No coverage receipt matches this period and status.</p>
                    @endforelse
                </div>
                <div class="mt-5">{{ $billingShifts->links() }}</div>
            </div>
        </section>
    @else
        <section class="space-y-5">
            <div class="grid gap-5 lg:grid-cols-2">
                <div class="rounded-3xl border border-[#DED6CA] bg-white p-5"><h2 class="font-display text-2xl font-semibold">Current plan</h2><dl class="mt-4 space-y-3 text-sm"><div class="flex justify-between gap-4"><dt class="text-[#607080]">Status</dt><dd class="font-semibold">{{ ucfirst($plan->status) }}</dd></div><div class="flex justify-between gap-4"><dt class="text-[#607080]">Pattern</dt><dd class="font-semibold">{{ $plan->coverage_pattern === '24_7' ? '24/7' : ucfirst($plan->coverage_pattern) }}</dd></div><div class="flex justify-between gap-4"><dt class="text-[#607080]">Begins</dt><dd class="text-right font-semibold">{{ $plan->starts_on->format('F j, Y') }}{{ $plan->coverage_pattern === '24_7' ? ' at '.\Illuminate\Support\Carbon::parse(data_get($plan->metadata, 'coverage_start_time', '07:00'))->format('g:i A') : '' }}</dd></div><div class="flex justify-between gap-4"><dt class="text-[#607080]">Ends</dt><dd class="font-semibold">{{ $plan->ends_on?->format('F j, Y') ?: 'No end date' }}</dd></div><div class="flex justify-between gap-4"><dt class="text-[#607080]">Timezone</dt><dd class="font-semibold">{{ $plan->timezone }}</dd></div></dl></div>
                <div class="rounded-3xl border border-[#DED6CA] bg-white p-5"><h2 class="font-display text-2xl font-semibold">Replacement choice</h2><p class="mt-3 text-[#607080]">{{ $plan->replacementRequiresFamilyConfirmation() ? 'You confirm an approved backup after they accept.' : 'A caregiver from your approved backup team is confirmed after voluntarily accepting.' }}</p><p class="mt-4 rounded-2xl bg-[#F7F2EA] p-4 text-sm text-[#526474]">Only active caregivers who your family approved, who accepted team membership, and who opted into matching backup coverage can receive replacement offers.</p></div>
            </div>

            <form wire:submit="saveMarketplaceApplications" class="rounded-3xl border border-[#DED6CA] bg-white p-5 sm:p-6">
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2F6F62]">Optional applications</p>
                <h2 class="mt-1 font-display text-2xl font-semibold">Let caregivers apply to this plan</h2>
                <p class="mt-2 max-w-3xl text-sm text-[#607080]">When enabled, eligible caregivers can see the service area, schedule, activities, and rate—not the recipient’s identity or exact address. Your family must approve every applicant before they may join or accept coverage.</p>
                <label class="mt-4 flex min-h-12 items-center gap-3 rounded-2xl bg-[#F7F2EA] p-4 font-semibold text-[#17313F]"><input type="checkbox" wire:model="marketplaceApplicationsEnabled" class="rounded border-[#AFA79B] text-[#2F6F62] focus:ring-[#2F6F62]">Accept new caregiver applications</label>
                <button type="submit" wire:loading.attr="disabled" class="mt-4 min-h-12 rounded-xl bg-[#0F3D3E] px-5 font-semibold text-white disabled:opacity-60">Save application setting</button>
            </form>

            <form wire:submit="saveFutureSchedule" class="rounded-3xl border border-[#DED6CA] bg-white p-5 sm:p-6">
                <p class="text-sm font-semibold uppercase tracking-wide text-[#2F6F62]">Future changes only</p>
                <h2 class="mt-1 font-display text-2xl font-semibold">Change the coverage schedule</h2>
                <p class="mt-2 max-w-3xl text-sm text-[#607080]">Choose an effective date. Past shifts, released assignments, completed visits, and billing history remain unchanged. If a future visit is already prepared, change that visit through its existing workflow first.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label><span class="text-sm font-semibold text-[#324457]">Effective date</span><input type="date" wire:model="scheduleEffectiveOn" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]">@error('scheduleEffectiveOn')<span class="mt-1 block text-sm text-rose-700">{{ $message }}</span>@enderror</label>
                    <label><span class="text-sm font-semibold text-[#324457]">Coverage pattern</span><select wire:model.live="scheduleCoveragePattern" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]"><option value="24_7">24/7</option><option value="overnight">Overnight</option><option value="custom">Custom weekly windows</option></select></label>
                </div>
                @if($scheduleCoveragePattern === '24_7')
                    <div class="mt-4 grid gap-4 sm:grid-cols-2"><label><span class="text-sm font-semibold text-[#324457]">Shift length</span><select wire:model.live="scheduleShiftLengthChoice" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]"><option value="720">12 hours · 2 shifts/day</option><option value="480">8 hours · 3 shifts/day</option><option value="360">6 hours · 4 shifts/day</option><option value="custom">Custom shift length</option></select>@error('scheduleShiftLengthChoice')<span class="mt-1 block text-sm text-rose-700">{{ $message }}</span>@enderror</label><label><span class="text-sm font-semibold text-[#324457]">Daily handoff anchor</span><input type="time" wire:model="scheduleStartTime" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]"></label></div>
                    @if($scheduleShiftLengthChoice === 'custom')
                        <label class="mt-4 block max-w-md"><span class="text-sm font-semibold text-[#324457]">Custom shift length in hours</span><input type="number" min="1" max="12" step="any" inputmode="decimal" wire:model="scheduleCustomShiftLengthHours" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]"><span class="mt-1 block text-xs text-[#607080]">Use a length that divides 24 hours evenly, such as 4, 3, 2, or 1.5 hours.</span>@error('scheduleCustomShiftLengthHours')<span class="mt-1 block text-sm text-rose-700">{{ $message }}</span>@enderror</label>
                    @endif
                @elseif($scheduleCoveragePattern === 'overnight')
                    <div class="mt-4 grid gap-4 sm:grid-cols-2"><label><span class="text-sm font-semibold text-[#324457]">Starts nightly</span><input type="time" wire:model="scheduleStartTime" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]"></label><label><span class="text-sm font-semibold text-[#324457]">Ends next morning</span><input type="time" wire:model="scheduleEndTime" class="mt-1 min-h-12 w-full rounded-xl border-[#CFC4B5]"></label></div>
                @else
                    <div class="mt-4 space-y-3">@foreach($scheduleWindows as $index => $window)<div wire:key="future-schedule-window-{{ $index }}" class="grid gap-3 rounded-2xl bg-[#F7F2EA] p-3 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end"><label><span class="text-xs font-semibold text-[#526474]">Day</span><select wire:model="scheduleWindows.{{ $index }}.day" class="mt-1 min-h-11 w-full rounded-xl border-[#CFC4B5]">@foreach($dayNames as $dayIndex => $dayName)<option value="{{ $dayIndex }}">{{ $dayName }}</option>@endforeach</select></label><label><span class="text-xs font-semibold text-[#526474]">Start</span><input type="time" wire:model="scheduleWindows.{{ $index }}.start" class="mt-1 min-h-11 w-full rounded-xl border-[#CFC4B5]"></label><label><span class="text-xs font-semibold text-[#526474]">End</span><input type="time" wire:model="scheduleWindows.{{ $index }}.end" class="mt-1 min-h-11 w-full rounded-xl border-[#CFC4B5]"></label><button type="button" wire:click="removeScheduleWindow({{ $index }})" class="min-h-11 rounded-xl border border-rose-200 px-3 text-sm font-semibold text-rose-700">Remove</button></div>@endforeach @error('scheduleWindows')<p class="text-sm text-rose-700">{{ $message }}</p>@enderror<button type="button" wire:click="addScheduleWindow" class="min-h-11 rounded-xl border border-[#2F6F62] px-4 font-semibold text-[#2F6F62]">Add coverage window</button></div>
                @endif
                <button type="submit" wire:loading.attr="disabled" wire:confirm="Apply this schedule only to unprepared future shifts from the effective date? Existing history stays unchanged." class="mt-5 min-h-12 rounded-xl bg-[#0F3D3E] px-5 font-semibold text-white disabled:opacity-60">Save future schedule</button>
            </form>
        </section>
    @endif

    @if($selectedShiftItem)
        @php
            $detailMeta = $statusMeta[$selectedShiftItem->status] ?? [ucfirst(str_replace('_', ' ', $selectedShiftItem->status)), 'bg-slate-100 text-slate-700 border-slate-200', '•'];
            $detailPayment = $selectedShiftItem->booking?->payment;
            $detailNet = max(0, (int) ($detailPayment?->amount_captured_cents ?? 0) - (int) ($detailPayment?->amount_refunded_cents ?? 0));
            $detailAddress = collect([
                data_get($plan->address_snapshot, 'address_line1'),
                data_get($plan->address_snapshot, 'address_line2'),
                data_get($plan->address_snapshot, 'city'),
                data_get($plan->address_snapshot, 'state'),
                data_get($plan->address_snapshot, 'zip'),
            ])->filter()->implode(', ');
        @endphp
        <div class="fixed inset-0 z-50 bg-[#102C34]/55 lg:flex lg:justify-end" role="presentation">
            <button type="button" wire:click="closeShift" class="absolute inset-0 hidden cursor-default lg:block" aria-label="Close shift details"></button>
            <section id="coverage-shift-details" role="dialog" aria-modal="true" aria-labelledby="coverage-shift-title" tabindex="-1" x-data x-init="$nextTick(() => $el.focus())" class="relative h-full w-full overflow-y-auto bg-[#FFFDF9] p-5 outline-none lg:max-w-xl lg:border-l lg:border-[#D8D0C5] lg:p-7 lg:shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#2F6F62]">Coverage shift #{{ $selectedShiftItem->id }}</p>
                        <h2 id="coverage-shift-title" class="mt-1 font-display text-2xl font-semibold text-[#17313F]">{{ $selectedShiftItem->scheduled_start_at->copy()->setTimezone($plan->timezone)->format('l, F j, Y') }}</h2>
                        <p class="mt-1 text-[#526474]">{{ $selectedShiftItem->scheduled_start_at->copy()->setTimezone($plan->timezone)->format('g:i A') }}–{{ $selectedShiftItem->scheduled_end_at->copy()->setTimezone($plan->timezone)->format('g:i A T') }} · {{ number_format($selectedShiftItem->scheduled_minutes / 60, 1) }} hours</p>
                    </div>
                    <button type="button" wire:click="closeShift" class="min-h-11 min-w-11 rounded-full border border-[#CFC4B5] text-xl" aria-label="Close shift details">×</button>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <span class="rounded-full border px-3 py-1 text-sm font-semibold {{ $detailMeta[1] }}">{{ $detailMeta[2] }} {{ $detailMeta[0] }}</span>
                    @if($selectedShiftItem->booking?->no_show_flag)<span class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-sm font-semibold text-rose-800">Missed visit recorded</span>@endif
                    @if($selectedShiftItem->booking?->dispute_opened_at)<span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-900">Dispute under review</span>@endif
                </div>

                @if($selectedShiftItem->status === 'awaiting_family_confirmation' && $selectedShiftItem->replacementCase?->winningOffer)
                    <section class="mt-5 rounded-2xl border border-violet-200 bg-violet-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-violet-800">Your decision</p>
                        <h3 class="mt-1 font-display text-xl font-semibold text-violet-950">{{ $selectedShiftItem->replacementCase->winningOffer->caregiver?->name }} accepted this backup shift</h3>
                        <p class="mt-1 text-sm text-violet-900">Confirm this caregiver from your approved care team, or continue looking among the other eligible approved backups.</p>
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <button wire:click="confirmReplacement({{ $selectedShiftItem->replacementCase->id }})" wire:loading.attr="disabled" class="min-h-12 rounded-xl bg-violet-800 px-3 font-semibold text-white disabled:opacity-60">Confirm caregiver</button>
                            <button wire:click="declineReplacement({{ $selectedShiftItem->replacementCase->id }})" wire:confirm="Do not select this caregiver and continue looking among eligible approved backups?" wire:loading.attr="disabled" class="min-h-12 rounded-xl border border-violet-300 px-3 font-semibold text-violet-900 disabled:opacity-60">Choose another</button>
                        </div>
                    </section>
                @endif

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-[#DED6CA] bg-white p-4"><p class="text-xs font-semibold uppercase tracking-wide text-[#7B8794]">Caregiver</p><p class="mt-1 font-semibold">{{ $selectedShiftItem->assignedCaregiver?->name ?: 'Caregiver needed' }}</p>@if($selectedShiftItem->replacementCase?->originalCaregiver)<p class="mt-1 text-sm text-[#607080]">Originally {{ $selectedShiftItem->replacementCase->originalCaregiver->name }}</p>@endif</div>
                    <div class="rounded-2xl border border-[#DED6CA] bg-white p-4"><p class="text-xs font-semibold uppercase tracking-wide text-[#7B8794]">Coverage lane</p><p class="mt-1 font-semibold">{{ $selectedShiftItem->template ? ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][$selectedShiftItem->template->day_of_week].' recurring lane' : 'Individual coverage shift' }}</p></div>
                    <div class="rounded-2xl border border-[#DED6CA] bg-white p-4 sm:col-span-2"><p class="text-xs font-semibold uppercase tracking-wide text-[#7B8794]">Care location</p><p class="mt-1 font-semibold">{{ $detailAddress ?: 'Location not recorded' }}</p></div>
                </div>

                <section class="mt-5 rounded-2xl border border-[#DED6CA] bg-white p-4">
                    <h3 class="font-display text-xl font-semibold">Care activities</h3>
                    <ul class="mt-3 space-y-2 text-sm text-[#526474]">
                        @forelse((array) $plan->task_snapshot as $task)
                            <li class="flex gap-2"><span aria-hidden="true">✓</span><span>{{ data_get($task, 'name', 'Care activity') }}</span></li>
                        @empty
                            <li>Care expectations are recorded in the plan notes.</li>
                        @endforelse
                    </ul>
                    @if($plan->care_notes)<p class="mt-3 border-t border-[#E7E0D8] pt-3 text-sm text-[#526474]">{{ $plan->care_notes }}</p>@endif
                </section>

                <section class="mt-5 rounded-2xl border border-[#DED6CA] bg-white p-4">
                    <h3 class="font-display text-xl font-semibold">Visit record and billing</h3>
                    @if($selectedShiftItem->booking)
                        <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                            <div><dt class="text-[#7B8794]">Visit</dt><dd class="font-semibold">#{{ $selectedShiftItem->care_booking_id }}</dd></div>
                            <div><dt class="text-[#7B8794]">Approved actual time</dt><dd class="font-semibold">{{ $selectedShiftItem->booking->worked_minutes ? number_format($selectedShiftItem->booking->worked_minutes / 60, 2).' hours' : 'Not finalized' }}</dd></div>
                            <div><dt class="text-[#7B8794]">Checked in</dt><dd class="font-semibold">{{ $selectedShiftItem->booking->started_at?->setTimezone($plan->timezone)->format('M j, g:i A') ?: 'Not recorded' }}</dd></div>
                            <div><dt class="text-[#7B8794]">Checked out</dt><dd class="font-semibold">{{ $selectedShiftItem->booking->completed_at?->setTimezone($plan->timezone)->format('M j, g:i A') ?: 'Not recorded' }}</dd></div>
                            <div><dt class="text-[#7B8794]">Billing state</dt><dd class="font-semibold">{{ $detailPayment?->status === 'authorized' ? 'Temporary authorization hold' : ucfirst(str_replace('_', ' ', $detailPayment?->status ?? 'Not prepared')) }}</dd></div>
                            <div><dt class="text-[#7B8794]">Net billed</dt><dd class="font-semibold">${{ number_format($detailNet / 100, 2) }}</dd></div>
                        </dl>
                        @if($selectedShiftItem->booking->timeCorrections->isNotEmpty())<p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">{{ $selectedShiftItem->booking->timeCorrections->count() }} audited time correction request{{ $selectedShiftItem->booking->timeCorrections->count() === 1 ? '' : 's' }} linked to this visit.</p>@endif
                        <a href="{{ route('family.requests.show', $selectedShiftItem->booking->care_request_id) }}" wire:navigate class="mt-4 inline-flex min-h-11 items-center rounded-xl border border-[#2F6F62] px-4 font-semibold text-[#2F6F62]">Open full visit record</a>
                    @else
                        <p class="mt-2 text-sm text-[#607080]">The normal visit and payment record will be created only after this shift is mutually confirmed and enters the safe booking window.</p>
                    @endif
                </section>

                @if($selectedShiftItem->replacementCases->isNotEmpty())
                    <section class="mt-5 rounded-2xl border border-[#DED6CA] bg-white p-4">
                        <h3 class="font-display text-xl font-semibold">Replacement history</h3>
                        <div class="mt-3 space-y-3">
                            @foreach($selectedShiftItem->replacementCases as $replacementCase)
                                <article class="rounded-xl bg-[#F7F2EA] p-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2"><p class="font-semibold">{{ $replacementCase->originalCaregiver?->name ?: 'The assigned caregiver' }} released this shift</p><span class="rounded-full bg-white px-3 py-1 text-xs font-semibold">{{ ucfirst(str_replace('_', ' ', $replacementCase->status)) }}</span></div>
                                    <p class="mt-1 text-sm text-[#526474]">{{ $replacementCase->opened_at?->setTimezone($plan->timezone)->format('M j, Y · g:i A') }} · Reason: {{ $replacementCase->reason ?: 'No reason recorded' }}</p>
                                    <p class="mt-2 text-sm font-semibold">
                                        {{ $replacementCase->offers->count() }} approved backup offer{{ $replacementCase->offers->count() === 1 ? '' : 's' }}
                                        @if($replacementCase->winningOffer?->caregiver)
                                            · {{ $replacementCase->winningOffer->caregiver->name }} accepted
                                        @endif
                                    </p>
                                </article>
                            @endforeach
                        </div>
                        @if($selectedReleasedBookings->isNotEmpty())<div class="mt-4 border-t border-[#E7E0D8] pt-3"><p class="text-xs font-semibold uppercase tracking-wide text-[#7B8794]">Prior visit records retained</p><div class="mt-2 space-y-2">@foreach($selectedReleasedBookings as $releasedBooking)<a href="{{ route('family.requests.show', $releasedBooking->care_request_id) }}" wire:navigate class="flex min-h-11 items-center justify-between rounded-xl bg-[#F7F2EA] px-3 text-sm font-semibold text-[#2F6F62]"><span>Visit #{{ $releasedBooking->id }} · {{ $releasedBooking->caregiver?->name ?: 'Previous caregiver' }}</span><span>{{ ucfirst($releasedBooking->status) }}</span></a>@endforeach</div></div>@endif
                    </section>
                @endif

                @if($selectedShiftItem->replacementCase && $selectedShiftItem->status === 'replacement_needed')
                    <section class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 p-4">
                        <h3 class="font-display text-xl font-semibold text-rose-950">Coverage is still open</h3>
                        <p class="mt-2 text-sm text-rose-900">If a newly approved backup has joined your care team and enabled matching offers, invite the eligible caregivers who have not already responded to this shift.</p>
                        <button wire:click="retryReplacement({{ $selectedShiftItem->replacementCase->id }})" wire:loading.attr="disabled" class="mt-3 min-h-12 rounded-xl bg-rose-800 px-4 font-semibold text-white disabled:opacity-60">Offer to newly eligible backups</button>
                    </section>
                @endif

                @if($selectedShiftItem->handoffs->isNotEmpty())
                    <section class="mt-5 rounded-2xl border border-[#DED6CA] bg-white p-4"><h3 class="font-display text-xl font-semibold">Handoff notes</h3><div class="mt-3 space-y-3">@foreach($selectedShiftItem->handoffs as $handoff)<article class="rounded-xl bg-[#F7F2EA] p-3"><p class="text-sm text-[#324457]">{{ $handoff->notes }}</p><p class="mt-1 text-xs text-[#7B8794]">{{ $handoff->caregiver?->name }} · {{ $handoff->recorded_at->setTimezone($plan->timezone)->format('M j, g:i A') }}</p></article>@endforeach</div></section>
                @endif

                @if($selectedShiftEvents->isNotEmpty())
                    <section class="mt-5 rounded-2xl border border-[#DED6CA] bg-white p-4"><h3 class="font-display text-xl font-semibold">Activity history</h3><ol class="mt-3 space-y-3">@foreach($selectedShiftEvents as $event)<li class="border-l-2 border-[#CFC4B5] pl-3"><p class="text-sm font-semibold">{{ ucfirst(str_replace('_', ' ', $event->event_type)) }}</p><p class="text-xs text-[#7B8794]">{{ $event->actor?->name ?: 'LoLo Care system' }} · {{ $event->happened_at->setTimezone($plan->timezone)->format('M j, Y g:i A') }}</p></li>@endforeach</ol></section>
                @endif
            </section>
        </div>
    @endif
</div>
