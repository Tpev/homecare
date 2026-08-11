@php
    $focusRequests = $familyData['focus_requests'] ?? collect();
    $readyToReview = $focusRequests->filter(function ($request) {
        return $request->status === \App\Models\CareRequest::STATUS_OPEN
            && (int) ($request->pending_candidate_count ?? 0) > 0;
    })->values();
    $needsApplicants = $focusRequests->filter(function ($request) {
        return $request->status === \App\Models\CareRequest::STATUS_OPEN
            && (int) ($request->pending_candidate_count ?? 0) === 0;
    })->values();
    $activeShifts = $familyData['active_shifts'] ?? collect();
    $regularPlans = $familyData['regular_care_plans'] ?? collect();
    $regularSources = $familyData['regular_care_sources'] ?? collect();
    $recentApplicants = $familyData['recent_applicants'] ?? collect();
    $familyDigest = $familyData['notification_digest'] ?? collect();
    $billingReady = (bool) ($familyData['billing_ready'] ?? false);
    $unreadMessages = (int) ($familyData['stats']['unread_messages'] ?? 0);

    $timesheetRequest = $activeShifts->first(function ($request) {
        $booking = $request->booking;

        return $booking
            && in_array($booking->status, [\App\Models\CareBooking::STATUS_COMPLETED, \App\Models\CareBooking::STATUS_REVIEWED], true)
            && ! $booking->family_confirmed_at;
    });
    $liveShiftRequest = $activeShifts->first(function ($request) {
        return in_array((string) ($request->booking?->status ?? ''), [
            \App\Models\CareBooking::STATUS_IN_PROGRESS,
            \App\Models\CareBooking::STATUS_PAUSED,
        ], true);
    });
    $nextShiftRequest = $activeShifts
        ->filter(fn ($request) => in_array((string) ($request->booking?->status ?? ''), [
            \App\Models\CareBooking::STATUS_SCHEDULED,
            \App\Models\CareBooking::STATUS_IN_PROGRESS,
            \App\Models\CareBooking::STATUS_PAUSED,
        ], true))
        ->sortBy(fn ($request) => optional($request->booking?->scheduled_start_at)->timestamp ?? PHP_INT_MAX)
        ->first();
    $reviewRequest = $readyToReview->first();
    $waitingRequest = $needsApplicants->first();
    $nextBooking = $nextShiftRequest?->booking;
    $nextPayment = $nextBooking?->payment;
    $nextPaymentNeedsAction = $nextPayment?->requiresFamilyAction() ?? false;
    $nextPaymentProtected = $nextPayment && in_array($nextPayment->status, [
        \App\Models\CareBookingPayment::STATUS_AUTHORIZED,
        \App\Models\CareBookingPayment::STATUS_CAPTURED,
        \App\Models\CareBookingPayment::STATUS_TRANSFERRED,
    ], true);
    $nextCaregiver = $nextBooking?->caregiver;
    $nextCaregiverName = trim((string) ($nextCaregiver?->name ?? ''));
    $nextCaregiverPhotoUrl = $nextCaregiver?->caregiverProfile?->profile_photo_path
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($nextCaregiver->caregiverProfile->profile_photo_path)
        : null;
    $nextCaregiverInitials = collect(preg_split('/\s+/', $nextCaregiverName) ?: [])
        ->filter()
        ->map(fn ($part) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) $part, 0, 1)))
        ->take(2)
        ->join('');
    $nextCaregiverInitials = $nextCaregiverInitials !== '' ? $nextCaregiverInitials : 'CG';
    $nextVisitTimeLabel = 'time pending';

    if ($nextBooking?->scheduled_start_at) {
        if ($nextBooking->scheduled_start_at->isToday()) {
            $nextVisitTimeLabel = 'today at '.$nextBooking->scheduled_start_at->format('g:i A');
        } elseif ($nextBooking->scheduled_start_at->isTomorrow()) {
            $nextVisitTimeLabel = 'tomorrow at '.$nextBooking->scheduled_start_at->format('g:i A');
        } else {
            $nextVisitTimeLabel = $nextBooking->scheduled_start_at->format('M d').' at '.$nextBooking->scheduled_start_at->format('g:i A');
        }
    }

    $paymentAttentionRequest = $activeShifts->first(
        fn ($request) => $request->booking?->payment?->requiresFamilyAction() ?? false
    );
    $paymentAttentionPlan = $regularPlans->first(function ($plan) {
        return $plan->status === \App\Models\CarePlan::STATUS_PAYMENT_ATTENTION
            || ($plan->nextBooking?->payment?->requiresFamilyAction() ?? false);
    });
    $paymentAttentionBooking = $paymentAttentionRequest?->booking ?: $paymentAttentionPlan?->nextBooking;
    $paymentAttentionPayment = $paymentAttentionBooking?->payment;
    $paymentAttentionHref = $paymentAttentionBooking
        ? route('family.requests.show', $paymentAttentionBooking->care_request_id)
        : ($paymentAttentionPlan ? route('family.care.show', $paymentAttentionPlan->id) : null);
    $paymentAttentionCaregiverName = trim((string) (
        $paymentAttentionRequest?->booking?->caregiver?->name
        ?: $paymentAttentionPlan?->caregiver?->name
        ?: 'Your caregiver'
    ));
    $paymentAttentionVisitLabel = $paymentAttentionBooking?->scheduled_start_at
        ? ($paymentAttentionBooking->scheduled_start_at->isToday()
            ? 'today at '.$paymentAttentionBooking->scheduled_start_at->format('g:i A')
            : $paymentAttentionBooking->scheduled_start_at->format('M d').' at '.$paymentAttentionBooking->scheduled_start_at->format('g:i A'))
        : 'for the next visit';

    $nextActionTitle = 'Get care when you are ready';
    $nextActionDescription = 'Post a short request. LoLo will show caregivers the schedule, location, and care needs clearly.';
    $nextActionRoute = route('family.requests.create');
    $nextActionLabel = 'Get care';
    $nextActionTone = 'primary';

    if (! $billingReady) {
        $nextActionTitle = 'Add a payment method';
        $nextActionDescription = 'Add a card once so hiring can happen without a stressful checkout later.';
        $nextActionRoute = route('family.billing.show');
        $nextActionLabel = 'Set up billing';
        $nextActionTone = 'warning';
    }

    if ($waitingRequest) {
        $nextActionTitle = 'Invite caregivers';
        $nextActionDescription = 'One request is waiting for replies. Inviting trusted caregivers usually gets a faster answer.';
        $nextActionRoute = route('caregivers.search');
        $nextActionLabel = 'Find caregivers';
        $nextActionTone = 'info';
    }

    if ($reviewRequest) {
        $nextActionTitle = 'Choose a caregiver';
        $nextActionDescription = 'A caregiver is waiting for your review. Open the request, compare the profile, then hire or message.';
        $nextActionRoute = route('family.requests.show', $reviewRequest->id);
        $nextActionLabel = 'Review caregiver';
        $nextActionTone = 'info';
    }

    if ($unreadMessages > 0) {
        $nextActionTitle = 'Reply to your caregiver';
        $nextActionDescription = $unreadMessages.' message'.($unreadMessages === 1 ? '' : 's').' waiting for your response.';
        $nextActionRoute = route('messages.index');
        $nextActionLabel = 'Open messages';
        $nextActionTone = 'info';
    }

    if ($nextShiftRequest) {
        $nextActionTitle = 'Your next visit is ready';
        $nextActionDescription = 'Open the visit to see the caregiver, time, address, payment status, and support options.';
        $nextActionRoute = route('family.requests.show', $nextShiftRequest->id);
        $nextActionLabel = 'Open visit';
        $nextActionTone = 'success';
    }

    if ($liveShiftRequest) {
        $nextActionTitle = 'Care is happening now';
        $nextActionDescription = 'Follow the active visit, message the caregiver, or contact support if something feels wrong.';
        $nextActionRoute = route('family.requests.show', $liveShiftRequest->id);
        $nextActionLabel = 'Track visit';
        $nextActionTone = 'success';
    }

    if ($timesheetRequest) {
        $nextActionTitle = 'Approve caregiver hours';
        $nextActionDescription = 'The caregiver submitted their hours. Check the time and approve payment if everything looks right.';
        $nextActionRoute = route('family.requests.show', $timesheetRequest->id);
        $nextActionLabel = 'Review hours';
        $nextActionTone = 'success';
    }

    if ($paymentAttentionHref) {
        $nextActionTitle = 'Fix payment for your visit';
        $nextActionDescription = 'Confirm or replace your card so this visit is payment protected.';
        $nextActionRoute = $paymentAttentionHref;
        $nextActionLabel = 'Fix payment';
        $nextActionTone = 'warning';
    }

    $secondaryActionRoute = route('family.requests.create');
    $secondaryActionLabel = 'Start new care';

    if ($nextActionRoute === route('family.requests.create')) {
        $secondaryActionRoute = route('caregivers.search');
        $secondaryActionLabel = 'Find caregivers';
    }

    $attentionItems = collect([
        $paymentAttentionHref ? [
            'title' => 'Payment needs attention',
            'body' => $paymentAttentionPayment?->last_error ?: 'Confirm or replace your card for the next visit.',
            'label' => 'Fix payment',
            'href' => $paymentAttentionHref,
            'tone' => 'amber',
        ] : null,
        $timesheetRequest ? [
            'title' => 'Hours need approval',
            'body' => $timesheetRequest->title,
            'label' => 'Review hours',
            'href' => route('family.requests.show', $timesheetRequest->id),
            'tone' => 'green',
        ] : null,
        $reviewRequest ? [
            'title' => 'Caregiver waiting',
            'body' => $reviewRequest->title,
            'label' => 'Review caregiver',
            'href' => route('family.requests.show', $reviewRequest->id),
            'tone' => 'blue',
        ] : null,
        $waitingRequest ? [
            'title' => 'Need more replies',
            'body' => $waitingRequest->title,
            'label' => 'Invite caregivers',
            'href' => route('caregivers.search'),
            'tone' => 'amber',
        ] : null,
        $unreadMessages > 0 ? [
            'title' => 'Unread messages',
            'body' => $unreadMessages.' conversation'.($unreadMessages === 1 ? '' : 's').' need your attention.',
            'label' => 'Open messages',
            'href' => route('messages.index'),
            'tone' => 'blue',
        ] : null,
        ! $billingReady ? [
            'title' => 'Billing not ready',
            'body' => 'Add a card so you can hire without delay.',
            'label' => 'Set up billing',
            'href' => route('family.billing.show'),
            'tone' => 'amber',
        ] : null,
    ])->filter()->values();

    $supportingAttentionItems = $attentionItems
        ->reject(fn ($item) => ($item['href'] ?? null) === $nextActionRoute)
        ->values();

    $careSummaryTitle = 'No visit scheduled yet.';
    $careSummaryBody = 'Get care or book a caregiver again whenever you are ready.';
    $careSummaryMeta = 'Nothing is waiting on you.';

    if (! $billingReady) {
        $careSummaryTitle = 'Billing is not ready yet.';
        $careSummaryBody = 'Hiring will be smoother once a card is on file.';
        $careSummaryMeta = 'Add it once, then choose caregivers without checkout stress.';
    }

    if ($waitingRequest) {
        $careSummaryTitle = 'Your request is posted.';
        $careSummaryBody = 'Caregivers can see it now. Inviting someone trusted may speed up replies.';
        $careSummaryMeta = $waitingRequest->title;
    }

    if ($reviewRequest) {
        $careSummaryTitle = 'A caregiver replied.';
        $careSummaryBody = 'You have someone to review before choosing who to hire.';
        $careSummaryMeta = $reviewRequest->title;
    }

    if ($unreadMessages > 0) {
        $careSummaryTitle = 'Messages are waiting.';
        $careSummaryBody = 'A caregiver or LoLo update is waiting for your reply.';
        $careSummaryMeta = 'Open Messages when you have a minute.';
    }

    if ($nextShiftRequest) {
        $careSummaryTitle = 'You have 1 upcoming visit.';
        $careSummaryBody = ($nextCaregiverName !== '' ? $nextCaregiverName : 'Your caregiver').' is coming '.$nextVisitTimeLabel.'.';
        $careSummaryMeta = $nextPaymentNeedsAction
            ? 'Payment confirmation is needed for this visit.'
            : ($nextPaymentProtected ? 'Payment confirmed. No action needed.' : 'Your card will be confirmed closer to the visit.');
    }

    if ($liveShiftRequest) {
        $careSummaryTitle = 'Care is happening now.';
        $careSummaryBody = ($nextCaregiverName !== '' ? $nextCaregiverName : 'Your caregiver').' is checked in for this visit.';
        $careSummaryMeta = 'Open the visit if you need chat, location, or support.';
    }

    if ($timesheetRequest) {
        $careSummaryTitle = 'Hours are ready to review.';
        $careSummaryBody = 'Check the submitted time, then approve payment if everything looks right.';
        $careSummaryMeta = $timesheetRequest->title;
    }

    if ($paymentAttentionHref) {
        $careSummaryTitle = 'Payment needs attention.';
        $careSummaryBody = $paymentAttentionCaregiverName.' is scheduled '.$paymentAttentionVisitLabel.'.';
        $careSummaryMeta = $paymentAttentionPayment?->last_error ?: 'Confirm or replace your card for this visit.';
    }

    $heroToneClass = $nextActionTone === 'warning' ? 'bg-amber-800' : 'bg-[#23483F]';
