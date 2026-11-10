<x-app-layout>
    <div class="hc-page hc-care-workspace space-y-5 pb-12 pt-5 sm:space-y-6 sm:pt-8">
        @include('customer-care.partials.status')
        @php
            $editable = $careRequest->staff_managed && ! $careRequest->staff_archived_at && in_array($careRequest->status, ['open', 'draft']) && ! $careRequest->booking;
            $schedule = $careRequest->isRecurring() ? $careRequest->recurringScheduleLabel() : ($careRequest->requested_start_at?->format('M j, Y · g:i A').' – '.$careRequest->requested_end_at?->format('g:i A'));
            $tabs = ['overview' => 'Overview', 'caregivers' => 'Caregivers', 'visits' => 'Visits', 'messages' => 'Messages', 'activity' => 'Activity'];
        @endphp
        <header class="hc-care-heading">
            <nav aria-label="Care location" class="hc-care-breadcrumb">
                <a href="{{ route('customer-care.index') }}">Customer Success</a><span aria-hidden="true">/</span>
                <a href="{{ route('customer-care.customer', $careRequest->family) }}">{{ $careRequest->family->name }}</a><span aria-hidden="true">/</span>
                <span>Request #{{ $careRequest->id }}</span>
            </nav>
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div class="min-w-0">
                    <p class="hc-care-eyebrow">{{ $careRequest->isRecurring() ? 'Recurring care' : 'One visit' }} · {{ $careRequest->family->name }}</p>
                    <h1 class="hc-care-title">{{ $careRequest->title }}</h1>
                    <p class="hc-care-subtitle">{{ $schedule }} · {{ $careRequest->city }}, {{ $careRequest->state }}</p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="hc-care-status">{{ $careRequest->staff_archived_at ? 'Archived' : ($careRequest->status === 'open' ? 'Finding a caregiver' : ucfirst($careRequest->status)) }}</span>
                        <span class="hc-care-meta">{{ $careRequest->staff_managed ? 'Managed by your team · Customer notifications off' : 'Customer-managed request' }}</span>
                    </div>
                </div>
                @if($careRequest->staff_managed && ! $careRequest->staff_archived_at)
                    <div class="flex shrink-0 flex-wrap gap-2">
                        @if($editable)<a class="hc-secondary-button" href="{{ route('customer-care.edit', $careRequest) }}">Edit request</a>@endif
                        <button type="button" class="hc-care-text-link px-3" x-data @click="$dispatch('open-modal', 'remove-managed-request')">Remove request</button>
                    </div>
                @endif
            </div>
        </header>

        @if(! $careRequest->staff_managed)
            <section class="hc-surface p-5 sm:p-6">
                <h2 class="text-xl font-semibold">Manage this request for the customer</h2>
                <p class="hc-care-subtitle">Your team will handle care and follow-up. Taking over turns off future customer notifications for this request and its visits. Notifications already sent cannot be recalled.</p>
                <form class="mt-5 space-y-4" method="POST" action="{{ route('customer-care.action', $careRequest) }}">
                    @csrf<input type="hidden" name="action" value="take_over">
                    <label class="block text-sm font-semibold">Reason<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="reason" required minlength="5" maxlength="2000" value="{{ old('reason') }}"></label>
                    <button class="hc-primary-button">Start managing silently</button>
                </form>
            </section>
        @else
            @if($careRequest->staff_archived_at)
                <section class="hc-care-next">
                    <div class="hc-care-next-copy"><p class="hc-care-eyebrow">Archived request</p><h2 class="mb-4 text-xl font-semibold">History is safely kept</h2><p>This request has been removed from active lists. Restoring it keeps its current status and does not reopen the posting or notify the customer.</p></div>
                    <form method="POST" action="{{ route('customer-care.action', $careRequest) }}">@csrf<input type="hidden" name="action" value="restore"><button class="hc-secondary-button">Restore request</button></form>
                </section>
            @elseif($tab === 'overview' && in_array($careRequest->status, ['draft', 'open', 'filled']))
                <section class="hc-care-next">
                    <div class="hc-care-next-copy">
                        <p class="hc-care-eyebrow">Next step</p>
                        <h2 class="mb-4 text-xl font-semibold">{{ match($careRequest->status) { 'draft' => 'Ready to find a caregiver?', 'open' => 'Find the right caregiver', default => 'Keep care on track' } }}</h2>
                        <p>{{ match($careRequest->status) { 'draft' => 'Review the care details, then post the request on behalf of this family.', 'open' => 'Invite caregivers, review applicants, or record a placement you have already agreed.', default => 'Manage upcoming visits, review actual hours, and follow up with the caregiver.' } }}</p>
                    </div>
                    <div class="shrink-0"><a class="hc-primary-button" href="{{ $careRequest->status === 'draft' ? route('customer-care.edit', $careRequest) : route('customer-care.show', ['careRequest' => $careRequest, 'tab' => $careRequest->status === 'open' ? 'caregivers' : 'visits']) }}">{{ match($careRequest->status) { 'draft' => 'Review and post', 'open' => 'Find caregivers', default => 'Manage visits' } }}</a></div>
                </section>
            @endif

            <nav class="hc-care-tabs" aria-label="Care navigation" style="--care-tab-count: 5">
                @foreach($tabs as $key => $label)
                    <a class="hc-care-tab flex items-center justify-center gap-1 text-center" href="{{ route('customer-care.show', ['careRequest' => $careRequest, 'tab' => $key]) }}" @if($tab === $key) aria-current="page" @endif>
                        {{ $label }}@if($key === 'messages' && $unreadMessages && $tab !== 'messages')<span class="rounded-full bg-[#F8F0E2] px-1.5 text-xs text-[#23483F]" aria-label="{{ $unreadMessages }} unread conversations">{{ $unreadMessages }}</span>@endif
                    </a>
                @endforeach
            </nav>
            @include('customer-care.partials.'.$tab)

            @if(! $careRequest->staff_archived_at)
                <dialog x-data x-init="if (@js($errors->any() && old('action') === 'archive')) $el.showModal()" @open-modal.window="if ($event.detail === 'remove-managed-request') $el.showModal()" @close-modal.window="if ($event.detail === 'remove-managed-request') $el.close()" class="m-auto max-h-[90dvh] w-[calc(100%_-_2rem)] max-w-lg overflow-y-auto rounded-3xl border border-[#E3D6C5] bg-[#FFFDFA] p-0 text-[#24302D] shadow-xl backdrop:bg-black/40" aria-labelledby="remove-request-title" aria-describedby="remove-request-description">
                    <form method="POST" action="{{ route('customer-care.action', $careRequest) }}" class="space-y-5 p-6">
                        @csrf<input type="hidden" name="action" value="archive">
                        <div><p class="hc-care-eyebrow">Request #{{ $careRequest->id }}</p><h2 id="remove-request-title" class="mt-2 text-2xl font-semibold">Remove this request?</h2></div>
                        <p id="remove-request-description" class="hc-care-subtitle">This closes an open posting and removes the request from active staff lists. Caregivers with active applications are informed that the posting has closed. The customer is not notified.</p>
                        <p class="hc-care-meta">Visit, payment, and conversation history stays available in Archived requests. Finish or cancel active visits and end recurring care first.</p>
                        @if(old('action') === 'archive' && $errors->any())<div role="alert" class="rounded-xl bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif
                        <label class="block text-sm font-semibold">Reason for removal<textarea class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="reason" required minlength="5" maxlength="2000" rows="3">{{ old('action') === 'archive' ? old('reason') : '' }}</textarea></label>
                        <div class="flex flex-wrap justify-end gap-3"><button type="button" class="hc-secondary-button" @click="$dispatch('close-modal', 'remove-managed-request')">Keep request</button><button class="hc-primary-button">Remove request</button></div>
                    </form>
                </dialog>
            @endif
        @endif
    </div>
</x-app-layout>
