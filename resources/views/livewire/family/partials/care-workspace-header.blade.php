@php
    $parentPlanId = (int) ($booking?->care_plan_id ?: $requestItem->care_plan_id);
    $isRecurringVisit = $booking && $parentPlanId;
    $returnFilter = in_array($returnVisitFilter, ['upcoming', 'attention', 'past', 'cancelled', 'all'], true) ? $returnVisitFilter : 'upcoming';
    $returnTab = in_array($returnCareTab, ['overview', 'caregivers', 'visits', 'details'], true) ? $returnCareTab : 'visits';
    $workspaceTabs = $stageTabs->filter(fn ($tab) => empty($tab['hidden']) && ! ($isRecurringVisit && $tab['key'] === 'applicants') && ! (! $booking && $plainRequestType === 'Recurring care' && $tab['key'] === 'support'));
@endphp
@if ($booking)
<header class="hc-care-heading">
    <nav aria-label="Care location" class="hc-care-breadcrumb">
        <a href="{{ route('family.care.index') }}" wire:navigate>My care</a>
        <span aria-hidden="true">/</span>
        @if ($parentPlanId)
            <a href="{{ route('family.care.show', ['carePlan' => $parentPlanId, 'tab' => $returnTab, 'visit_filter' => $returnFilter, 'visitsPage' => min(1000000, max(1, $returnVisitPage))]).($returnTab === 'visits' ? '#care-visit-'.$booking?->id : '') }}" wire:navigate>Back to recurring care{{ $returnTab === 'visits' ? ' · Visits' : '' }}</a>
            <span aria-hidden="true">/</span><span>Visit #{{ $booking?->id }}</span>
        @else
            <span>{{ $plainRequestType }}</span>
        @endif
    </nav>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <p class="hc-care-eyebrow">{{ $isRecurringVisit ? 'One visit in your recurring care' : $plainRequestType }}</p>
            <h1 class="hc-care-title">{{ $recordHeadline }}</h1>
            <p class="hc-care-subtitle">{{ $plainSchedule }} · {{ $requestItem->city }}, {{ $requestItem->state }}</p>
            @if ($booking?->caregiver)<p class="hc-care-meta mt-1">Caregiver: {{ $booking->caregiver->name }}</p>@endif
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="hc-care-status">{{ $booking ? $visitStatusLabel : ($requestDatePassed ? 'Requested date passed' : ($requestItem->status === 'open' ? 'Finding a caregiver' : ucfirst($requestItem->status))) }}</span>
                @if ($requestItem->is_private)<span class="hc-care-meta">By invitation</span>@endif
                <span class="hc-care-meta">{{ $booking ? 'Visit #'.$booking->id : 'Request #'.$requestItem->id }}</span>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 sm:justify-end">
            @if ($hiredApplication)
                <button type="button" wire:click="startConversation({{ $hiredApplication->id }})" class="hc-secondary-button">Message caregiver</button>
            @endif
            <button type="button" wire:click="setActiveTab('support')" class="hc-secondary-button">Get help</button>
            <a href="{{ route('family.care.journey', ['resourceType' => $parentPlanId ? 'regular' : 'request', 'resourceId' => $parentPlanId ?: $requestItem->id]) }}" wire:navigate class="hc-care-text-link">Care timeline</a>
        </div>
    </div>
</header>
<nav class="hc-care-tabs" aria-label="{{ $isRecurringVisit ? 'This visit' : 'Care navigation' }}" style="--care-tab-count: {{ $workspaceTabs->count() }}">
    @foreach ($workspaceTabs as $tab)
        <button type="button" wire:click="setActiveTab('{{ $tab['key'] }}')" class="hc-care-tab" @if ($activeTab === $tab['key']) aria-current="page" @endif>
            {{ $isRecurringVisit ? match ($tab['key']) { 'shift' => 'This visit', 'overview' => 'Care instructions', 'support' => 'Help & changes', default => $tab['label'] } : $tab['label'] }}
        </button>
    @endforeach
</nav>
@endif

