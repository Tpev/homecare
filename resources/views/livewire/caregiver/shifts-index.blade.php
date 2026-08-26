<div>
    <div class="hc-page py-6 space-y-5">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        @php
            $statusTone = fn (string $value) => match ($value) {
                \App\Models\CareBooking::STATUS_IN_PROGRESS => 'bg-emerald-100 text-emerald-700',
                \App\Models\CareBooking::STATUS_PAUSED => 'bg-amber-100 text-amber-800',
                \App\Models\CareBooking::STATUS_SCHEDULED => 'bg-[#E8F0FF] text-[#4F6FAF]',
                \App\Models\CareBooking::STATUS_COMPLETED => 'bg-indigo-100 text-indigo-700',
                \App\Models\CareBooking::STATUS_REVIEWED => 'bg-emerald-100 text-emerald-700',
                \App\Models\CareBooking::STATUS_DISPUTED => 'bg-rose-100 text-rose-700',
                \App\Models\CareBooking::STATUS_CANCELLED => 'bg-[#E9E1D5] text-[#4B5B6B]',
                default => 'bg-[#F0E9E1] text-[#4B5B6B]',
            };
            $filterOptions = [
                ['label' => 'All', 'value' => 'all'],
                ['label' => 'Scheduled', 'value' => \App\Models\CareBooking::STATUS_SCHEDULED],
                ['label' => 'In progress', 'value' => \App\Models\CareBooking::STATUS_IN_PROGRESS],
                ['label' => 'Paused', 'value' => \App\Models\CareBooking::STATUS_PAUSED],
                ['label' => 'Completed', 'value' => \App\Models\CareBooking::STATUS_COMPLETED],
                ['label' => 'Reviewed', 'value' => \App\Models\CareBooking::STATUS_REVIEWED],
                ['label' => 'Issues', 'value' => 'issues'],
                ['label' => 'Time to update', 'value' => 'time_correction'],
            ];
        @endphp

        <section class="hc-brand-panel">
            <div class="relative space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="hc-brand-kicker text-[#E8E0FF]">My visits</p>
                        <h1 class="mt-1 text-2xl font-display font-semibold leading-tight sm:text-3xl">Your visits, in one timeline.</h1>
                        <p class="mt-1 text-sm text-[#F7F1E8]/82">Regular, one-time, and Continuous Coverage visits are ordered together by date.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('caregiver.work-inbox.index') }}" wire:navigate><x-button color="white" light sm>Work Inbox</x-button></a>
                        <a href="{{ route('caregiver.earnings.index') }}" wire:navigate><x-button color="white" light sm>Earnings</x-button></a>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <div class="hc-brand-stat"><p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Active</p><p class="mt-1 text-lg font-semibold">{{ (int) ($counts['active'] ?? 0) }}</p></div>
                    <div class="hc-brand-stat"><p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Scheduled</p><p class="mt-1 text-lg font-semibold">{{ (int) ($counts['scheduled'] ?? 0) }}</p></div>
                    <div class="hc-brand-stat"><p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Completed</p><p class="mt-1 text-lg font-semibold">{{ (int) ($counts['completed'] ?? 0) }}</p></div>
                    <div class="hc-brand-stat"><p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Needs action</p><p class="mt-1 text-lg font-semibold">{{ (int) ($counts['time_correction_actions'] ?? 0) + (int) ($counts['issues'] ?? 0) }}</p></div>
                </div>

                @if (!empty($nextVisit))
                    @php
                        $nextVisitBooking = $nextVisit['booking'];
                        $nextVisitCoverage = $nextVisit['coverage_shift'];
                        $nextVisitTitle = $nextVisitBooking?->careRequest?->title ?? $nextVisitCoverage?->plan?->title ?? 'Upcoming visit';
                        $nextVisitLocation = $nextVisitBooking
                            ? collect([$nextVisitBooking->careRequest?->city, $nextVisitBooking->careRequest?->state])->filter()->implode(', ')
                            : collect([data_get($nextVisitCoverage?->plan?->address_snapshot, 'city'), data_get($nextVisitCoverage?->plan?->address_snapshot, 'state')])->filter()->implode(', ');
                    @endphp
                    <div class="rounded-[1.4rem] border border-[#D3CBF0] bg-[rgba(255,255,255,0.08)] px-3 py-3">
                        <p class="text-xs uppercase tracking-[0.14em] text-[#E8E0FF]">Next visit</p>
                        <p class="mt-1 text-sm font-semibold text-[#FAF9F7]">{{ $nextVisitTitle }}</p>
                        <p class="text-xs text-[#F0E9E1]/82">
                            {{ optional($nextVisit['scheduled_start_at'])->format('D, M d \\a\\t H:i') }}@if($nextVisitLocation) · {{ $nextVisitLocation }}@endif
                        </p>
                    </div>
                @endif
            </div>
        </section>

        <div class="sticky top-16 z-20 -mx-1 px-1">
            <div class="rounded-2xl border border-[#DED6CA] bg-[rgba(255,253,250,0.95)] p-2 shadow-sm backdrop-blur">
                <div class="overflow-x-auto">
                    <div class="flex min-w-max gap-1">
                        @foreach ($filterOptions as $option)
                            <button type="button" wire:click="$set('status', '{{ $option['value'] }}')" class="h-11 min-w-[8.5rem] rounded-xl px-3 text-sm font-medium transition {{ $status === $option['value'] ? 'bg-[#0F3D3E] text-[#FAF9F7]' : 'text-[#6E746F] hover:bg-[#F5F1EB] hover:text-[#0F3D3E]' }}">
                                {{ $option['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <section class="space-y-3" aria-label="Care visits ordered by date">
            @forelse ($visitTimeline as $visit)
                @if ($visit['kind'] === 'coverage')
                    @php
                        $coverageVisit = $visit['coverage_shift'];
                        $coverageStart = $coverageVisit->scheduled_start_at->copy()->setTimezone($coverageVisit->plan->timezone);
                        $coverageEnd = $coverageVisit->scheduled_end_at->copy()->setTimezone($coverageVisit->plan->timezone);
                        $coverageAddress = (array) $coverageVisit->plan->address_snapshot;
                        $coverageLocation = collect([data_get($coverageAddress, 'address_line1'), data_get($coverageAddress, 'city'), data_get($coverageAddress, 'state'), data_get($coverageAddress, 'zip')])->filter()->implode(', ');
                        $coverageNeedsAttention = $coverageVisit->status === \App\Models\ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION;
                        $insidePreparationWindow = $coverageVisit->scheduled_start_at->lte(now()->addHours($coveragePreparationHours));
                    @endphp
                    <article id="coverage-commitment-{{ $coverageVisit->id }}" class="scroll-mt-32 rounded-lg border {{ $coverageNeedsAttention ? 'border-amber-300 bg-amber-50' : 'border-[#BFD8CB] bg-white' }} p-5 shadow-sm sm:p-6" wire:key="{{ $visit['key'] }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="font-display text-xl font-semibold text-[#17313F]">{{ $coverageVisit->plan->title }}</h2>
                                    <span class="rounded-full bg-[#E8F4EE] px-3 py-1 text-xs font-semibold text-[#17634F]">Continuous Coverage</span>
                                </div>
                                <p class="mt-1 text-base text-[#526474]">For {{ $coverageVisit->plan->recipientName() }} · Family contact: {{ $coverageVisit->plan->family?->name }}</p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $coverageNeedsAttention ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-800' }}">{{ $coverageNeedsAttention ? 'SETUP NEEDS ATTENTION' : 'CONFIRMED' }}</span>
                        </div>
                        <div class="mt-4 grid grid-cols-1 gap-3 text-base text-[#526474] md:grid-cols-3">
                            <div class="rounded-md border border-[#DED6CA] bg-[#F7F5F1] px-4 py-3"><p class="text-sm font-semibold text-[#6A7784]">When</p><p class="mt-1 font-semibold text-[#17313F]">{{ $coverageStart->format('D, M j, g:i A') }}</p><p>to {{ $coverageEnd->format('g:i A T') }}</p></div>
                            <div class="rounded-md border border-[#DED6CA] bg-[#F7F5F1] px-4 py-3"><p class="text-sm font-semibold text-[#6A7784]">Care location</p><p class="mt-1 font-semibold text-[#17313F]">{{ $coverageLocation ?: 'Address available in Coverage' }}</p></div>
                            <div class="rounded-md border {{ $coverageNeedsAttention ? 'border-amber-300 bg-amber-50' : 'border-[#BFD8CB] bg-[#F1F8F4]' }} px-4 py-3"><p class="text-sm font-semibold text-[#6A7784]">Expected earnings</p><p class="mt-1 font-semibold text-[#17313F]">{{ $coverageEarningEstimates->get($coverageVisit->id) }}</p><p class="font-semibold {{ $coverageNeedsAttention ? 'text-amber-800' : 'text-emerald-700' }}">{{ $coverageNeedsAttention ? 'LoLo Care is reviewing visit setup' : 'Confirmed commitment' }}</p></div>
                        </div>
                        <div class="mt-4 rounded-xl {{ $coverageNeedsAttention ? 'bg-amber-100 text-amber-950' : 'bg-[#F7F2EA] text-[#526474]' }} p-3 text-sm">
                            @if($coverageNeedsAttention)
                                This visit remains confirmed, but its booking or payment setup needs attention. You do not need to create another visit.
                            @elseif($insidePreparationWindow)
                                Visit controls are being prepared. They will appear here automatically; no duplicate booking is needed.
                            @else
                                Visit controls, including check-in, will appear here closer to the shift. No payment is processed this early.
                            @endif
                        </div>
                        <a href="{{ route('caregiver.continuous-coverage.index', ['tab' => 'schedule']).'#coverage-shift-'.$coverageVisit->id }}" wire:navigate class="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-[#2F6F62] px-4 font-semibold text-[#2F6F62] sm:w-auto">Manage in Coverage</a>
                    </article>
                @else
                    @php
                        $booking = $visit['booking'];
                        $request = $booking->careRequest;
                        $bookingStatus = (string) $booking->status;
                        $isRegular = (bool) $booking->care_plan_id;
                        $payment = $booking->payment;
                        $timeCorrection = $booking->timeCorrections->first();
                        $paymentNeedsAction = $payment && in_array($payment->status, [\App\Models\CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED, \App\Models\CareBookingPayment::STATUS_REAUTH_REQUIRED, \App\Models\CareBookingPayment::STATUS_FAILED], true);
                        $paymentProtected = $payment && in_array($payment->status, [\App\Models\CareBookingPayment::STATUS_AUTHORIZED, \App\Models\CareBookingPayment::STATUS_CAPTURED, \App\Models\CareBookingPayment::STATUS_TRANSFERRED], true);
                        $estimatedMinutes = (int) ($booking->expected_minutes ?: optional($booking->scheduled_start_at)->diffInMinutes($booking->scheduled_end_at, false));
                        $grossRate = ((int) ($booking->caregiver_gross_rate_cents ?: config('marketplace.pricing_v2.caregiver_gross_hourly_cents', 2700))) / 100;
                        $estimatedEarnings = $grossRate * max(0, $estimatedMinutes) / 60;
                        $ctaLabel = match ($bookingStatus) {
                            \App\Models\CareBooking::STATUS_SCHEDULED => 'Start visit',
                            \App\Models\CareBooking::STATUS_IN_PROGRESS => 'Continue visit',
                            \App\Models\CareBooking::STATUS_PAUSED => 'Resume visit',
                            \App\Models\CareBooking::STATUS_COMPLETED => 'View recap',
                            \App\Models\CareBooking::STATUS_REVIEWED => 'View visit',
                            \App\Models\CareBooking::STATUS_DISPUTED => 'Open dispute view',
                            \App\Models\CareBooking::STATUS_CANCELLED => 'View details',
                            default => 'Open visit',
                        };
                        if ($timeCorrection?->status === \App\Models\CareBookingTimeCorrection::STATUS_CHANGES_REQUESTED) {
                            $ctaLabel = 'Update requested hours';
                        } elseif ($timeCorrection?->status === \App\Models\CareBookingTimeCorrection::STATUS_PENDING_FAMILY) {
                            $ctaLabel = 'View pending correction';
                        }
                    @endphp
                    <article class="rounded-lg border border-[#DED6CA] bg-white p-5 shadow-sm sm:p-6" wire:key="{{ $visit['key'] }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="font-display text-xl font-semibold text-[#17313F]">{{ $request?->title ?? 'Care request' }}</h2>
                                    @if ($booking->plan_visit_kind === 'completed_extra')
                                        <span class="rounded-full bg-[#E8F4EE] px-3 py-1 text-xs font-semibold text-[#17634F]">Family-approved extra visit</span>
                                    @elseif ($isRegular)
                                        <span class="rounded-full bg-[#E8F4EE] px-3 py-1 text-xs font-semibold text-[#17634F]">Regular care</span>
                                    @endif
                                    @if ($timeCorrection)<span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">{{ $timeCorrection->statusLabel() }}</span>@endif
                                </div>
                                <p class="mt-1 text-base text-[#526474]">For {{ $request?->recipient?->full_name ?: $booking->family?->name }} · Family contact: {{ $booking->family?->name ?? 'Unknown' }}</p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusTone($bookingStatus) }}">{{ strtoupper($bookingStatus) }}</span>
                        </div>
                        <div class="mt-4 grid grid-cols-1 gap-3 text-base text-[#526474] md:grid-cols-3">
                            <div class="rounded-md border border-[#DED6CA] bg-[#F7F5F1] px-4 py-3"><p class="text-sm font-semibold text-[#6A7784]">When</p><p class="mt-1 font-semibold text-[#17313F]">{{ optional($booking->scheduled_start_at)->format('D, M j, g:i A') ?: '-' }}</p><p>to {{ optional($booking->scheduled_end_at)->format('g:i A') ?: '-' }}</p></div>
                            <div class="rounded-md border border-[#DED6CA] bg-[#F7F5F1] px-4 py-3"><p class="text-sm font-semibold text-[#6A7784]">Care location</p><p class="mt-1 font-semibold text-[#17313F]">{{ $request?->address_line1 ?: 'Address available in visit' }}</p><p>{{ $request?->city }}, {{ $request?->state }} {{ $request?->zip }}</p></div>
                            <div class="rounded-md border {{ $paymentNeedsAction ? 'border-amber-300 bg-amber-50' : 'border-[#BFD8CB] bg-[#F1F8F4]' }} px-4 py-3"><p class="text-sm font-semibold text-[#6A7784]">Expected earnings</p><p class="mt-1 text-xl font-semibold text-[#17313F]">${{ number_format($estimatedEarnings, 2) }}</p><p class="font-semibold {{ $paymentNeedsAction ? 'text-amber-800' : 'text-emerald-700' }}">{{ $paymentNeedsAction ? 'Family action needed' : ($paymentProtected ? 'Payment protected' : 'Payment checked before visit') }}</p></div>
                        </div>
                        <div class="mt-4 grid grid-cols-1 gap-2 sm:flex sm:flex-wrap">
                            @if ($request)<a href="{{ route('care-requests.apply', $request->id) }}" wire:navigate class="hc-primary-button w-full sm:w-auto">{{ $ctaLabel }}</a>@endif
                            @if ($booking->application?->conversation)<a href="{{ route('messages.show', $booking->application->conversation->id) }}" wire:navigate class="hc-secondary-button w-full sm:w-auto">Open chat</a>@endif
                        </div>
                    </article>
                @endif
            @empty
                <div class="rounded-2xl border border-dashed border-[#D6CCBE] bg-[rgba(255,253,250,0.98)] px-4 py-8 text-center text-sm text-[#607080]">
                    No visits match this filter. Confirmed Continuous Coverage commitments and prepared visits will appear together here by date.
                </div>
            @endforelse
        </section>

        @if ($visitTimeline->hasPages())
            <div>{{ $visitTimeline->links() }}</div>
        @endif

        @if ($hiredWithoutBooking->count() > 0)
            <x-card>
                <x-slot:header><h2 class="font-display text-lg font-semibold">Pending visit setup</h2></x-slot:header>
                <div class="space-y-2">
                    @foreach ($hiredWithoutBooking as $application)
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm text-[#17313F]">{{ $application->careRequest?->title }}</p>
                                <a href="{{ route('care-requests.apply', $application->care_request_id) }}" wire:navigate class="text-xs font-medium text-indigo-700 underline underline-offset-2">Open request</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endif
    </div>
</div>