@endphp

<section class="rounded-3xl border border-[#E4DDD3] bg-[#FFFCF8] p-4 shadow-sm sm:p-6 lg:p-7">
    <div class="flex flex-col justify-between gap-5 rounded-3xl p-5 text-white sm:p-6 lg:flex-row lg:items-end {{ $heroToneClass }}">
        <div class="max-w-3xl">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#D8E8D4]">Right now</p>
            <h1 class="mt-2 text-3xl font-display font-semibold leading-tight text-white sm:text-4xl">{{ $careSummaryTitle }}</h1>
            <p class="mt-3 text-base leading-7 text-[#F7F1E8]">{{ $careSummaryBody }}</p>
            <div class="mt-5 rounded-2xl border border-white/20 bg-white/10 px-4 py-3">
                <p class="text-sm font-medium text-[#F7F1E8]">{{ $careSummaryMeta }}</p>
            </div>
        </div>
        <div class="flex shrink-0 flex-col gap-3 sm:flex-row sm:flex-wrap lg:justify-end">
            <a href="{{ $nextActionRoute }}" wire:navigate class="inline-flex min-h-12 items-center justify-center rounded-xl bg-white px-5 text-base font-semibold text-[#23483F] shadow-sm transition hover:bg-[#F8F0E2]">
                {{ $nextActionLabel }}
            </a>
            <a href="{{ $secondaryActionRoute }}" wire:navigate class="inline-flex min-h-12 items-center justify-center rounded-xl border border-white/35 px-5 text-base font-semibold text-white transition hover:bg-white/10">
                {{ $secondaryActionLabel }}
            </a>
        </div>
    </div>

    @if ($supportingAttentionItems->isNotEmpty())
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            @foreach ($supportingAttentionItems->take(2) as $item)
                <a href="{{ $item['href'] }}" wire:navigate class="group flex min-h-20 items-center justify-between gap-3 rounded-2xl border border-[#E4DDD3] bg-white px-4 py-3 text-[#17313F] transition hover:border-[#23483F]/35 hover:shadow-sm">
                    <span>
                        <span class="block text-sm font-semibold">{{ $item['title'] }}</span>
                        <span class="mt-0.5 block text-xs text-[#607080]">{{ $item['body'] }}</span>
                    </span>
                    <span class="shrink-0 text-sm font-semibold text-[#2F6F62] group-hover:text-[#23483F]">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    @endif