@if ($booking && in_array($activeTab, ['home', 'shift'], true))
<section id="care-request-primary" class="hc-care-next" aria-labelledby="care-next-title" @if ($timesheetNeedsReview && ! $reviewingCompletion) data-ai-target="family.request.timesheet" tabindex="-1" @endif>
    <div class="hc-care-next-copy">
        <p class="hc-care-eyebrow">{{ $lifecycleStage['eyebrow'] }}</p>
        <h2 id="care-next-title">{{ $hasActiveTimeCorrection ? 'Review the updated visit hours' : $lifecycleStage['title'] }}</h2>
        <p>{{ $hasActiveTimeCorrection ? 'A time correction is in progress. Its review and payment status are shown below.' : ($visitStageSummary ?: $lifecycleStage['body']) }}</p>
        @if ($isRecurringVisit)<p class="hc-care-meta mt-2">Changes and approvals here apply to this dated visit. Your recurring schedule has its own controls.</p>@endif
    </div>
    <div class="flex flex-col gap-2 sm:items-start">
        @if ($requestDatePassed)
            <a href="{{ route('family.requests.create', ['type' => 'one_time']) }}" wire:navigate class="hc-primary-button">Choose another date</a>
        @elseif ($isWaitingForCaregivers)
            <button type="button" wire:click="openCaregiverInvitePanel" class="hc-primary-button">Find matching caregivers</button>
        @elseif ($isReviewingCaregivers)
            <button type="button" wire:click="setActiveTab('applicants')" class="hc-primary-button">Review {{ $openCaregiverResponses }} caregiver{{ $openCaregiverResponses === 1 ? '' : 's' }}</button>
            <button type="button" wire:click="openCaregiverInvitePanel" class="hc-care-text-link">Invite someone else</button>
        @elseif ($timesheetNeedsReview)
            <p class="hc-care-total">{{ $workedLabel }} <span>· ${{ number_format($estimatedPaymentTotal, 2) }}</span></p>
            <button type="button" wire:click="reviewCompletion" class="hc-primary-button">Review hours & payment</button>
        @elseif ($isLiveVisit)
            <button type="button" wire:click="reviewCompletion" class="hc-primary-button">The visit has ended</button>
        @elseif ($isScheduledVisit)
            <button type="button" wire:click="setActiveTab('support')" class="hc-secondary-button">Change or cancel this visit</button>
        @elseif ($canLeaveFamilyReview)
            <a href="#family-review-form" class="hc-primary-button">Leave a review</a>
        @elseif ($canRebookHiredCaregiver)
            <a href="{{ route('family.requests.book_again', $requestItem->id) }}" wire:navigate class="hc-primary-button">Book {{ $hiredCaregiverFirstName }} again</a>
        @endif
    </div>
</section>
@endif

@if ($activeTab === 'shift' && ($isScheduledVisit || $isLiveVisit))
    @include('livewire.family.partials.visit-care-essentials')
@endif

@if ($activeTab === 'visits' && ! $booking)
    <section class="hc-surface p-5 sm:p-6">
        <p class="hc-care-eyebrow">Requested schedule</p><h2 class="mt-1 text-2xl font-semibold">Your weekly care pattern</h2>
        <p class="hc-care-subtitle mt-2">These are requested times. Visits become confirmed when you hire a caregiver.</p>
        <div class="mt-5 space-y-3">
            @foreach ($requestItem->recurringScheduleSlots() as $slot)
                <div class="hc-care-visit-row"><p class="font-semibold">{{ ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][(int) $slot['day']] ?? 'Day' }}</p><p>{{ $slot['start_time'] }}–{{ $slot['end_time'] }}</p><span class="hc-care-status">Requested</span></div>
            @endforeach
        </div>
        <p class="mt-4 text-sm">Starting {{ $requestItem->recurring_starts_on?->format('F j, Y') ?: 'when agreed' }}{{ $requestItem->recurring_ends_on ? ' · Ending '.$requestItem->recurring_ends_on->format('F j, Y') : ' · No end date requested' }}.</p>
        <button type="button" wire:click="setActiveTab('applicants')" class="hc-primary-button mt-5">View caregivers</button>
    </section>
@endif
