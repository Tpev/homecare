<x-app-layout>
    <div class="hc-page hc-care-workspace space-y-6 py-8">
        @include('customer-care.partials.status')
        <header class="hc-care-heading flex flex-wrap items-start justify-between gap-4">
            <div><p class="hc-care-eyebrow">Your care team workspace</p><h1 class="hc-care-title">Customer Success</h1><p class="hc-care-subtitle">Find a family, arrange care, and keep every visit on track.</p></div>
            <a class="hc-secondary-button" href="#customers">Find a family</a>
        </header>
        <form method="GET" class="flex gap-3">
            @if($archived)<input type="hidden" name="view" value="archived">@endif
            <div class="flex-1"><label for="customer-search" class="text-sm font-semibold sr-only">Find a customer or request</label><input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" id="customer-search" name="search" value="{{ $search }}" placeholder="Search family name, email or request"></div><button class="hc-primary-button">Search</button>
        </form>
        @if($inbox->isNotEmpty() || $attention->isNotEmpty())
            <section class="hc-care-next block">
                <div class="w-full"><p class="hc-care-eyebrow">Needs your attention</p><h2 class="mb-4 text-xl font-semibold">Pick up where care needs you</h2>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        @foreach($inbox as $thread)
                            <a class="hc-care-visit-row" href="{{ route('customer-care.show', ['careRequest' => $thread->care_request_id, 'tab' => 'messages']) }}"><div><p class="text-sm font-semibold">{{ $thread->caregiver?->name }} replied</p><p class="hc-care-meta">{{ $thread->careRequest->family?->name }} · {{ $thread->careRequest->title }}</p></div><span class="hc-care-status">Read reply</span></a>
                        @endforeach
                        @foreach($attention as $visit)
                            <a class="hc-care-visit-row" href="{{ route('customer-care.show', ['careRequest' => $visit->careRequest, 'tab' => 'visits']) }}#visit-{{ $visit->id }}"><div><p class="text-sm font-semibold">{{ $visit->careRequest->family?->name }} · Visit #{{ $visit->id }}</p><p class="hc-care-meta">{{ $visit->scheduled_start_at?->format('M j, g:i A') }} · {{ $visit->dispute_status === 'open' ? 'Open dispute' : (($visit->careHourFunding?->status === 'payment_attention' || $visit->careHourSettlement?->card_minutes > 0) ? 'Add prepaid coverage' : ($visit->change_requests_count ? 'Review schedule change' : ($visit->time_corrections_count ? 'Review time correction' : 'Review completed hours'))) }}</p></div><span class="hc-care-status">Review visit</span></a>
                        @endforeach
                    </div>
                </div>
            </section>
        @else<p class="hc-care-meta">No unread caregiver replies. Visit reviews will appear here when they need attention.</p>@endif
        <div class="grid items-start gap-6 lg:grid-cols-3">
            <section class="hc-surface p-5 sm:p-6 lg:col-span-2">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3"><h2 class="text-2xl font-semibold">{{ $archived ? 'Archived requests' : 'Managed requests' }}</h2><a class="hc-care-text-link" href="{{ route('customer-care.index', array_filter(['view' => $archived ? null : 'archived', 'search' => $search])) }}">{{ $archived ? 'View active requests' : 'Archived requests' }}</a></div>
                <div class="space-y-3">
                    @forelse($requests as $item)
                        <a class="hc-care-visit-row group" href="{{ route('customer-care.show', $item) }}">
                            <div class="min-w-0"><p class="hc-care-eyebrow">{{ $item->family?->name }}</p><h3 class="mt-1 text-lg font-semibold group-hover:underline">{{ $item->title }}</h3><p class="hc-care-meta mt-2">{{ $item->isRecurring() ? 'Recurring care' : $item->requested_start_at?->format('M j, Y · g:i A') }} · {{ $item->applications_count }} applicants</p></div>
                            <span class="hc-care-status">{{ $archived ? 'Archived' : ucfirst(str_replace('_', ' ', $item->booking?->status ?? $item->status)) }}</span>
                        </a>
                    @empty<div class="hc-care-soft p-8 text-center"><h3 class="text-lg font-semibold">{{ $archived ? 'No archived requests' : 'No requests found' }}</h3><p class="hc-care-subtitle">{{ $archived ? 'Removed requests stay here with their history.' : 'Choose a family to create their first request, or try a different search.' }}</p></div>@endforelse
                </div>
                <div class="mt-5">{{ $requests->links() }}</div>
            </section>
            <section id="customers" class="hc-surface p-5 sm:p-6">
                <h2 class="text-2xl font-semibold">Families</h2><p class="hc-care-subtitle">Open an account to arrange care or add prepaid hours.</p>
                <div class="mt-5 space-y-3">
                    @forelse($customers as $customer)<a class="block rounded-2xl border border-[#E3D6C5] p-4 transition hover:bg-[#F8F0E2]" href="{{ route('customer-care.customer', $customer) }}"><p class="text-sm font-semibold">{{ $customer->name }}</p><p class="hc-care-meta mt-1 break-all">{{ $customer->email }}</p></a>@empty<p class="hc-care-subtitle">No matching families.</p>@endforelse
                </div>
                <div class="mt-5">{{ $customers->links() }}</div>
                <details class="mt-5 border-t border-[#E3D6C5] pt-3"><summary class="cursor-pointer text-sm font-semibold">Create a family account</summary>
                    <form method="POST" action="{{ route('customer-care.customers.store') }}" class="mt-4 space-y-4">@csrf
                        <label class="block text-sm font-semibold">Name<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="name" required maxlength="255" value="{{ old('name') }}"></label><label class="block text-sm font-semibold">Email<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="email" type="email" required maxlength="255" value="{{ old('email') }}"></label><label class="block text-sm font-semibold">Phone (optional)<input class="mt-2 block w-full rounded-xl border px-3 py-2 text-sm font-normal focus:border-[#23483F] focus:ring-[#23483F]" name="phone" type="tel" maxlength="50" value="{{ old('phone') }}"></label>
                        <p class="hc-care-meta">Creates the account without sending an invitation or access email.</p><button class="hc-primary-button">Create customer account</button>
                    </form>
                </details>
            </section>
        </div>
    </div>
</x-app-layout>
