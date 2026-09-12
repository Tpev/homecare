<div
    x-data="{ confirmingPayment: false, decisionTrigger: null, inviteTrigger: null }"
    x-on:click.capture="if ($event.target.closest('[data-invite-trigger]')) inviteTrigger = $event.target.closest('[data-invite-trigger]')"
    x-on:care-decision-opened.window="decisionTrigger = document.activeElement; $nextTick(() => { $refs.decisionReview?.focus(); $refs.decisionReview?.scrollIntoView({ block: 'start' }); })"
    x-on:care-decision-closed.window="$nextTick(() => decisionTrigger?.isConnected && decisionTrigger.focus())"
    x-on:care-workspace-tab-changed.window="$nextTick(() => $el.querySelector('.hc-care-tab[aria-current]')?.focus())"
    x-on:caregiver-invite-panel-closed.window="$nextTick(() => (inviteTrigger?.isConnected ? inviteTrigger : ($refs.inviteCaregiverTrigger || $el.querySelector('.hc-recruit-stage[aria-current]')))?.focus())"
    x-on:payment-confirmation-started.window="confirmingPayment = true"
    x-on:payment-confirmation-finished.window="confirmingPayment = false"
    class="hc-page hc-care-workspace hc-recruitment space-y-5 pb-28 pt-5 sm:space-y-6 sm:pb-8 sm:pt-8"
    data-inline-support
