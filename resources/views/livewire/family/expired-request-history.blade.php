<div class="hc-care-workspace hc-page space-y-5 py-5 sm:space-y-6 sm:py-8">
    <h1 class="sr-only">Care history</h1>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-family-care-nav active="history" />
        <a href="{{ route('family.requests.create') }}" wire:navigate class="hc-primary-button min-h-11 self-end shrink-0">Request new care</a>
    </div>
    @include('livewire.family.partials.history-sections')

    <section class="space-y-4 rounded-3xl border border-[#E4DDD3] bg-[#FFFCF8] p-4 sm:p-5" aria-labelledby="expired-requests-heading">
        <div>
            <h2 id="expired-requests-heading" class="font-display text-xl font-semibold text-[#17313F]">Expired requests</h2>
            <p class="mt-1 text-sm text-[#607080]">Unbooked requests move here 24 hours after their service end time. Your details, caregiver replies and messages are kept. No action is needed.</p>
        </div>
        <label class="block text-sm font-medium text-[#324457]" for="expired-request-search">Search history</label>
        <input id="expired-request-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Request #, recipient, or title" class="h-11 w-full rounded-xl border-[#DED6CA] bg-white px-3 text-sm">
        <p class="text-sm text-[#607080]">{{ $requests->total() }} expired {{ Str::plural('request', $requests->total()) }}</p>
        <div class="grid gap-3 md:grid-cols-2">
            @forelse ($requests as $request)
                <article wire:key="expired-request-{{ $request->id }}" class="min-w-0 rounded-2xl border border-[#E4DDD3] bg-white p-4">
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Expired</span>
                    <h3 class="mt-3 break-words font-semibold text-[#17313F]">{{ $request->title ?: 'Care request' }}</h3>
                    <p class="mt-1 text-sm text-[#607080]">{{ $request->recipient?->full_name ?: 'Care recipient' }} · Request #{{ $request->id }}</p>
                    <p class="mt-3 text-sm font-medium text-[#17313F]">{{ $request->requested_start_at?->format('D, M j, Y · g:i A T') ?: 'Date not set' }}</p>
                    @if ($request->requested_end_at)<p class="text-sm text-[#607080]">Ended {{ $request->requested_end_at->format('D, M j, Y · g:i A T') }}</p>@endif
                    <p class="mt-2 text-sm text-[#607080]">{{ $request->applications_count }} caregiver {{ Str::plural('reply', $request->applications_count) }} saved</p>
                    <a href="{{ route('family.requests.show', $request) }}" wire:navigate class="hc-secondary-button mt-4 min-h-11">View request history</a>
                </article>
            @empty
                <p class="py-6 text-sm text-[#607080] md:col-span-2">No expired requests{{ trim($search) !== '' ? ' match your search' : ' yet' }}.</p>
            @endforelse
        </div>
        {{ $requests->links() }}
    </section>
</div>
