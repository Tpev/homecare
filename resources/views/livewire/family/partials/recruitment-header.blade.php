@php
    $recruitStage = $reviewingApplicationId ? 'start' : match ($activeTab) {
        'invite' => 'find',
        'applicants' => 'review',
        'start' => 'start',
        'home', 'overview', 'visits' => 'request',
        default => null,
    };
    $recruitSteps = [
        ['key' => 'request', 'label' => 'Care request', 'description' => 'Care & schedule details', 'action' => "setActiveTab('home')"],
        ['key' => 'find', 'label' => 'Find caregivers', 'description' => 'Search & invite', 'action' => "setActiveTab('invite')"],
        ['key' => 'review', 'label' => 'Review applicants', 'description' => 'Compare & chat', 'action' => "setActiveTab('applicants')"],
        ['key' => 'start', 'label' => 'Get started', 'description' => 'Review & confirm care', 'action' => $reviewingApplicationId ? null : "setActiveTab('start')"],
    ];
    $recruitCanHire = $requestItem->status === 'open' && ! $requestDatePassed;
    $recruitUnavailableTitle = match ((string) $requestItem->status) {
        'draft' => 'This request is a draft',
        'filled' => 'Caregiver selected. Visit setup is pending.',
        default => 'This request is closed',
    };
    $recruitPendingBody = match ((string) $requestItem->status) {
        'draft' => 'This request has not been published. Your saved care details remain available.',
        'filled' => 'A caregiver has been selected, but the visit record is not ready yet. Your care details and caregiver history remain available.',
        default => null,
    };
@endphp
<header class="hc-recruit-header" x-data>
    <div class="hc-recruit-topline">
        <a href="{{ route('family.requests.index') }}" wire:navigate class="hc-recruit-back">← My care</a>
        <div class="hc-recruit-utility"><span>Request #{{ $requestItem->id }}</span><button type="button" wire:click="setActiveTab('support')">Get help</button><a href="{{ route('family.care.journey', ['resourceType' => 'request', 'resourceId' => $requestItem->id]) }}" wire:navigate>Care timeline</a></div>
    </div>
    <div class="hc-recruit-title-row">
        <div>
            <h1>{{ $requestItem->title }}</h1>
            <p class="hc-recruit-context">{{ $plainRequestType }} <span>·</span> {{ $requestItem->request_type === 'one_time' ? $recordHeadline : $recordRecipientName }} <span>·</span> {{ $requestItem->city }}, {{ $requestItem->state }}</p>
        </div>
        <div class="hc-recruit-request-actions">
            <span class="hc-recruit-status"><span aria-hidden="true"></span>{{ $requestDatePassed ? 'Requested date passed' : ($requestItem->status === 'open' ? ($requestItem->is_private ? 'By invitation' : 'Open for applications') : ucfirst($requestItem->status)) }}</span>
            @if ($canWithdrawRequest)
                <button type="button" x-on:click="$refs.withdrawDialog.showModal()" class="hc-recruit-withdraw-trigger" wire:loading.attr="disabled" wire:target="withdrawRequest">
                    <span wire:loading.remove wire:target="withdrawRequest">Withdraw request</span><span wire:loading wire:target="withdrawRequest">Withdrawing…</span>
                </button>
            @endif
        </div>
    </div>
    @if ($canWithdrawRequest)
        <dialog x-ref="withdrawDialog" class="hc-recruit-withdraw-dialog" aria-labelledby="withdraw-request-title" aria-describedby="withdraw-request-consequences">
            <h2 id="withdraw-request-title">Withdraw this request?</h2>
            <div id="withdraw-request-consequences">
                <p>Caregivers will no longer be able to apply.</p>
                <ul><li>Active applications and pending invitations will close.</li><li>Affected caregivers will be notified.</li></ul>
                <p>Your request and caregiver history stay available.</p>
            </div>
            <div class="hc-recruit-withdraw-actions">
                <button type="button" x-on:click="$refs.withdrawDialog.close()" class="hc-secondary-button" autofocus>Keep request</button>
                <button type="button" wire:click="withdrawRequest" x-on:click="$refs.withdrawDialog.close()" class="hc-recruit-withdraw-confirm" wire:loading.attr="disabled" wire:target="withdrawRequest">Withdraw request</button>
            </div>
        </dialog>
    @endif
