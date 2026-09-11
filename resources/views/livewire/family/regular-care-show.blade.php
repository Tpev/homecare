<div class="hc-page hc-care-workspace space-y-6 pb-10 pt-6 sm:pt-8" data-inline-support>
    @php
        $dayOptions = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
        $address = $plan->address_snapshot ?? [];
        $tasks = collect($plan->task_snapshot ?? []);
        $isPaused = $plan->status === \App\Models\CarePlan::STATUS_PAUSED;
        $isEnded = in_array($plan->status, [\App\Models\CarePlan::STATUS_ENDED, \App\Models\CarePlan::STATUS_CANCELLED], true);
        $isPending = in_array($plan->status, [\App\Models\CarePlan::STATUS_PENDING_CAREGIVER, \App\Models\CarePlan::STATUS_COUNTERED, \App\Models\CarePlan::STATUS_DRAFT], true);
        $canManage = $plan->isLive();
        $caregiverProfile = $plan->caregiver?->caregiverProfile;
        $source = $plan->sourceCareRequest;
        $profileUrl = $caregiverProfile?->slug ? route('caregivers.show', ['slug' => $caregiverProfile->slug, 'careRequest' => $source?->id]) : null;
        $profileName = data_get($careProfileSnapshot, 'preferred_name') ?: $plan->recipientName();
        $reportAttention = $completedExtraVisits->whereIn('status', \App\Models\CompletedExtraVisitRequest::unresolvedStatuses());
        $attentionCount = $attentionVisits->count() + $reportAttention->count() + $plan->pendingScheduleChanges->count();
        $timezone = $plan->timezone ?: config('app.timezone');
    @endphp
    @if (session('status'))<x-alert color="green">{{ session('status') }}</x-alert>@endif
    <x-input-error :messages="$errors->get('plan')" role="alert" />
    <x-input-error :messages="$errors->get('visit')" role="alert" />
    <header data-ai-target="family.regular_care.attention" tabindex="-1" class="space-y-4">
        <a href="{{ route('family.care.index') }}" wire:navigate class="inline-flex min-h-11 items-center font-semibold text-[#23483F] underline underline-offset-4">← Back to your care</a>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div><p class="text-sm text-[#485E53]">Recurring care · Care #{{ $plan->id }}</p><h1 class="mt-1 font-display text-3xl font-semibold leading-tight text-[#23483F] sm:text-4xl">Recurring care for {{ $plan->recipientName() }}</h1><p class="mt-2 text-base text-[#485E53]">{{ $plan->caregiver?->name ?: 'Caregiver not selected' }} · {{ $scheduleLabel }}</p><span class="mt-3 inline-flex rounded-full px-3 py-1.5 text-sm font-semibold {{ ($isEnded || in_array($plan->status, [\App\Models\CarePlan::STATUS_DECLINED, \App\Models\CarePlan::STATUS_EXPIRED], true)) ? 'bg-slate-100 text-slate-800' : ($isPaused || $isPending || $plan->status === \App\Models\CarePlan::STATUS_PAYMENT_ATTENTION ? 'bg-amber-50 text-amber-950' : 'bg-[#E8F1E9] text-[#23483F]') }}">{{ $planStateLabel }}</span></div>
            <div class="flex flex-wrap gap-2">@if ($messageUrl)<a href="{{ $messageUrl }}" wire:navigate class="hc-secondary-button">Message caregiver</a>@elseif ($source)<a href="{{ route('family.requests.show', ['careRequest' => $source->id, 'tab' => 'applicants']) }}" wire:navigate class="hc-secondary-button">Caregiver & conversations</a>@endif<button type="button" wire:click="$toggle('showHelp')" aria-expanded="{{ $showHelp ? 'true' : 'false' }}" aria-controls="plan-help" class="hc-secondary-button">Get help</button></div>
        </div>
    </header>
    <nav class="hc-care-tabs grid grid-cols-4 gap-1 rounded-2xl border border-[#E3D6C5] bg-[#FFFDFA] p-1" aria-label="Recurring care navigation">
        @foreach (['overview' => 'Overview', 'caregivers' => 'Caregivers', 'visits' => 'Visits', 'details' => 'Care details'] as $key => $label)<button type="button" wire:click="setActiveTab('{{ $key }}')" class="hc-care-tab min-h-12 rounded-xl px-2 py-3 text-sm font-semibold {{ $activeTab === $key ? 'bg-[#23483F] text-[#FFF7EA]' : 'text-[#23483F] hover:bg-[#F5ECDE]' }}" @if($activeTab === $key) aria-current="page" @endif>{{ $label }}</button>@endforeach
    </nav>
    @if ($showHelp)<section id="plan-help" class="hc-surface space-y-4 p-5 sm:p-6"><h2 class="font-display text-2xl font-semibold">Help with your recurring care</h2><p class="text-[#485E53]">For hours, payment, a change or a care concern, choose the affected visit. Its support history stays with that date.</p><div class="flex flex-wrap gap-3"><button type="button" wire:click="setVisitFilter('all')" class="hc-primary-button">Choose a visit</button><button type="button" x-data x-on:click="$dispatch('open-care-support-chat')" class="hc-secondary-button">Chat with support</button><a href="{{ route('support.index') }}" wire:navigate class="hc-secondary-button">Contact LoLo Support</a><button type="button" wire:click="$set('showHelp', false)" class="hc-secondary-button">Close help</button></div></section>@endif
    @if ($attentionCount && $activeTab !== 'overview')<div class="flex flex-col gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-4 sm:flex-row sm:items-center sm:justify-between" role="status"><p class="font-semibold text-amber-950">{{ $attentionCount }} {{ Str::plural('item', $attentionCount) }} to follow up across this care.</p><button type="button" wire:click="setActiveTab('overview')" class="hc-secondary-button">View care updates</button></div>@endif
    @include('livewire.family.partials.regular-care-'.$activeTab)
    <div class="flex flex-wrap gap-x-5 gap-y-2 border-t border-[#E3D6C5] pt-4 text-sm"><a href="{{ route('family.care.journey', ['resourceType' => 'regular', 'resourceId' => $plan->id]) }}" wire:navigate class="inline-flex min-h-11 items-center font-semibold text-[#23483F] underline underline-offset-4">Care timeline</a><a href="{{ route('family.care.history', ['plan' => $plan->id]) }}" wire:navigate class="inline-flex min-h-11 items-center font-semibold text-[#23483F] underline underline-offset-4">All past visits & payments</a></div>
</div>