</section>

@if ($familyDigest->count() > 0)
    <section class="rounded-[1.4rem] border border-sky-200 bg-sky-50 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">New updates</p>
                <p class="mt-1 text-base font-semibold text-sky-950">{{ $familyDigest->first()['title'] }}</p>
                <p class="mt-1 text-sm text-sky-900">{{ $familyDigest->first()['body'] }}</p>
            </div>
            <a href="{{ $familyDigest->first()['url'] ?: route('family.notifications.index') }}" wire:navigate class="inline-flex min-h-11 items-center justify-center rounded-xl bg-white px-4 text-sm font-semibold text-sky-900 shadow-sm">
                Open update
            </a>
        </div>
    </section>
@endif

<section class="grid gap-5 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
    <div class="space-y-5">
        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-xl font-semibold">Next visit</h2>
                    <a href="{{ route('family.care.history') }}" wire:navigate class="text-sm font-semibold text-[#2F6F62] underline underline-offset-4">Care history</a>
                </div>
            </x-slot:header>

            @if ($nextShiftRequest)
                <div class="rounded-2xl border border-[#D8E1D7] bg-[#F2F8F4] p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">{{ strtoupper(str_replace('_', ' ', (string) $nextBooking?->status)) }}</p>
                    <h3 class="mt-2 font-display text-xl font-semibold text-[#17313F]">{{ $nextShiftRequest->title }}</h3>
                    <p class="mt-2 text-base text-[#324457]">
                        {{ optional($nextBooking?->scheduled_start_at)->format('M d, g:i A') ?: 'Time pending' }}
                        @if ($nextBooking?->scheduled_end_at)
                            to {{ $nextBooking->scheduled_end_at->format('g:i A') }}
                        @endif
                    </p>
                    <p class="mt-1 text-sm text-[#607080]">{{ $nextShiftRequest->city }}, {{ $nextShiftRequest->state }}</p>
                    @if ($nextCaregiverName !== '')
                        <div class="mt-3 flex items-center gap-3 rounded-xl border border-white/80 bg-white/80 px-3 py-2">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#17313F] text-sm font-semibold text-white">
                                @if ($nextCaregiverPhotoUrl)
                                    <img src="{{ $nextCaregiverPhotoUrl }}" alt="{{ $nextCaregiverName }}" class="h-full w-full object-cover">
                                @else
                                    <span aria-hidden="true">{{ $nextCaregiverInitials }}</span>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#607080]">Coming</p>
                                <p class="truncate text-sm font-semibold text-[#17313F]">{{ $nextCaregiverName }}</p>
                            </div>
                        </div>
                    @endif
                    <div class="mt-4">
                        <a href="{{ route('family.requests.show', $nextShiftRequest->id) }}" wire:navigate class="hc-primary-button w-full sm:w-auto">Open visit</a>
                    </div>
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-[#D6CCBE] p-5 text-sm text-[#607080]">
                    No upcoming visit yet. Get care or hire from an open request.
                </div>
            @endif
        </x-card>
    </div>

    <div class="space-y-5">
        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-xl font-semibold">Book again</h2>
                        <p class="text-sm text-[#607080]">Book one more visit or make care weekly.</p>
                    </div>
                    <a href="{{ route('family.care.index') }}" wire:navigate class="hc-link">Weekly care</a>
                </div>
            </x-slot:header>

            <div class="space-y-3">
                @forelse ($regularSources->take(3) as $request)
                    @php
                        $hired = $request->applications->first();
                        $caregiverName = trim((string) ($hired?->caregiver?->name ?? ''));
                        $caregiverFirstName = $caregiverName !== ''
                            ? \Illuminate\Support\Str::of($caregiverName)->before(' ')
                            : 'caregiver';
                    @endphp
                    <div class="rounded-2xl border border-[#E4DDD3] bg-white p-4">
                        <p class="font-display text-lg font-semibold text-[#17313F]">{{ $hired?->caregiver?->name ?: 'Hired caregiver' }}</p>
                        <p class="mt-1 text-sm text-[#607080]">{{ $request->title }}</p>
                        <div class="mt-3">
                            <a href="{{ route('family.requests.book_again', $request->id) }}" wire:navigate class="hc-secondary-button w-full sm:w-auto">Book {{ $caregiverFirstName }} again</a>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-[#D6CCBE] p-5 text-sm text-[#607080]">
                        After you complete and approve a visit, the caregiver appears here for easy rebooking.
                    </div>
                @endforelse
            </div>
        </x-card>

    </div>
</section>