</header>
<nav class="hc-recruit-stages" aria-label="Care recruitment">
    @foreach ($recruitSteps as $step)
        <button type="button" class="hc-care-tab hc-recruit-stage" @if ($step['action']) wire:click="{{ $step['action'] }}" @else disabled @endif @if ($recruitStage === $step['key']) aria-current="step" @endif>
            <span class="hc-recruit-focus-ring" aria-hidden="true"></span>
            <span class="hc-recruit-step-number" aria-hidden="true">{{ $loop->iteration }}</span>
            <span class="hc-recruit-step-copy"><span class="hc-recruit-step-label">{{ $step['label'] }}</span><span class="hc-recruit-step-description">{{ $step['description'] }}</span></span>
            @if ($step['key'] === 'review' && $openCaregiverResponses)<span class="hc-recruit-count"><span class="sr-only">Active applicants: </span>{{ $openCaregiverResponses }}</span>@endif
        </button>
    @endforeach
</nav>
@if ($activeTab === 'home' && ! $reviewingApplicationId)
<div class="hc-recruit-overview">
    <section class="hc-recruit-next" aria-labelledby="recruit-next-title">
        <div class="hc-recruit-next-copy">
            <p class="hc-recruit-eyebrow">{{ $recruitCanHire ? ($openCaregiverResponses ? 'Up next · Step 3' : 'Up next · Step 2') : 'Request status' }}</p>
            <h2 id="recruit-next-title">{{ $requestDatePassed ? 'Choose a new date' : ($requestItem->status !== 'open' ? $recruitUnavailableTitle : ($openCaregiverResponses ? 'Meet your applicants' : 'Find the right caregiver')) }}</h2>
            <p>{{ $requestDatePassed ? 'This requested date has passed. Start a new request for the care you need.' : ($requestItem->status !== 'open' ? ($recruitPendingBody ?? 'Your request information and caregiver history remain available.') : ($openCaregiverResponses ? 'Compare their experience, read application notes and chat before choosing.' : ($requestItem->is_private ? 'Invite a caregiver to review your private request.' : 'Your request is posted. You can also invite someone who looks like a good fit.'))) }}</p>
        </div>
        @if ($requestDatePassed)<a href="{{ route('family.requests.create', ['type' => 'one_time']) }}" wire:navigate class="hc-primary-button">Choose another date</a>
        @elseif ($requestItem->status === 'open')<button type="button" wire:click="setActiveTab('{{ $openCaregiverResponses ? 'applicants' : 'invite' }}')" class="hc-primary-button">{{ $openCaregiverResponses ? 'Review applicants' : 'Find caregivers' }} <span aria-hidden="true">→</span></button>@endif
        <div class="hc-recruit-next-footer">
            <div class="hc-recruit-totals"><button type="button" wire:click="setActiveTab('applicants')"><strong>{{ $openCaregiverResponses }}</strong><span>Active applicants</span></button><button type="button" wire:click="setCaregiverView('invited')"><strong>{{ $requestItem->invitations->count() }}</strong><span>Invitations sent</span></button></div>
            <p class="hc-recruit-caption">{{ $recruitCanHire ? 'Hiring opens your '.($plainRequestType === 'Recurring care' ? 'recurring care home and its confirmed visits' : 'booked visit').'.' : 'Your care details and activity stay available below.' }}</p>
        </div>
    </section>
    <section class="hc-recruit-paper" aria-labelledby="request-summary-heading">
        <div class="hc-recruit-section-title"><h2 id="request-summary-heading">Your care request</h2><button type="button" wire:click="setActiveTab('overview')" class="hc-recruit-link">Full care details ↗</button></div>
        <dl class="hc-recruit-facts">
            <div><dt>Care for</dt><dd>{{ $recordRecipientName }}</dd></div>
            <div><dt>{{ $plainRequestType === 'Recurring care' ? 'Weekly schedule' : 'When' }}</dt><dd>{{ $recruitmentSchedule }}</dd></div>
            <div><dt>Location</dt><dd>{{ $serviceAddress }}</dd></div>
            <div><dt>Visibility</dt><dd>{{ $requestItem->is_private ? 'Only invited caregivers' : 'Caregivers can find and apply' }}</dd></div>
        </dl>
        @if ($plainRequestType === 'Recurring care')
            <p class="hc-recruit-caption">Starts {{ $requestItem->recurring_starts_on?->format('M j, Y') ?: 'when agreed' }}{{ $requestItem->recurring_ends_on ? ' · Ends '.$requestItem->recurring_ends_on->format('M j, Y') : ' · No end date' }}. Visits are confirmed after hiring.</p>
            <button type="button" wire:click="setActiveTab('visits')" class="hc-recruit-link">View requested weekly visits →</button>
        @endif
        <div class="hc-recruit-copy"><h3>What help is needed</h3><p>{{ $requestItem->scope_of_work }}</p></div>
        <div class="hc-recruit-tags">@foreach ($requestItem->tasks as $task)<span>{{ $task->name }}</span>@endforeach</div>
        @if ($requestItem->time_expectations)<p class="hc-recruit-caption">{{ $requestItem->time_expectations }}</p>@endif
    </section>
