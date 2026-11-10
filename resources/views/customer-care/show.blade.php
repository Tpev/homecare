<x-app-layout>
    <div class="hc-page pt-5">
        @if(! $careRequest->staff_managed || $careRequest->staff_archived_at || $errors->any())
            @include('customer-care.partials.status')
        @endif
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[#D8E1D7] bg-[#F3F7F3] p-4">
            <div class="text-sm"><a class="hc-care-text-link" href="{{ route('customer-care.index') }}">Customer Success</a><span class="mx-2">/</span><a class="hc-care-text-link" href="{{ route('customer-care.customer', $careRequest->family) }}">{{ $careRequest->family->name }}</a><p class="mt-1 hc-care-meta">{{ $careRequest->staff_managed ? 'Managing for this family · Customer notifications off' : 'Customer-managed request' }}</p></div>
            @if($careRequest->staff_managed)
            <div class="flex flex-wrap items-center gap-3 text-sm">
                @if(! $careRequest->staff_archived_at && in_array($careRequest->status, ['open', 'draft']) && ! $careRequest->booking)<a class="hc-secondary-button" href="{{ route('customer-care.edit', $careRequest) }}">Edit request</a>@endif
                <a class="hc-care-text-link" href="{{ route('customer-care.show', ['careRequest' => $careRequest, 'panel' => 'messages']) }}">Messages @if($unreadMessages) ({{ $unreadMessages }})@endif</a>
                <a class="hc-care-text-link" href="{{ route('customer-care.show', ['careRequest' => $careRequest, 'panel' => 'visits']) }}">{{ $careRequest->isRecurring() || $careRequest->care_plan_id ? 'Recurring care' : 'Visit tools' }}</a>
                <a class="hc-care-text-link" href="{{ route('customer-care.show', ['careRequest' => $careRequest, 'panel' => 'activity']) }}">Internal notes</a>
                <a class="hc-care-text-link" href="{{ route('customer-care.customer', $careRequest->family) }}">Prepaid hours</a>
                @unless($careRequest->staff_archived_at)<button type="button" class="hc-care-text-link" x-data @click="$dispatch('open-modal', 'remove-managed-request')">Remove request</button>@endunless
            </div>
            @endif
        </div>
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
                <section class="hc-care-next mt-5"><div><h1 class="text-2xl font-semibold">{{ $careRequest->title }}</h1><p class="hc-care-subtitle">Archived request · Restore to manage this request. Restoring does not reopen the posting.</p></div><form method="POST" action="{{ route('customer-care.action', $careRequest) }}">@csrf<input type="hidden" name="action" value="restore"><button class="hc-secondary-button">Restore request</button></form></section>
            @endif
            @if(in_array($tab, ['messages', 'activity', 'visits'], true))
                <section class="my-5 space-y-4" aria-label="Care team tools"><a class="hc-care-text-link" href="{{ route('customer-care.show', $careRequest) }}">← Back to request</a>@include('customer-care.partials.'.$tab)</section>
            @endif
            @include('customer-care.partials.remove-request')
        @endif
    </div>
    @if($careRequest->staff_managed && ! $careRequest->staff_archived_at)
        <livewire:customer-care.manage-care-request :care-request="$careRequest->id" />
    @endif
</x-app-layout>