>
    @if($aiPrepared)
        <x-alert color="blue">LoLo prepared the correction details. Review and edit them before you send, submit, or approve anything. Nothing was changed automatically.</x-alert>
    @endif
    @if (session('warning'))
        <x-alert color="amber">{{ session('warning') }}</x-alert>
    @endif

    @if ($errors->has('billing'))
        <x-alert color="red">{{ $errors->first('billing') }}</x-alert>
    @endif

    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @if ($requestItem->is_private && $requestItem->status === \App\Models\CareRequest::STATUS_OPEN && $requestItem->invitations->isEmpty())
        <x-alert color="amber">
            This request is private and cannot be discovered in the caregiver marketplace. Invite at least one caregiver when you are ready.
        </x-alert>
    @endif

    @php
        $booking = $requestItem->booking;
        $pricing = app(\App\Support\MarketplacePricing::class);
        $payment = $booking?->payment;
        $needsPaymentAuthorization = $payment && in_array($payment->status, [
            \App\Models\CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
            \App\Models\CareBookingPayment::STATUS_REAUTH_REQUIRED,
            \App\Models\CareBookingPayment::STATUS_FAILED,
        ], true);
        $hiredApplication = $requestItem->applications->firstWhere('status', \App\Models\CareRequestApplication::STATUS_HIRED);
        $hiredCaregiverName = trim((string) ($booking?->caregiver?->name ?? $hiredApplication?->caregiver?->name ?? ''));
        $hiredCaregiverFirstName = $hiredCaregiverName !== ''
            ? \Illuminate\Support\Str::of($hiredCaregiverName)->before(' ')
            : 'caregiver';
        $hiredConversation = $hiredApplication?->conversation;
        $caregiverTabSubtitle = $hiredApplication
            ? 'Caregiver selected'
            : $requestItem->applications->count().' to review';
        $noShowEligibleAt = $booking?->scheduled_start_at?->copy()->addMinutes(30);
        $canMarkNoShow = $booking
            && $booking->status === \App\Models\CareBooking::STATUS_SCHEDULED
            && $noShowEligibleAt
            && now()->gte($noShowEligibleAt);
        $serviceAddress = trim(collect([
            $requestItem->address_line1,
            $requestItem->address_line2,
            trim($requestItem->city.', '.$requestItem->state.' '.$requestItem->zip),
        ])->filter()->implode(', '));
        $serviceMapEmbedUrl = $serviceAddress !== ''
            ? 'https://www.google.com/maps?q='.rawurlencode($serviceAddress).'&output=embed'
            : null;
        $serviceMapOpenUrl = $serviceAddress !== ''
            ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($serviceAddress)
            : null;
        $familyReview = $booking?->reviews?->firstWhere('reviewer_user_id', (int) auth()->id());
        $caregiverReview = $booking?->reviews?->firstWhere('reviewer_user_id', (int) ($booking?->caregiver_user_id ?? 0));
        $latestTimeCorrection = $booking?->timeCorrections?->first();
        $paymentTimeCorrection = $latestTimeCorrection?->status === \App\Models\CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED
            ? $latestTimeCorrection
            : null;
        $paymentActionAmountCents = $paymentTimeCorrection
            ? (int) data_get($paymentTimeCorrection->financial_preview, 'target_charge_cents', 0)
            : (int) data_get($payment?->metadata, 'requested_authorization_cents', 0);
        $paymentActionAmountLabel = $paymentActionAmountCents > 0
            ? '$'.number_format($paymentActionAmountCents / 100, 2)
            : null;
        $paymentActionButtonLabel = $paymentTimeCorrection && $paymentActionAmountLabel
            ? 'Confirm '.$paymentActionAmountLabel.' payment'
            : 'Confirm card authorization';
        $showPaymentAuthorizationAction = $needsPaymentAuthorization || (bool) $paymentTimeCorrection;
        $hasActiveTimeCorrection = $latestTimeCorrection
            && in_array($latestTimeCorrection->status, \App\Models\CareBookingTimeCorrection::activeStatuses(), true);
        $timesheetNeedsReview = $booking
            && in_array($booking->status, [\App\Models\CareBooking::STATUS_COMPLETED, \App\Models\CareBooking::STATUS_REVIEWED], true)
            && ! $booking->family_confirmed_at
            && ! $hasActiveTimeCorrection;
        $canLeaveFamilyReview = $booking
            && in_array($booking->status, [\App\Models\CareBooking::STATUS_COMPLETED, \App\Models\CareBooking::STATUS_REVIEWED], true)
            && $booking->family_confirmed_at
            && ! $familyReview;
        $workedMinutes = (int) ($booking?->worked_minutes ?? 0);
        $workedLabel = sprintf('%dh %02dm', intdiv($workedMinutes, 60), $workedMinutes % 60);
        $applicationRate = (float) ($hiredApplication?->proposed_rate ?? 0);
        $profileRate = (float) ($hiredApplication?->caregiver?->caregiverProfile?->resolvePlatformHourlyRate() ?? 0);
        $baseShiftRate = $applicationRate > 0
            ? $applicationRate
            : ($profileRate > 0 ? $profileRate : (float) config('marketplace.family_estimate_hourly_rate', 30.00));
        $shiftRate = $booking
            ? $pricing->hourlyRateForBooking($booking, $baseShiftRate)
            : $pricing->hourlyRateForFamily($requestItem->family, $baseShiftRate);
        $shiftEarnings = $workedMinutes > 0 && $shiftRate > 0
            ? round(($workedMinutes / 60) * $shiftRate, 2)
            : 0;
        $usesPricingV2 = $booking && $pricing->usesCurrentPricing($booking);
        $shiftQuote = $usesPricingV2 && $workedMinutes > 0
            ? $pricing->quoteForCurrentBooking($booking, $workedMinutes)
            : null;
        $shiftEarnings = $shiftQuote ? ((int) $shiftQuote['family_care_amount_cents'] / 100) : $shiftEarnings;
        $processingFeeAmount = $shiftQuote ? ((int) $shiftQuote['family_processing_fee_cents'] / 100) : 0;
        $platformFeePercent = $usesPricingV2 ? 0 : ($booking
            ? $pricing->platformFeePercentForBooking($booking, max(0, (float) config('marketplace.payments.platform_fee_percent', 0)))
            : $pricing->platformFeePercentForFamily($requestItem->family, max(0, (float) config('marketplace.payments.platform_fee_percent', 0))));
        $estimatedPaymentTotal = $shiftQuote
            ? ((int) $shiftQuote['total_charge_cents'] / 100)
            : ($shiftEarnings > 0 ? round($shiftEarnings * (1 + ($platformFeePercent / 100)), 2) : 0);
        $canWithdrawRequest = in_array($requestItem->status, [
            \App\Models\CareRequest::STATUS_DRAFT,
            \App\Models\CareRequest::STATUS_OPEN,
        ], true) && ! $booking;
        $openCaregiverResponses = $requestItem->applications
            ->whereIn('status', [
                \App\Models\CareRequestApplication::STATUS_APPLIED,
                \App\Models\CareRequestApplication::STATUS_SHORTLISTED,
            ])
            ->count();
        $requestDatePassed = \App\Support\CareRequestProgress::oneTimeDateHasPassed($requestItem);
        $topCaregiverActionLabel = $openCaregiverResponses > 0 ? 'Review caregivers' : 'Invite caregivers';
        $canCancelScheduledShift = $booking && $booking->status === \App\Models\CareBooking::STATUS_SCHEDULED;
        $canRequestVisitChange = $booking && $booking->status === \App\Models\CareBooking::STATUS_SCHEDULED;
        $canDisputeVisit = $booking && in_array($booking->status, [
            \App\Models\CareBooking::STATUS_COMPLETED,
            \App\Models\CareBooking::STATUS_REVIEWED,
        ], true);
        $supportScreenEyebrow = match ((string) ($booking?->status ?? '')) {
            \App\Models\CareBooking::STATUS_SCHEDULED => 'Before the visit',
            \App\Models\CareBooking::STATUS_IN_PROGRESS,
            \App\Models\CareBooking::STATUS_PAUSED => 'Live help',
            \App\Models\CareBooking::STATUS_COMPLETED => 'Timesheet help',
            \App\Models\CareBooking::STATUS_REVIEWED => $timesheetNeedsReview ? 'Timesheet help' : 'Completed visit help',
            \App\Models\CareBooking::STATUS_CANCELLED => 'Cancelled visit help',
            \App\Models\CareBooking::STATUS_DISPUTED => 'Support review',
            default => 'Help',
        };
        $supportScreenTitle = match ((string) ($booking?->status ?? '')) {
            \App\Models\CareBooking::STATUS_SCHEDULED => 'Change or cancel this visit',
            \App\Models\CareBooking::STATUS_IN_PROGRESS,
            \App\Models\CareBooking::STATUS_PAUSED => 'Need help during this visit?',
            \App\Models\CareBooking::STATUS_COMPLETED => $timesheetNeedsReview ? 'Question hours or payment' : 'Get help with this completed visit',
            \App\Models\CareBooking::STATUS_REVIEWED => $timesheetNeedsReview ? 'Question hours or payment' : 'Get help with this completed visit',
            \App\Models\CareBooking::STATUS_CANCELLED => 'Get help with this cancelled visit',
            \App\Models\CareBooking::STATUS_DISPUTED => 'Support is reviewing this visit',
            default => 'Safety and support',
        };
        $supportScreenBody = match ((string) ($booking?->status ?? '')) {
            \App\Models\CareBooking::STATUS_SCHEDULED => 'Use this before caregiver check-in if the time no longer works or you need to cancel.',
            \App\Models\CareBooking::STATUS_IN_PROGRESS,
            \App\Models\CareBooking::STATUS_PAUSED => 'For ordinary questions, message the caregiver. If something feels wrong, report it here.',
            \App\Models\CareBooking::STATUS_COMPLETED => $timesheetNeedsReview
                ? 'If the submitted hours do not look right, ask for help before approving payment.'
                : 'Check the actual payment status on this visit. You can open a support ticket or dispute if something is wrong.',
            \App\Models\CareBooking::STATUS_REVIEWED => $timesheetNeedsReview
                ? 'If the submitted hours do not look right, ask for help before approving payment.'
                : 'Get help with billing, safety or record corrections for this visit.',
            \App\Models\CareBooking::STATUS_CANCELLED => 'This visit is closed. Support can help with cancellation questions or billing follow-up.',
            \App\Models\CareBooking::STATUS_DISPUTED => 'A dispute is already open. Add a support ticket only if you need to share more context.',
            default => 'Choose the option that best matches what you need.',
        };
        $supportButtonLabel = match ((string) ($booking?->status ?? '')) {
            \App\Models\CareBooking::STATUS_SCHEDULED => 'Change or cancel',
            \App\Models\CareBooking::STATUS_COMPLETED => $timesheetNeedsReview ? 'Question hours' : 'Get support',
            \App\Models\CareBooking::STATUS_REVIEWED => $timesheetNeedsReview ? 'Question hours' : 'Get support',
            default => 'Safety/support',
        };
        $lateCancel = $booking?->scheduled_start_at
            ? now()->diffInMinutes($booking->scheduled_start_at, false) <= 24 * 60
            : false;
        $shiftStatusTone = match ((string) ($booking?->status ?? '')) {
            \App\Models\CareBooking::STATUS_SCHEDULED => 'border-[#BDD4F7] bg-[#EEF5FF] text-[#28486F]',
            \App\Models\CareBooking::STATUS_IN_PROGRESS => 'border-emerald-200 bg-emerald-50 text-emerald-900',
            \App\Models\CareBooking::STATUS_PAUSED => 'border-amber-200 bg-amber-50 text-amber-900',
            \App\Models\CareBooking::STATUS_COMPLETED,
            \App\Models\CareBooking::STATUS_REVIEWED => 'border-indigo-200 bg-indigo-50 text-indigo-900',
            \App\Models\CareBooking::STATUS_CANCELLED,
            \App\Models\CareBooking::STATUS_DISPUTED => 'border-rose-200 bg-rose-50 text-rose-900',
            default => 'border-[#E4DDD3] bg-[#F7F2EA] text-[#485E53]',
        };
        $shiftStatusTitle = match ((string) ($booking?->status ?? '')) {
            \App\Models\CareBooking::STATUS_SCHEDULED => 'Visit is scheduled',
            \App\Models\CareBooking::STATUS_IN_PROGRESS => 'Caregiver is checked in',
            \App\Models\CareBooking::STATUS_PAUSED => 'Visit is paused',
            \App\Models\CareBooking::STATUS_COMPLETED => 'Timesheet needs review',
            \App\Models\CareBooking::STATUS_REVIEWED => $timesheetNeedsReview ? 'Timesheet needs review' : 'Visit is closed',
            \App\Models\CareBooking::STATUS_CANCELLED => 'Visit was cancelled',
            \App\Models\CareBooking::STATUS_DISPUTED => 'Visit is in dispute',
            default => 'Visit status',
        };
        $shiftStatusBody = match ((string) ($booking?->status ?? '')) {
            \App\Models\CareBooking::STATUS_SCHEDULED => 'Waiting for caregiver check-in. You can cancel before check-in, or mark no-show 30 minutes after scheduled start.',
            \App\Models\CareBooking::STATUS_IN_PROGRESS => 'The caregiver has started the visit. Watch check-in details, message them, or mark complete when care is finished.',
            \App\Models\CareBooking::STATUS_PAUSED => 'The caregiver paused the visit. They can resume or end from their visit screen.',
            \App\Models\CareBooking::STATUS_COMPLETED => 'The caregiver submitted the timesheet. Review worked time before confirming.',
            \App\Models\CareBooking::STATUS_REVIEWED => $timesheetNeedsReview
                ? 'The caregiver submitted the timesheet. Review worked time before confirming.'
                : 'Review the recorded hours, payment status and feedback below.',
            \App\Models\CareBooking::STATUS_CANCELLED => $booking?->no_show_flag ? 'This visit was closed as caregiver no-show.' : 'This visit was cancelled.',
            \App\Models\CareBooking::STATUS_DISPUTED => 'Support is reviewing this visit.',
            default => 'Visit details are available after hiring.',
        };
        $shiftBadgeColor = match ((string) ($booking?->status ?? '')) {
            \App\Models\CareBooking::STATUS_IN_PROGRESS => 'green',
            \App\Models\CareBooking::STATUS_PAUSED => 'amber',
            \App\Models\CareBooking::STATUS_COMPLETED,
            \App\Models\CareBooking::STATUS_REVIEWED => 'indigo',
            \App\Models\CareBooking::STATUS_CANCELLED,
            \App\Models\CareBooking::STATUS_DISPUTED => 'red',
            default => 'blue',
        };
        $visitStatusLabel = $booking
            ? ucfirst(str_replace('_', ' ', (string) $booking->status))
            : 'No visit';
        $plainRequestType = ! ($booking?->care_plan_id || $requestItem->care_plan_id) && $requestItem->request_type === \App\Models\CareRequest::TYPE_ONE_TIME
            ? 'One-time care'
            : 'Recurring care';
        $plainSchedule = $booking
            ? trim((optional($booking->scheduled_start_at)->format('M d, g:i A') ?: 'Time pending').' - '.(optional($booking->scheduled_end_at)->format('g:i A') ?: ''))
            : ($requestItem->request_type === \App\Models\CareRequest::TYPE_ONE_TIME
                ? (optional($requestItem->requested_start_at)->format('M d, g:i A') ?: 'Time not set')
                : ($requestItem->recurringScheduleLabel() ?: 'Weekly schedule'));
        $recruitmentSchedule = ! $booking && $requestItem->request_type === \App\Models\CareRequest::TYPE_ONE_TIME && $requestItem->requested_start_at
            ? $requestItem->requested_start_at->format('M d, g:i A')
                .($requestItem->requested_end_at ? '–'.$requestItem->requested_end_at->format($requestItem->requested_end_at->isSameDay($requestItem->requested_start_at) ? 'g:i A' : 'D, M j, g:i A') : '')
                .' · '.$requestItem->requested_start_at->format('Y T')
            : $plainSchedule;
        $recordRecipientName = trim((string) ($requestItem->recipient?->full_name ?? '')) ?: 'Care recipient';
        $recordHeadline = $booking
            ? $recordRecipientName.' · '.($booking->scheduled_start_at?->format('D, M j') ?: 'Visit')
            : ($requestItem->request_type === \App\Models\CareRequest::TYPE_RECURRING
                ? 'Recurring care for '.$recordRecipientName
                : $recordRecipientName.' · '.($requestItem->requested_start_at?->format('D, M j') ?: 'Date not set'));
        $visitCaregiverDisplayName = trim((string) $hiredCaregiverFirstName) !== ''
            ? trim((string) $hiredCaregiverFirstName)
            : 'Your caregiver';
        $visitStartLabel = $booking?->scheduled_start_at?->format('M d, g:i A');
        $visitEndLabel = $booking?->scheduled_end_at?->format('g:i A');
        $visitTimeRangeLabel = trim(($visitStartLabel ?: 'the scheduled time').($visitEndLabel ? ' - '.$visitEndLabel : ''));
        $visitStageSummary = match ((string) ($booking?->status ?? '')) {
            \App\Models\CareBooking::STATUS_SCHEDULED => $visitCaregiverDisplayName.' is coming on '.$visitTimeRangeLabel.'.',
            \App\Models\CareBooking::STATUS_IN_PROGRESS => $visitCaregiverDisplayName.' checked in at '.($booking?->started_at?->format('g:i A') ?: 'the visit start').'.',
            \App\Models\CareBooking::STATUS_PAUSED => $visitCaregiverDisplayName.' paused the visit. They can resume or end it from their visit screen.',
            default => null,
        };
        $canRebookHiredCaregiver = ! $timesheetNeedsReview
            && ! $requestItem->care_plan_id
            && $booking
            && $hiredApplication
            && ! in_array($booking->status, [\App\Models\CareBooking::STATUS_CANCELLED, \App\Models\CareBooking::STATUS_DISPUTED], true)
            && ($booking->family_confirmed_at || $booking->status === \App\Models\CareBooking::STATUS_REVIEWED);
        $visitPanelEyebrow = match ((string) ($booking?->status ?? '')) {
            \App\Models\CareBooking::STATUS_COMPLETED,
            \App\Models\CareBooking::STATUS_REVIEWED => $timesheetNeedsReview ? 'Visit details' : 'Visit record',
            \App\Models\CareBooking::STATUS_IN_PROGRESS,
            \App\Models\CareBooking::STATUS_PAUSED => 'Live details',
            \App\Models\CareBooking::STATUS_CANCELLED,
            \App\Models\CareBooking::STATUS_DISPUTED => 'Visit record',
            default => 'Visit essentials',
        };
        $visitPanelTitle = match ((string) ($booking?->status ?? '')) {
            \App\Models\CareBooking::STATUS_SCHEDULED => 'Before the visit',
            \App\Models\CareBooking::STATUS_COMPLETED,
            \App\Models\CareBooking::STATUS_REVIEWED => $timesheetNeedsReview ? 'Visit details before approval' : 'Final details',
            \App\Models\CareBooking::STATUS_IN_PROGRESS => 'Live visit details',
            \App\Models\CareBooking::STATUS_PAUSED => 'Paused visit details',
            \App\Models\CareBooking::STATUS_CANCELLED => $booking?->no_show_flag ? 'No-show details' : 'Cancelled visit details',
            \App\Models\CareBooking::STATUS_DISPUTED => 'Support review details',
            default => 'Visit essentials',
        };
        $visitPanelBody = match ((string) ($booking?->status ?? '')) {
            \App\Models\CareBooking::STATUS_SCHEDULED => 'Keep the caregiver, time, location, and late-arrival guidance in one simple place.',
            \App\Models\CareBooking::STATUS_COMPLETED => $timesheetNeedsReview
                ? 'Confirm schedule, caregiver, and location here. The hours and payment decision stay above.'
                : 'Keep the final schedule, time, payment, notes, and feedback in one record.',
            \App\Models\CareBooking::STATUS_REVIEWED => $timesheetNeedsReview
                ? 'Confirm schedule, caregiver, and location here. The hours and payment decision stay above.'
                : 'Keep the final schedule, time, payment, notes, and feedback in one record.',
            \App\Models\CareBooking::STATUS_IN_PROGRESS => 'Schedule, check-in, location, and payment stay here while live actions stay above.',
            \App\Models\CareBooking::STATUS_PAUSED => 'Schedule, check-in, location, and payment stay here while pause details stay above.',
            \App\Models\CareBooking::STATUS_CANCELLED => 'This visit is closed. Review the cancellation record or get care again.',
            \App\Models\CareBooking::STATUS_DISPUTED => 'Support is reviewing the visit. Keep details and messages together here.',
            default => 'Confirm time, caregiver, location, payment status, and support options before care starts.',
        };
        $isLiveVisit = $booking && in_array($booking->status, [
            \App\Models\CareBooking::STATUS_IN_PROGRESS,
            \App\Models\CareBooking::STATUS_PAUSED,
        ], true);
        $isFinalVisitRecord = $booking
            && in_array($booking->status, [\App\Models\CareBooking::STATUS_COMPLETED, \App\Models\CareBooking::STATUS_REVIEWED], true)
            && (bool) $booking->family_confirmed_at
            && ! $hasActiveTimeCorrection;
        $showVisitMapEmbed = $serviceMapEmbedUrl && ! $isFinalVisitRecord;
        $isScheduledVisit = ($lifecycleStage['key'] ?? '') === 'visit_scheduled';
        $showVisitActionStrip = (! $isLiveVisit || $showPaymentAuthorizationAction) && ! $isFinalVisitRecord;
        $showVisitStatusNotice = $booking && in_array($booking->status, [
            \App\Models\CareBooking::STATUS_PAUSED,
            \App\Models\CareBooking::STATUS_CANCELLED,
            \App\Models\CareBooking::STATUS_DISPUTED,
        ], true);
        $stageTabs = collect($lifecycleStage['tabs'] ?? []);
        $stageTabGridClass = $stageTabs->count() <= 4
            ? 'grid-cols-2 xl:grid-cols-4'
            : 'grid-cols-1 sm:grid-cols-2 xl:grid-cols-4';
        $isWaitingForCaregivers = ($lifecycleStage['key'] ?? '') === 'waiting_for_caregivers';
        $isReviewingCaregivers = ($lifecycleStage['key'] ?? '') === 'reviewing_caregivers';
        $caregiverScreenTitle = match (true) {
            $isWaitingForCaregivers => 'Suggested caregivers',
            $isReviewingCaregivers => 'Choose a caregiver',
            (bool) $hiredApplication => 'Selected caregiver',
            default => 'Caregivers',
        };
        $caregiverScreenBody = match (true) {
            $isWaitingForCaregivers => 'No one has replied yet. Invite one or two trusted matches while the request stays open.',
            $isReviewingCaregivers => 'Review the people who replied. You can chat first, view profiles, or hire when ready.',
            (bool) $hiredApplication => ($hiredCaregiverName ?: 'The caregiver').' is selected. Keep profile and chat history here.',
            default => 'Caregiver replies and past conversations stay here.',
        };
        $activeCaregiverResponses = $requestItem->applications
            ->whereIn('status', [
                \App\Models\CareRequestApplication::STATUS_APPLIED,
                \App\Models\CareRequestApplication::STATUS_SHORTLISTED,
            ])
            ->count();
        $visibleApplications = $this->visibleApplications;
        $visibleApplicationCount = $visibleApplications->count();
        $showCaregiverFilterControls = $requestItem->applications->isNotEmpty() || $applicationStatus !== 'all' || $applicationCertificationCriteria->hasSelections();
        $featuredCaregiverApplication = $isReviewingCaregivers ? $visibleApplications->first() : null;
        $showFeaturedCaregiverDecision = false;
        $showApplicationList = ! $showFeaturedCaregiverDecision;
        $featuredCaregiverProfile = $featuredCaregiverApplication?->caregiver?->caregiverProfile;
        $featuredCaregiverProfileHref = $featuredCaregiverProfile?->slug
            ? route('caregivers.show', ['slug' => $featuredCaregiverProfile->slug, 'careRequest' => $requestItem->id])
            : null;
        $featuredCaregiverFirstName = $featuredCaregiverApplication
            ? \Illuminate\Support\Str::of($featuredCaregiverApplication->caregiver->name)->before(' ')->trim()
            : null;
        if ($featuredCaregiverFirstName?->isEmpty()) {
            $featuredCaregiverFirstName = 'caregiver';
        }
        $featuredCaregiverStatusLabel = match ((string) ($featuredCaregiverApplication?->status ?? '')) {
            \App\Models\CareRequestApplication::STATUS_APPLIED => 'Interested',
            \App\Models\CareRequestApplication::STATUS_SHORTLISTED => 'Saved',
            default => ucfirst(str_replace('_', ' ', (string) ($featuredCaregiverApplication?->status ?? ''))),
        };
        $mobilePrimaryAction = match (true) {
            $showPaymentAuthorizationAction => ['type' => 'payment', 'label' => $paymentActionButtonLabel],
            $requestDatePassed => ['type' => 'new_date', 'label' => 'Choose another date'],
            $timesheetNeedsReview => ['type' => 'approve_hours', 'label' => 'Review hours & payment'],
            $isLiveVisit => ['type' => 'complete_visit', 'label' => 'The visit has ended'],
            $isScheduledVisit => ['type' => 'open_visit', 'label' => 'Open visit details'],
            $isWaitingForCaregivers => ['type' => 'find_caregivers', 'label' => 'Find matching caregivers'],
            $requestItem->status === \App\Models\CareRequest::STATUS_OPEN => ['type' => 'review_caregivers', 'label' => 'Review caregivers'],
            default => null,
        };
    @endphp

    @if (! $booking)
        @include('livewire.family.partials.recruitment-header')
    @endif
    @include('livewire.family.partials.care-workspace-header')

    @include('livewire.family.partials.care-decision-review')

    @if ($showPaymentAuthorizationAction)
        <x-alert color="amber">
            <div data-ai-target="family.request.payment_attention" tabindex="-1" class="flex flex-col gap-3 outline-none sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-semibold">{{ $paymentTimeCorrection ? 'Finish payment for the approved hours.' : 'Card authorization needs attention.' }}</p>
                    @if ($paymentTimeCorrection)
                        <p class="text-sm">
                            Your approval is saved. Confirm
                            {{ $paymentTimeCorrection->durationLabel() }}
                            @if ($paymentActionAmountLabel) for {{ $paymentActionAmountLabel }} @endif
                            to finish this visit.
                        </p>
                    @else
                        <p class="text-sm">{{ $payment->last_error ?: 'Confirm the card authorization before the visit is financially protected.' }}</p>
                    @endif
                </div>
                <x-button
                    color="amber"
                    wire:click="startPaymentAuthorization"
                    wire:loading.attr="disabled"
                    wire:target="startPaymentAuthorization"
                    x-bind:disabled="confirmingPayment"
                    x-bind:aria-busy="confirmingPayment"
                    class="w-full disabled:cursor-wait disabled:opacity-60 sm:w-auto"
                >
                    <span x-text="confirmingPayment ? 'Opening secure confirmation…' : @js($paymentActionButtonLabel)"></span>
                </x-button>
            </div>
        </x-alert>
    @endif

    @if ($activeTab === 'overview')
        <div data-ai-target="family.request.overview" tabindex="-1" class="space-y-5">
        @if ($careProfileSnapshot)
            <x-care-recipient-profile-summary :snapshot="$careProfileSnapshot" />
        @endif
        <x-card>
            <x-slot:header>
                <h2 class="font-display text-lg font-semibold">Request context</h2>
            </x-slot:header>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3 text-sm">
                <div class="space-y-3 md:col-span-2">
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-[#485E53] uppercase">Scope of work</p>
                        <p class="mt-1 text-[#324457]">{{ $requestItem->scope_of_work ?: '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-[#485E53] uppercase">Time expectations</p>
                        <p class="mt-1 text-[#324457]">{{ $requestItem->time_expectations ?: '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-[#485E53] uppercase">Home access</p>
                        <p class="mt-1 text-[#324457]">{{ $requestItem->home_access_notes ?: '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-[#485E53] uppercase">Address</p>
                        <p class="mt-1 text-[#324457]">
                            {{ $requestItem->address_line1 }}{{ $requestItem->address_line2 ? ', '.$requestItem->address_line2 : '' }},
                            {{ $requestItem->city }}, {{ $requestItem->state }} {{ $requestItem->zip }}
                        </p>
                        @if ($serviceMapEmbedUrl)
                            <div wire:ignore class="mt-3 overflow-hidden rounded-xl border border-[#E4DDD3] bg-[#F7F2EA]">
                                <iframe
                                    title="Service location map"
                                    src="{{ $serviceMapEmbedUrl }}"
                                    loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade"
                                    class="h-44 w-full"
                                ></iframe>
                            </div>
                            @if ($serviceMapOpenUrl)
                                <a href="{{ $serviceMapOpenUrl }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block text-xs font-medium text-[#7C5DDC] underline underline-offset-2">
                                    Open full map
                                </a>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="rounded-lg border border-[#E4DDD3] bg-[#F7F2EA] p-3">
                        <p class="text-xs font-semibold tracking-wide text-[#485E53] uppercase">Recipient</p>
                        <p class="mt-1 font-medium text-[#23483F]">{{ $requestItem->recipient?->full_name ?: '-' }}</p>
                        <p class="text-[#485E53]">{{ $requestItem->recipient?->relationship_to_family ?: '-' }}</p>
                    </div>

                    @if ($requestItem->thirdPartyContact)
                        <div class="rounded-lg border border-[#E4DDD3] bg-[#F7F2EA] p-3">
                            <p class="text-xs font-semibold tracking-wide text-[#485E53] uppercase">Third-party contact</p>
                            <p class="mt-1 font-medium text-[#23483F]">{{ $requestItem->thirdPartyContact->full_name }}</p>
                            <p class="text-[#485E53]">{{ $requestItem->thirdPartyContact->phone ?: '-' }}</p>
                            <p class="text-[#485E53]">{{ $requestItem->thirdPartyContact->email ?: '-' }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </x-card>

        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-lg font-semibold">Task list</h2>
                    <p class="text-sm text-[#485E53]">{{ $requestItem->tasks->count() }} task(s)</p>
                </div>
            </x-slot:header>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                @forelse ($requestItem->tasks as $task)
                    <div class="rounded-lg border border-[#E4DDD3] p-3">
                        <p class="font-display font-semibold text-[#23483F]">{{ $task->name }}</p>
                        <p class="mt-1 text-sm text-[#485E53]">{{ $task->pivot?->task_note ?: 'No additional notes.' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-[#485E53]">No tasks attached to this request.</p>
                @endforelse
            </div>
        </x-card>

        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-lg font-semibold">Selected caregiver</h2>
                    <p class="text-sm text-[#485E53]">{{ $requestItem->invitations->count() }} invite(s) sent</p>
                </div>
            </x-slot:header>

            @if ($hiredApplication)
                @php
                    $selectedCaregiverProfile = $hiredApplication->caregiver->caregiverProfile;
                    $selectedCaregiverProfileHref = $selectedCaregiverProfile?->slug
                        ? route('caregivers.show', ['slug' => $selectedCaregiverProfile->slug, 'careRequest' => $requestItem->id])
                        : null;
                    $selectedCaregiverRate = $pricing->hourlyRateForFamily(
                        $requestItem->family,
                        (float) ($hiredApplication->proposed_rate ?: $selectedCaregiverProfile?->resolvePlatformHourlyRate() ?: config('marketplace.family_estimate_hourly_rate', 30.00))
                    );
                @endphp
                <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="font-display text-lg font-semibold text-[#23483F]">{{ $hiredApplication->caregiver->name }}</p>
                            <p class="text-sm text-[#485E53]">
                                Care rate: ${{ number_format($selectedCaregiverRate, 2) }}/hr* · processing fee ${{ number_format($pricing->familyProcessingFeeHourlyCents() / 100, 2) }}/hr
                            </p>
                            @if ($selectedCaregiverProfile)
                                <p class="mt-1 text-sm text-[#485E53]">
                                    {{ (int) ($selectedCaregiverProfile->years_experience ?? 0) }} year{{ (int) ($selectedCaregiverProfile->years_experience ?? 0) === 1 ? '' : 's' }} experience
                                    @if ($selectedCaregiverProfile->average_rating && $selectedCaregiverProfile->reviews_count > 0)
                                        - {{ number_format((float) $selectedCaregiverProfile->average_rating, 1) }} stars from {{ (int) $selectedCaregiverProfile->reviews_count }} review{{ (int) $selectedCaregiverProfile->reviews_count === 1 ? '' : 's' }}
                                    @endif
                                </p>
                            @endif
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            @if ($selectedCaregiverProfileHref)
                                <a href="{{ $selectedCaregiverProfileHref }}" wire:navigate>
                                    <x-button color="blue" light class="hc-action-secondary">View profile</x-button>
                                </a>
                            @endif
                            @if ($hiredConversation)
                                <a href="{{ route('messages.show', $hiredConversation->id) }}" wire:navigate>
                                    <x-button color="indigo" light class="hc-action-secondary">Open chat</x-button>
                                </a>
                            @endif
                            @if ($booking)
                                <x-button color="blue" light wire:click="setActiveTab('shift')" class="hc-action-secondary">Go to visit</x-button>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-lg border border-dashed border-[#D6CCBE] px-4 py-5 text-sm text-[#485E53]">
                    No caregiver hired yet. Open the Caregivers tab to save, message, or hire someone.
                </div>
            @endif
        </x-card>
        </div>
    @endif

    @if ($activeTab === 'invite')
        @include('livewire.family.partials.recruitment-discovery')
    @endif

    @if ($activeTab === 'applicants' && ! $reviewingApplicationId)
        @include('livewire.family.partials.recruitment-applicants')
    @endif

    @if ($activeTab === 'shift')
        @if (! $booking)
            <x-card>
                <x-slot:header><h2 class="font-display text-lg font-semibold">Visit details</h2></x-slot:header>
                <div class="rounded-md border border-dashed border-[#D6CCBE] px-4 py-6 text-sm text-[#485E53]">
                    Visit details become available once you hire a caregiver.
                </div>
            </x-card>
        @else
            <section id="visit-section" data-ai-target="family.request.visit" tabindex="-1" class="space-y-5 rounded-3xl border border-[#D8E1D7] bg-white p-4 shadow-sm outline-none sm:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="hc-brand-kicker">{{ $visitPanelEyebrow }}</p>
                        <h2 class="mt-1 font-display text-2xl font-semibold text-[#23483F]">
                            {{ $visitPanelTitle }}
                        </h2>
                        <p class="mt-1 max-w-3xl text-sm text-[#485E53]">
                            {{ $visitPanelBody }}
                        </p>
                    </div>
                    <x-badge :text="$visitStatusLabel" :color="$shiftBadgeColor" />
                </div>

                @if ($showVisitStatusNotice)
                    <div class="rounded-2xl border px-4 py-3 {{ $shiftStatusTone }}">
                        <p class="font-display text-lg font-semibold">{{ $shiftStatusTitle }}</p>
                        <p class="mt-1 text-sm">{{ $shiftStatusBody }}</p>
                    </div>
                @endif

                @include('livewire.family.partials.time-correction-review', [
                    'booking' => $booking,
                    'correction' => $latestTimeCorrection,
                    'caregiverFirstName' => (string) $hiredCaregiverFirstName,
                    'paymentActionShown' => $showPaymentAuthorizationAction,
                ])

                @if ($isScheduledVisit || $isLiveVisit)
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(280px,0.45fr)]">
                        <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-4">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#485E53]">
                                {{ $isLiveVisit ? 'Live check-in' : 'Visit plan' }}
                            </p>
                            <h3 class="mt-1 font-display text-xl font-semibold text-[#23483F]">
                                @if ($isLiveVisit)
                                    {{ $hiredCaregiverFirstName }} is with {{ $requestItem->recipient?->full_name ?: 'the care recipient' }}.
                                @else
                                    {{ $hiredCaregiverFirstName }} is scheduled to come.
                                @endif
                            </h3>
                            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div class="rounded-xl border border-[#E4DDD3] bg-white px-3 py-2">
                                    <p class="text-xs uppercase tracking-[0.12em] text-[#485E53]">Time</p>
                                    <p class="mt-1 font-semibold text-[#23483F]">{{ optional($booking->scheduled_start_at)->format('M d') ?: 'Date pending' }}</p>
                                    <p class="text-sm text-[#485E53]">{{ optional($booking->scheduled_start_at)->format('g:i A') ?: '-' }} - {{ optional($booking->scheduled_end_at)->format('g:i A') ?: '-' }}</p>
                                </div>
                                <div class="rounded-xl border border-[#E4DDD3] bg-white px-3 py-2">
                                    <p class="text-xs uppercase tracking-[0.12em] text-[#485E53]">Caregiver</p>
                                    <p class="mt-1 font-semibold text-[#23483F]">{{ $hiredCaregiverName ?: 'Selected caregiver' }}</p>
                                    <p class="text-sm text-[#485E53]">
                                        {{ $booking->started_at ? 'Checked in '.$booking->started_at->format('g:i A') : 'Check-in pending' }}
                                    </p>
                                </div>
                                <div class="rounded-xl border border-[#E4DDD3] bg-white px-3 py-2">
                                    <p class="text-xs uppercase tracking-[0.12em] text-[#485E53]">Payment</p>
                                    <p class="mt-1 font-semibold text-[#23483F]">{{ $payment ? ucfirst(str_replace('_', ' ', (string) $payment->status)) : 'Needs setup' }}</p>
                                    <p class="text-sm text-[#485E53]">
                                        @if ($payment?->amount_authorized_cents)
                                            Authorized ${{ number_format($payment->amount_authorized_cents / 100, 2) }}
                                        @else
                                            Card authorization pending
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 rounded-xl border border-[#E4DDD3] bg-white px-3 py-3">
                                <p class="text-xs uppercase tracking-[0.12em] text-[#485E53]">Care location</p>
                                <p class="mt-1 font-semibold text-[#23483F]">{{ $serviceAddress ?: 'Address not set' }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-[#485E53]">
                                    @if ($serviceMapOpenUrl)
                                        <a href="{{ $serviceMapOpenUrl }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-[#7C5DDC] underline underline-offset-2">Open map</a>
                                    @endif
                                    <span>{{ $requestItem->recipient?->full_name ?: 'Recipient' }} - {{ $requestItem->recipient?->relationship_to_family ?: 'Care recipient' }}</span>
                                </div>
                            </div>
                        </div>

                        <aside class="space-y-3">
                            @if ($isLiveVisit)
                                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950">
                                    <p class="font-display text-lg font-semibold">Checked in</p>
                                    <p class="mt-1">{{ $hiredCaregiverFirstName }} checked in {{ optional($booking->started_at)->format('M d, g:i A') ?: 'at the visit start' }}.</p>
                                    @if ($booking->check_in_lat && $booking->check_in_lng)
                                        <p class="mt-2 text-xs text-emerald-800">GPS was captured at check-in.</p>
                                    @endif
                                </div>
                            @elseif (! $canMarkNoShow)
                                <details class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] px-4 py-3 text-sm text-[#485E53]">
                                    <summary class="cursor-pointer list-none font-semibold text-[#23483F] [&::-webkit-details-marker]:hidden">
                                        If the caregiver is late
                                    </summary>
                                    <div class="mt-2 space-y-2">
                                        @if ($noShowEligibleAt)
                                            <p>No-show becomes available at {{ $noShowEligibleAt->format('M d, g:i A') }}. Until then, message the caregiver or contact support.</p>
                                        @else
                                            <p>No-show needs a scheduled start time. Contact support if the caregiver has not arrived.</p>
                                        @endif
                                    </div>
                                </details>
                            @endif

                            @if ($canMarkNoShow)
                                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-950">
                                    <p class="font-display text-lg font-semibold">Caregiver has not arrived?</p>
                                    <p class="mt-1">The no-show window is open for this visit.</p>
                                    <x-button color="red" light wire:click="markNoShow" onclick="if (!confirm('Mark this caregiver as no-show? This cancels the visit and affects reliability.')) return false;" class="mt-3 w-full">Mark caregiver no-show</x-button>
                                </div>
                            @endif

                            <details class="rounded-2xl border border-[#E4DDD3] bg-white px-4 py-3 text-sm text-[#485E53]">
                                <summary class="cursor-pointer list-none font-semibold text-[#23483F] [&::-webkit-details-marker]:hidden">
                                    Map and visit record
                                </summary>
                                <div class="mt-3 space-y-3 border-t border-[#EFE6D8] pt-3">
                                    @if ($showVisitMapEmbed)
                                        <div wire:ignore class="overflow-hidden rounded-xl border border-[#E4DDD3] bg-[#F7F2EA]">
                                            <iframe
                                                title="Visit service location map"
                                                src="{{ $serviceMapEmbedUrl }}"
                                                loading="lazy"
                                                referrerpolicy="no-referrer-when-downgrade"
                                                class="h-44 w-full"
                                            ></iframe>
                                        </div>
                                    @endif
                                    <p>Started: {{ optional($booking->started_at)->format('M d, H:i') ?: 'Pending' }}</p>
                                    <p>Completed: {{ optional($booking->completed_at)->format('M d, H:i') ?: 'Pending' }}</p>
                                    <p>Payment: {{ $payment ? strtoupper((string) $payment->status) : 'NOT READY' }}</p>
                                </div>
                            </details>
                        </aside>
                    </div>

                    @php
                        $pendingCaregiverChanges = $booking->changeRequests->filter(
                            fn ($change) => $change->status === \App\Models\CareBookingChangeRequest::STATUS_PENDING
                                && $change->requester?->role === 'caregiver'
                        );
                    @endphp
                    @if ($pendingCaregiverChanges->isNotEmpty())
                        <section
                            data-ai-target="family.request.visit_issue"
                            tabindex="-1"
                            class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950 outline-none"
                            aria-labelledby="pending-caregiver-change-heading"
                        >
                            <h3 id="pending-caregiver-change-heading" class="font-display text-lg font-semibold">Caregiver requested a visit change</h3>
                            <p class="mt-1">Your current visit schedule stays in place until you accept a change.</p>
                            <div class="mt-3 space-y-3">
                                @foreach ($pendingCaregiverChanges as $change)
                                    <div class="rounded-xl border border-amber-200 bg-white px-3 py-3">
                                        <p class="font-semibold">{{ ucfirst(str_replace('_', ' ', (string) $change->type)) }}</p>
                                        <p class="mt-1 text-amber-900">{{ $change->reason }}</p>
                                        @if ($change->proposed_start_at)
                                            <p class="mt-1 text-xs text-amber-800">
                                                Proposed: {{ $change->proposed_start_at->format('M d, Y g:i A') }}
                                                @if ($change->proposed_end_at)
                                                    to {{ $change->proposed_end_at->format('g:i A') }}
                                                @endif
                                            </p>
                                        @endif
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <x-button color="green" light wire:click="resolveChangeRequest({{ $change->id }}, 'accept')" class="hc-action-secondary">Accept change</x-button>
                                            <x-button color="red" light wire:click="resolveChangeRequest({{ $change->id }}, 'reject')">Keep current schedule</x-button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @else
                <div class="grid grid-cols-1 gap-3 xl:grid-cols-4">
                    <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-4">
                        <p class="text-xs uppercase tracking-[0.12em] text-[#485E53]">Schedule</p>
                        <p class="mt-2 font-semibold text-[#23483F]">{{ optional($booking->scheduled_start_at)->format('M d, Y') ?: 'Pending' }}</p>
                        <p class="text-sm text-[#485E53]">{{ optional($booking->scheduled_start_at)->format('g:i A') ?: '-' }} - {{ optional($booking->scheduled_end_at)->format('g:i A') ?: '-' }}</p>
                    </div>

                    <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-4">
                        <p class="text-xs uppercase tracking-[0.12em] text-[#485E53]">Caregiver</p>
                        <p class="mt-2 font-semibold text-[#23483F]">{{ $hiredCaregiverName ?: 'Selected caregiver' }}</p>
                        <p class="text-sm text-[#485E53]">{{ $booking->started_at ? 'Checked in '.$booking->started_at->format('M d, g:i A') : 'Check-in pending' }}</p>
                    </div>

                    <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-4 xl:col-span-2">
                        <p class="text-xs uppercase tracking-[0.12em] text-[#485E53]">Care location</p>
                        <p class="mt-2 font-semibold text-[#23483F]">{{ $serviceAddress ?: 'Address not set' }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @if ($serviceMapOpenUrl)
                                <a href="{{ $serviceMapOpenUrl }}" target="_blank" rel="noopener noreferrer" class="text-sm font-medium text-[#7C5DDC] underline underline-offset-2">Open map</a>
                            @endif
                            <span class="text-sm text-[#485E53]">{{ $requestItem->recipient?->full_name ?: 'Recipient' }} - {{ $requestItem->recipient?->relationship_to_family ?: 'Care recipient' }}</span>
                        </div>
                    </div>
                </div>

                @if ($showVisitMapEmbed)
                    <details class="rounded-2xl border border-[#E4DDD3] bg-[#FFFDFA] p-4">
                    <summary class="text-sm font-semibold">Location map</summary>
                    <div wire:ignore class="mt-3 overflow-hidden rounded-2xl border border-[#E4DDD3] bg-[#F7F2EA]">
                        <iframe
                            title="Visit service location map"
                            src="{{ $serviceMapEmbedUrl }}"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            class="h-56 w-full"
                        ></iframe>
                    </div>
                    </details>
                @endif

                @if (! $timesheetNeedsReview && ! $isFinalVisitRecord)
                    @if (! $payment)
                        <div data-ai-target="family.request.payment_attention" tabindex="-1" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 outline-none">
                            Payment authorization is not ready yet.
                            <button type="button" wire:click="startPaymentAuthorization" class="font-semibold underline underline-offset-2">Confirm card authorization</button>
                            or update your card in
                            <a href="{{ route('family.billing.show') }}" wire:navigate class="font-medium underline underline-offset-2">Billing & Payments</a>.
                        </div>
                    @else
                        <div @if ($payment->last_error) data-ai-target="family.request.payment_attention" tabindex="-1" @endif class="rounded-xl border border-[#E4DDD3] bg-[#F7F2EA] px-3 py-2 text-xs text-[#485E53] outline-none">
                            Payment status: <span class="font-semibold text-[#23483F]">{{ ucfirst(str_replace('_', ' ', $payment->status)) }}</span>
                            @if ($payment->amount_authorized_cents)
                                - Authorized ${{ number_format($payment->amount_authorized_cents / 100, 2) }}
                            @endif
                            @if ($payment->amount_captured_cents)
                                - Captured ${{ number_format($payment->amount_captured_cents / 100, 2) }}
                            @endif
                            @if ($payment->last_error)
                                <span class="mt-1 block text-amber-800">{{ $payment->last_error }}</span>
                                <a href="{{ route('family.billing.show') }}" wire:navigate class="mt-1 inline-block font-semibold text-amber-900 underline underline-offset-2">Use a different card</a>
                            @endif
                        </div>
                    @endif
                @endif

                @if ($showVisitActionStrip)
                <div class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                        @if ($hiredConversation && ! $isLiveVisit)
                            <a href="{{ route('messages.show', $hiredConversation->id) }}" wire:navigate>
                                <x-button color="indigo" class="hc-action-primary w-full sm:w-auto">Open chat</x-button>
                            </a>
                        @endif

                        @if (! $isLiveVisit && in_array($booking->status, [\App\Models\CareBooking::STATUS_IN_PROGRESS, \App\Models\CareBooking::STATUS_PAUSED], true))
                            <x-button color="green" wire:click="reviewCompletion" class="hc-action-primary w-full sm:w-auto">The visit has ended</x-button>
                        @endif

                        @if ($canMarkNoShow)
                            <x-button color="red" light wire:click="markNoShow" onclick="if (!confirm('Mark this caregiver as no-show? This cancels the visit and affects reliability.')) return false;" class="w-full sm:w-auto">Mark caregiver no-show</x-button>
                        @endif

                        @if (! $isLiveVisit)
                            <x-button color="white" light wire:click="setActiveTab('support')" class="hc-action-secondary w-full sm:w-auto">{{ $supportButtonLabel }}</x-button>
                        @endif
                    </div>

                    @if ($booking->status === \App\Models\CareBooking::STATUS_SCHEDULED && ! $canMarkNoShow)
                        <details class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] px-4 py-3 text-sm text-[#485E53]">
                            <summary class="cursor-pointer list-none font-semibold text-[#23483F] [&::-webkit-details-marker]:hidden">
                                If the caregiver is late
                            </summary>
                            <div class="mt-2 space-y-2">
                                @if ($noShowEligibleAt)
                                    <p>No-show becomes available at {{ $noShowEligibleAt->format('M d, g:i A') }}. Until then, message the caregiver or contact support.</p>
                                @else
                                    <p>No-show needs a scheduled start time. Contact support if the caregiver has not arrived.</p>
                                @endif
                            </div>
                        </details>
                    @endif
                </div>
                @endif

                @if ($booking->status !== \App\Models\CareBooking::STATUS_SCHEDULED)
                    <div class="space-y-4 text-sm">

                        @if (($booking->timesheet_submitted_at || $booking->worked_minutes) && ! $timesheetNeedsReview)
                            @if ($isFinalVisitRecord)
                                <div data-ai-target="family.request.timesheet" tabindex="-1" class="rounded-2xl border border-[#CFE1D8] bg-[#F6FBF8] p-4 outline-none">
                                    <p class="text-xs uppercase tracking-[0.12em] text-emerald-700">Visit receipt</p>
                                    <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                                        <div class="rounded-xl border border-[#D8E1D7] bg-white px-3 py-2">
                                            <p class="text-xs uppercase tracking-[0.12em] text-[#485E53]">Worked time</p>
                                            <p class="mt-1 text-lg font-semibold text-[#23483F]">{{ $workedLabel }}</p>
                                        </div>
                                        <div class="rounded-xl border border-[#D8E1D7] bg-white px-3 py-2">
                                            <p class="text-xs uppercase tracking-[0.12em] text-[#485E53]">Payment</p>
                                            <p class="mt-1 text-lg font-semibold text-[#23483F]">
                                                @if ($payment?->amount_captured_cents)
                                                    ${{ number_format($payment->amount_captured_cents / 100, 2) }}
                                                @else
                                                    ${{ number_format($shiftEarnings, 2) }}
                                                @endif
                                            </p>
                                            <p class="text-xs text-[#485E53]">{{ $payment ? ucfirst(str_replace('_', ' ', (string) $payment->status)) : 'No payment recorded' }}</p>
                                        </div>
                                        <div class="rounded-xl border border-[#D8E1D7] bg-white px-3 py-2">
                                            <p class="text-xs uppercase tracking-[0.12em] text-[#485E53]">Timesheet</p>
                                            <p class="mt-1 text-lg font-semibold text-[#23483F]">Confirmed</p>
                                            <p class="text-xs text-[#485E53]">
                                                {{ optional($booking->family_confirmed_at)->format('M d, H:i') ?: 'Confirmed by family' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div data-ai-target="family.request.timesheet" tabindex="-1" class="grid grid-cols-1 gap-3 rounded-lg border border-[#E4DDD3] bg-[#F7F2EA] p-3 outline-none md:grid-cols-3">
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.12em] text-[#485E53]">Worked time</p>
                                        <p class="mt-1 text-base font-semibold text-[#23483F]">{{ $workedLabel }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.12em] text-[#485E53]">Estimated visit total</p>
                                        <p class="mt-1 text-base font-semibold text-[#23483F]">${{ number_format($estimatedPaymentTotal, 2) }}</p>
                                        <p class="text-xs text-[#485E53]">{{ '$'.number_format($shiftRate, 2) }}/hr care{{ $usesPricingV2 ? ' + processing fee' : '' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.12em] text-[#485E53]">Timesheet</p>
                                        <p class="mt-1 text-base font-semibold text-[#23483F]">
                                            {{ $booking->family_confirmed_at ? 'Confirmed' : 'Awaiting your confirmation' }}
                                        </p>
                                        <p class="text-xs text-[#485E53]">
                                            Submitted {{ optional($booking->timesheet_submitted_at)->format('M d, H:i') ?: 'Pending' }}
                                        </p>
                                    </div>
                                </div>
                            @endif
                        @endif

                        @if ($isFinalVisitRecord && ($familyReview || $caregiverReview))
                            <div class="rounded-2xl border border-[#E4DDD3] bg-white p-4">
                                <p class="text-xs uppercase tracking-[0.12em] text-[#485E53]">Reviews</p>
                                <div class="mt-3 grid grid-cols-1 gap-3 {{ $caregiverReview ? 'lg:grid-cols-2' : '' }}">
                                    @if ($familyReview)
                                        <div class="rounded-xl border border-[#E4DDD3] bg-[#FFFCF8] px-3 py-3">
                                            <p class="font-semibold text-[#23483F]">Your review</p>
                                            <div class="mt-2 flex items-center gap-1">
                                                @for ($star = 1; $star <= 5; $star++)
                                                    <svg viewBox="0 0 20 20" class="h-5 w-5 {{ ((int) ($familyReview?->rating ?? 0)) >= $star ? 'text-amber-400' : 'text-[#D7DEE6]' }}" fill="currentColor" aria-hidden="true">
                                                        <path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.14 3.5a1 1 0 00.95.69h3.68c.97 0 1.38 1.24.6 1.81l-2.98 2.17a1 1 0 00-.36 1.12l1.14 3.5c.3.92-.75 1.68-1.54 1.12l-2.98-2.17a1 1 0 00-1.18 0l-2.98 2.17c-.79.57-1.84-.2-1.54-1.12l1.14-3.5a1 1 0 00-.36-1.12L2.68 8.93c-.78-.57-.37-1.81.6-1.81h3.68a1 1 0 00.95-.69l1.14-3.5z"/>
                                                    </svg>
                                                @endfor
                                                <span class="ml-1 text-sm font-medium text-[#485E53]">{{ (int) ($familyReview?->rating ?? 0) }}/5</span>
                                            </div>
                                            <p class="mt-2 text-sm text-[#485E53]">{{ $familyReview->comment ?: 'No additional comment was provided.' }}</p>
                                        </div>
                                    @endif

                                    @if ($caregiverReview)
                                        <div class="rounded-xl border border-[#E4DDD3] bg-[#FFFCF8] px-3 py-3">
                                            <p class="font-semibold text-[#23483F]">Caregiver feedback</p>
                                            <div class="mt-2 flex items-center gap-1">
                                                @for ($star = 1; $star <= 5; $star++)
                                                    <svg viewBox="0 0 20 20" class="h-5 w-5 {{ ((int) ($caregiverReview->rating ?? 0)) >= $star ? 'text-amber-400' : 'text-[#D7DEE6]' }}" fill="currentColor" aria-hidden="true">
                                                        <path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.14 3.5a1 1 0 00.95.69h3.68c.97 0 1.38 1.24.6 1.81l-2.98 2.17a1 1 0 00-.36 1.12l1.14 3.5c.3.92-.75 1.68-1.54 1.12l-2.98-2.17a1 1 0 00-1.18 0l-2.98 2.17c-.79.57-1.84-.2-1.54-1.12l1.14-3.5a1 1 0 00-.36-1.12L2.68 8.93c-.78-.57-.37-1.81.6-1.81h3.68a1 1 0 00.95-.69l1.14-3.5z"/>
                                                    </svg>
                                                @endfor
                                                <span class="ml-1 text-sm font-medium text-[#485E53]">{{ (int) $caregiverReview->rating }}/5</span>
                                            </div>
                                            <p class="mt-2 text-sm text-[#485E53]">{{ $caregiverReview->comment ?: 'No comment left by caregiver.' }}</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <details class="rounded-2xl border border-[#E4DDD3] bg-white p-4">
                            <summary class="cursor-pointer list-none font-display text-base font-semibold text-[#23483F] [&::-webkit-details-marker]:hidden">
                                Detailed visit record
                            </summary>
                            <div class="mt-4 space-y-4 border-t border-[#EFE6D8] pt-4">
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-4 text-xs text-[#485E53]">
                                    <div class="rounded-lg border border-[#E4DDD3] bg-[#FFFCF8] px-3 py-2">
                                        <p class="font-semibold text-[#23483F]">Caregiver check-in</p>
                                        <p class="mt-1">{{ optional($booking->started_at)->format('M d, H:i') ?: 'Pending' }}</p>
                                    </div>
                                    <div class="rounded-lg border border-[#E4DDD3] bg-[#FFFCF8] px-3 py-2">
                                        <p class="font-semibold text-[#23483F]">Caregiver check-out</p>
                                        <p class="mt-1">{{ optional($booking->completed_at)->format('M d, H:i') ?: 'Pending' }}</p>
                                    </div>
                                    <div class="rounded-lg border border-[#E4DDD3] bg-[#FFFCF8] px-3 py-2">
                                        <p class="font-semibold text-[#23483F]">Family confirmation</p>
                                        <p class="mt-1">{{ optional($booking->family_confirmed_at)->format('M d, H:i') ?: 'Pending' }}</p>
                                    </div>
                                    <div class="rounded-lg border border-[#E4DDD3] bg-[#FFFCF8] px-3 py-2">
                                        <p class="font-semibold text-[#23483F]">Dispute</p>
                                        <p class="mt-1">{{ strtoupper($booking->dispute_status ?? 'none') }}</p>
                                    </div>
                                </div>

                                @if ($booking->expected_minutes || $booking->worked_minutes)
                                    <p class="text-xs text-[#485E53]">
                                        Expected {{ $booking->expected_minutes ?? '-' }} minutes - worked {{ $booking->worked_minutes ?? '-' }} minutes.
                                    </p>
                                @endif

                                <div class="rounded-xl border border-[#E4DDD3] bg-[#FFFCF8] p-3">
                                    <p class="font-medium text-[#23483F]">Care tasks</p>
                                    <div class="mt-3 space-y-2">
                                        @forelse ($booking->taskChecks as $taskCheck)
                                            <div class="rounded border border-[#E4DDD3] bg-white px-3 py-2">
                                                <p class="{{ $taskCheck->is_completed ? 'line-through text-[#485E53]' : 'text-[#23483F]' }}">{{ $taskCheck->label }}</p>
                                                @if ($taskCheck->notes)
                                                    <p class="text-xs text-[#485E53]">{{ $taskCheck->notes }}</p>
                                                @endif
                                            </div>
                                        @empty
                                            <p class="text-xs text-[#485E53]">No task checks yet.</p>
                                        @endforelse
                                    </div>
                                </div>

                                <div class="rounded-xl border border-[#E4DDD3] bg-[#FFFCF8] p-3">
                                    <p class="font-medium text-[#23483F]">Visit timeline</p>
                                    <div class="mt-3 max-h-52 space-y-1 overflow-auto text-xs text-[#485E53]">
                                        @forelse ($booking->events->take(20) as $event)
                                            <p>{{ optional($event->happened_at)->format('M d H:i') }} - {{ strtoupper(str_replace('_', ' ', $event->event_type)) }}</p>
                                        @empty
                                            <p>No events yet.</p>
                                        @endforelse
                                    </div>
                                </div>

                                @if ($booking->changeRequests->count() > 0)
                                    <div class="rounded-xl border border-[#E4DDD3] bg-[#FFFCF8] p-3">
                                        <p class="font-medium text-[#23483F]">Change requests</p>
                                        <div class="mt-3 space-y-2">
                                            @foreach ($booking->changeRequests as $change)
                                                <div
                                                    @if ($change->status === 'pending' && $change->requester?->role === 'caregiver') data-ai-target="family.request.visit_issue" tabindex="-1" @endif
                                                    class="rounded-md border border-[#E4DDD3] bg-white px-3 py-2 outline-none"
                                                >
                                                    <p class="font-medium">{{ strtoupper($change->type) }} - {{ strtoupper($change->status) }}</p>
                                                    <p class="text-[#485E53]">{{ $change->reason }}</p>
                                                    @if ($change->proposed_start_at)
                                                        <p class="text-xs text-[#485E53]">
                                                            Proposed:
                                                            {{ optional($change->proposed_start_at)->format('M d, Y H:i') }}
                                                            to
                                                            {{ optional($change->proposed_end_at)->format('M d, Y H:i') }}
                                                        </p>
                                                    @endif
                                                    @if ($change->status === 'pending' && (int) $change->requester_user_id !== (int) auth()->id())
                                                        <div class="mt-2 flex gap-2">
                                                            <x-button color="green" light wire:click="resolveChangeRequest({{ $change->id }}, 'accept')" class="hc-action-secondary">Accept</x-button>
                                                            <x-button color="red" light wire:click="resolveChangeRequest({{ $change->id }}, 'reject')">Reject</x-button>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </details>
                    </div>
                @elseif ($booking->changeRequests->count() > 0)
                    <details class="rounded-lg border border-[#E4DDD3] bg-white p-3 text-sm">
                        <summary class="cursor-pointer font-medium text-[#23483F]">Change requests</summary>
                        <div class="mt-3 space-y-2">
                            @foreach ($booking->changeRequests as $change)
                                <div
                                    @if ($change->status === 'pending' && $change->requester?->role === 'caregiver') data-ai-target="family.request.visit_issue" tabindex="-1" @endif
                                    class="rounded-md border border-[#E4DDD3] px-3 py-2 outline-none"
                                >
                                    <p class="font-medium">{{ strtoupper($change->type) }} - {{ strtoupper($change->status) }}</p>
                                    <p class="text-[#485E53]">{{ $change->reason }}</p>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endif
                @endif
            </section>

            @if ($isLiveVisit)
                @include('livewire.family.partials.live-visit-record')
            @endif

            @if (in_array($booking->status, [\App\Models\CareBooking::STATUS_COMPLETED, \App\Models\CareBooking::STATUS_REVIEWED], true) && ! $timesheetNeedsReview && $canLeaveFamilyReview)
                <div id="family-review-form" class="mx-auto grid max-w-5xl grid-cols-1 gap-4">
                <x-card>
                    <x-slot:header>
                        <h2 class="font-display text-lg font-semibold">Leave a caregiver review</h2>
                        <p class="text-xs text-[#485E53]">Tap stars to rate this visit.</p>
                    </x-slot:header>

                    <div class="space-y-4">
                        <div>
                            <p class="text-sm font-medium text-[#324457]">Rating</p>
                            <div class="mt-2 flex items-center gap-1">
                                @for ($star = 1; $star <= 5; $star++)
                                    <button
                                        type="button"
                                        wire:click="$set('reviewRating', {{ $star }})"
                                        class="rounded-md p-1 transition hover:scale-105 focus:outline-none focus:ring-2 focus:ring-amber-300"
                                        aria-label="Rate {{ $star }} out of 5"
                                        aria-pressed="{{ ($reviewRating ?? 0) === $star ? 'true' : 'false' }}"
                                    >
                                        <svg viewBox="0 0 20 20" class="h-8 w-8 {{ ($reviewRating ?? 0) >= $star ? 'text-amber-400' : 'text-[#D7DEE6]' }}" fill="currentColor" aria-hidden="true">
                                            <path d="M9.05 2.93c.3-.92 1.6-.92 1.9 0l1.14 3.5a1 1 0 00.95.69h3.68c.97 0 1.38 1.24.6 1.81l-2.98 2.17a1 1 0 00-.36 1.12l1.14 3.5c.3.92-.75 1.68-1.54 1.12l-2.98-2.17a1 1 0 00-1.18 0l-2.98 2.17c-.79.57-1.84-.2-1.54-1.12l1.14-3.5a1 1 0 00-.36-1.12L2.68 8.93c-.78-.57-.37-1.81.6-1.81h3.68a1 1 0 00.95-.69l1.14-3.5z"/>
                                        </svg>
                                    </button>
                                @endfor
                            </div>
                            <p class="mt-1 text-xs text-[#485E53]">
                                @if ($reviewRating)
                                    Selected rating: {{ $reviewRating }}/5
                                @else
                                    No rating selected yet.
                                @endif
                            </p>
                            @error('reviewRating') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <x-textarea label="Review comment" wire:model="reviewComment" />
                        @error('reviewComment') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <x-slot:footer>
                        <x-button color="amber" wire:click="submitReview">Submit review</x-button>
                    </x-slot:footer>
                </x-card>
                </div>
            @endif
        @endif
    @endif

    @if ($activeTab === 'support')
        <div class="hc-surface flex flex-wrap items-center justify-between gap-3 p-4">
            <p class="text-sm">Need a hand? Chat with support or use the visit options below.</p>
            <button type="button" x-on:click="$dispatch('open-care-support-chat')" class="hc-secondary-button">Chat with support</button>
        </div>
        @if (! $booking)
            <x-card>
                <x-slot:header><h2 class="font-display text-lg font-semibold">Support</h2></x-slot:header>
                <div class="rounded-md border border-dashed border-[#D6CCBE] px-4 py-6 text-sm text-[#485E53]">
                    Support tools appear after a caregiver is hired and a visit exists.
                </div>
            </x-card>
        @else
            <x-card>
                <x-slot:header>
                    <div>
                        <p class="hc-brand-kicker">{{ $supportScreenEyebrow }}</p>
                        <h2 class="mt-1 font-display text-xl font-semibold text-[#23483F]">{{ $supportScreenTitle }}</h2>
                        <p class="mt-1 max-w-2xl text-sm text-[#485E53]">{{ $supportScreenBody }}</p>
                    </div>
                </x-slot:header>
                <div class="space-y-4">
                    @if ($canRequestVisitChange)
                        <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h3 class="font-display text-lg font-semibold text-[#23483F]">Request cancellation or reschedule</h3>
                                    <p class="mt-1 text-sm text-[#485E53]">Use this for schedule changes before the caregiver checks in.</p>
                                </div>
                                <x-badge color="blue" text="Before check-in" />
                            </div>
                            <div class="mt-4 space-y-4">
                                <x-native-select-field
                                    label="Change type"
                                    wire:model="changeType"
                                    :options="[
                                        ['label' => 'Cancel visit', 'value' => 'cancel'],
                                        ['label' => 'Reschedule visit', 'value' => 'reschedule'],
                                    ]"
                                />
                                <x-textarea label="Reason" wire:model="changeReason" />
                                @if ($changeType === 'reschedule')
                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <x-input type="datetime-local" label="Proposed start" wire:model="proposedStartAt" />
                                        <x-input type="datetime-local" label="Proposed end" wire:model="proposedEndAt" />
                                    </div>
                                @endif
                                <x-button color="blue" wire:click="submitChangeRequest" class="hc-action-primary">Send request</x-button>
                            </div>
                            @if ($canCancelScheduledShift)
                                <details class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-950">
                                    <summary class="cursor-pointer list-none font-semibold [&::-webkit-details-marker]:hidden">
                                        Need to cancel now?
                                    </summary>
                                    <div class="mt-3 space-y-3 text-sm">
                                        <p>
                                            This cancels the visit before caregiver check-in and releases the payment authorization when possible.
                                            @if ($lateCancel)
                                                It is inside the late-cancellation window.
                                            @endif
                                        </p>
                                        <x-textarea label="Cancellation reason" wire:model="directCancelReason" />
                                        @error('directCancelReason') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                                        <x-button color="red" wire:click="cancelScheduledBooking" onclick="if (!confirm('Cancel this scheduled visit?')) return false;">Cancel visit</x-button>
                                    </div>
                                </details>
                            @endif
                        </div>
                    @elseif ($timesheetNeedsReview)
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-950">
                            <h3 class="font-display text-lg font-semibold">Question the submitted hours</h3>
                            <p class="mt-1 text-sm">
                                Use this before approving if the worked time, location, or payment amount looks wrong.
                            </p>
                            <div class="mt-4 space-y-3">
                                <x-textarea label="What looks wrong?" wire:model="disputeReason" />
                                <x-button color="red" wire:click="openDispute">Open dispute</x-button>
                            </div>
                        </div>
                    @elseif (in_array($booking->status, [\App\Models\CareBooking::STATUS_IN_PROGRESS, \App\Models\CareBooking::STATUS_PAUSED], true))
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-950">
                            <h3 class="font-display text-lg font-semibold">During the visit</h3>
                            <p class="mt-1 text-sm">
                                Message the caregiver for ordinary updates. Use an incident report if there is a safety concern or something needs LoLo Care review.
                            </p>
                            @if ($hiredConversation)
                                <a href="{{ route('messages.show', $hiredConversation->id) }}" wire:navigate class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-white px-4 text-sm font-semibold text-emerald-950 shadow-sm">
                                    Open chat
                                </a>
                            @endif
                        </div>
                    @endif

                    <details class="rounded border border-[#E4DDD3] p-3">
                        <summary class="cursor-pointer font-medium">Support ticket</summary>
                        <div class="mt-3 space-y-4">
                            <x-input label="Subject" wire:model="supportSubject" />
                            <x-native-select-field
                                label="Category"
                                wire:model="supportCategory"
                                :options="[
                                    ['label' => 'General question', 'value' => 'general'],
                                    ['label' => 'The hours look wrong', 'value' => 'dispute'],
                                    ['label' => 'I am worried about safety', 'value' => 'incident'],
                                    ['label' => 'I need to cancel or reschedule', 'value' => 'cancellation'],
                                    ['label' => 'Payment question', 'value' => 'billing'],
                                    ['label' => 'Time correction', 'value' => 'time_correction'],
                                ]"
                            />
                            <x-textarea label="Describe issue" wire:model="supportDescription" />
                            <x-button color="red" wire:click="createSupportTicket">Create support ticket</x-button>
                        </div>
                    </details>

                    <details class="rounded border border-[#E4DDD3] p-3">
                        <summary class="cursor-pointer font-medium">Report incident</summary>
                        <div class="mt-3 space-y-4">
                            <x-input label="Incident title" wire:model="incidentTitle" />
                            <x-native-select-field
                                label="Severity"
                                wire:model="incidentSeverity"
                                :options="[
                                    ['label' => 'Low', 'value' => 'low'],
                                    ['label' => 'Medium', 'value' => 'medium'],
                                    ['label' => 'High', 'value' => 'high'],
                                ]"
                            />
                            <x-textarea label="Description" wire:model="incidentDescription" />
                            <x-button color="red" light wire:click="reportIncident">Submit incident</x-button>
                        </div>
                    </details>

                    @if ($canDisputeVisit && ! $timesheetNeedsReview)
                        <details class="rounded border border-[#E4DDD3] p-3">
                            <summary class="cursor-pointer font-medium text-red-700">Open dispute</summary>
                            <div class="mt-3 space-y-3">
                                <x-textarea label="Dispute reason" wire:model="disputeReason" />
                                <x-button color="red" wire:click="openDispute">Open dispute</x-button>
                            </div>
                        </details>
                    @endif
                </div>
            </x-card>
        @endif
    @endif

    @if ($showCaregiverInvitePanel)
        @include('livewire.family.partials.caregiver-invite-panel')
    @endif

    @if ($booking && $mobilePrimaryAction && ! $reviewingCompletion && ! $reviewingApplicationId && in_array($activeTab, ['home', 'shift'], true))
        <div class="hc-mobile-primary-bar fixed inset-x-0 bottom-0 z-40 border-t border-[#D8D0C5] bg-[#FFFCF8]/95 p-3 shadow-[0_-8px_24px_rgba(23,49,63,0.12)] backdrop-blur sm:hidden">
            @if ($mobilePrimaryAction['type'] === 'payment')
                <button type="button" wire:click="startPaymentAuthorization" wire:loading.attr="disabled" class="hc-primary-button w-full">{{ $mobilePrimaryAction['label'] }}</button>
            @elseif ($mobilePrimaryAction['type'] === 'new_date')
                <a href="{{ route('family.requests.create', ['type' => \App\Models\CareRequest::TYPE_ONE_TIME]) }}" wire:navigate class="hc-primary-button w-full">{{ $mobilePrimaryAction['label'] }}</a>
            @elseif ($mobilePrimaryAction['type'] === 'approve_hours' || $mobilePrimaryAction['type'] === 'complete_visit')
                <button type="button" wire:click="reviewCompletion" class="hc-primary-button w-full">{{ $mobilePrimaryAction['label'] }}</button>
            @elseif ($mobilePrimaryAction['type'] === 'open_visit')
                <button type="button" wire:click="setActiveTab('shift')" class="hc-primary-button w-full">{{ $mobilePrimaryAction['label'] }}</button>
            @elseif ($mobilePrimaryAction['type'] === 'find_caregivers')
                <button type="button" wire:click="openCaregiverInvitePanel" class="hc-primary-button w-full">{{ $mobilePrimaryAction['label'] }}</button>
            @else
                <button type="button" wire:click="setActiveTab('applicants')" class="hc-primary-button w-full">{{ $mobilePrimaryAction['label'] }}</button>
            @endif
        </div>
    @endif
</div>

@script
<script>
    window.homecareLoadStripeJs = window.homecareLoadStripeJs || (() => new Promise((resolve, reject) => {
        if (window.Stripe) {
            resolve(window.Stripe);

            return;
        }

        const existing = document.querySelector('script[src="https://js.stripe.com/v3/"]');
        if (existing) {
            existing.addEventListener('load', () => resolve(window.Stripe));
            existing.addEventListener('error', () => reject(new Error('Stripe.js could not be loaded.')));

            return;
        }

        const script = document.createElement('script');
        script.src = 'https://js.stripe.com/v3/';
        script.async = true;
        script.onload = () => resolve(window.Stripe);
        script.onerror = () => reject(new Error('Stripe.js could not be loaded.'));
        document.head.appendChild(script);
    }));

    $wire.on('confirm-stripe-booking-payment', async (payload) => {
        const detail = Array.isArray(payload) ? (payload[0] || {}) : (payload || {});

        if (window.homecareConfirmingBookingPayment || !detail.clientSecret || !detail.publishableKey) {
            return;
        }

        window.homecareConfirmingBookingPayment = true;
        window.dispatchEvent(new CustomEvent('payment-confirmation-started'));

        try {
            const StripeConstructor = await window.homecareLoadStripeJs();
            const stripe = StripeConstructor(detail.publishableKey);
            const confirmParams = {
                return_url: window.location.href,
            };

            if (detail.paymentMethodId) {
                confirmParams.payment_method = detail.paymentMethodId;
            }

            const result = await stripe.confirmCardPayment(detail.clientSecret, confirmParams);

            if (result.error) {
                const paymentIntentId = result.error.payment_intent?.id || detail.paymentIntentId || '';
                await $wire.failStripeAuthorization(paymentIntentId, result.error.message || 'Card authorization failed.');

                return;
            }

            if (result.paymentIntent?.id) {
                await $wire.finalizeStripeAuthorization(result.paymentIntent.id);
            }
        } catch (error) {
            await $wire.failStripeAuthorization(detail.paymentIntentId || '', error?.message || 'Card authorization failed.');
        } finally {
            window.homecareConfirmingBookingPayment = false;
            window.dispatchEvent(new CustomEvent('payment-confirmation-finished'));
        }
    });
</script>
@endscript