</div>
@endif
@if ($activeTab === 'start' && ! $reviewingApplicationId)
<section class="hc-recruit-paper hc-recruit-start" aria-labelledby="recruit-start-heading">
    <div class="hc-recruit-section-title">
        <div>
            <p class="hc-recruit-eyebrow">Step 4 · Get started</p>
            <h2 id="recruit-start-heading">{{ $recruitCanHire ? 'Choose a caregiver to get started' : ($requestDatePassed ? 'Choose a new date before hiring' : $recruitUnavailableTitle) }}</h2>
            <p>{{ $recruitCanHire ? 'Select an applicant to review the care, schedule and price before confirming.' : ($recruitPendingBody ?? 'Your care request and caregiver history remain available. Start a new request when you need care again.') }}</p>
        </div>
    </div>
    @if ($recruitCanHire)
        <dl class="hc-recruit-start-checks">
            <div><dt>Choose your caregiver</dt><dd>Compare applicants and chat about the help you need.</dd></div>
            <div><dt>Review the details</dt><dd>Check {{ $plainRequestType === 'Recurring care' ? 'the repeating schedule, first visit' : 'the visit date and time' }}, care rate and fees together.</dd></div>
            <div><dt>Confirm your care</dt><dd>{{ $plainRequestType === 'Recurring care' ? 'Hiring opens your recurring care home, where you manage individual visits.' : 'Hiring opens your booked visit, with everything you need for the shift.' }}</dd></div>
        </dl>
        <div class="hc-recruit-start-actions">
            <button type="button" wire:click="setActiveTab('{{ $openCaregiverResponses ? 'applicants' : 'invite' }}')" class="hc-primary-button">{{ $openCaregiverResponses ? 'Choose from applicants' : 'Find caregivers' }} <span aria-hidden="true">→</span></button>
            <p class="hc-recruit-caption">{{ $openCaregiverResponses ? 'No one is hired until you confirm your chosen caregiver.' : ($requestItem->is_private ? 'No applicants yet. Invite caregivers so they can respond to your private request.' : 'No applicants yet. Invite caregivers or come back when someone applies.') }}</p>
        </div>
    @else
        <div class="hc-recruit-start-actions"><a href="{{ route('family.requests.create', ['type' => $requestItem->request_type]) }}" wire:navigate class="hc-primary-button">Create a new request</a><button type="button" wire:click="setActiveTab('applicants')" class="hc-recruit-link">View caregiver history</button></div>
    @endif
</section>
@endif
