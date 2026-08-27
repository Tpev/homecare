<div class="hc-page space-y-5 py-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div><p class="text-sm font-semibold uppercase tracking-wide text-[#0F6B5B]">Care operations</p><h1 class="mt-1 font-display text-3xl font-semibold text-[#17313F]">Recurring care</h1><p class="mt-1 text-base text-[#526474]">Agreements, upcoming visits, and payment readiness.</p></div>
        <div class="grid gap-3 sm:grid-cols-2">
            <input type="search" wire:model.blur="search" class="min-h-12 rounded-md border-[#BFC8CE] text-base" placeholder="Search family or caregiver">
            <select wire:model.live="status" class="min-h-12 rounded-md border-[#BFC8CE] text-base"><option value="live">Live plans</option><option value="all">All plans</option><option value="active">Active</option><option value="paused">Paused</option><option value="ended">Ended</option><option value="pending_caregiver">Pending caregiver</option></select>
        </div>
    </header>

    <section class="overflow-hidden rounded-lg border border-[#D8D0C5] bg-white">
        <div class="hidden grid-cols-[80px_1.2fr_1fr_1fr_1fr_auto] gap-3 border-b border-[#E7E0D8] bg-[#F5F2ED] px-5 py-3 text-sm font-semibold text-[#526474] lg:grid"><span>Plan</span><span>Family</span><span>Caregiver</span><span>Status</span><span>Next visit</span><span></span></div>
        <div class="divide-y divide-[#E7E0D8]">
            @forelse($plans as $plan)
                <article class="grid gap-3 px-5 py-4 text-base lg:grid-cols-[80px_1.2fr_1fr_1fr_1fr_auto] lg:items-center">
                    <span class="font-semibold text-[#17313F]">#{{ $plan->id }}</span>
                    <div><p class="font-semibold text-[#17313F]">{{ $plan->family?->name }}</p><p class="text-sm text-[#526474]">{{ $plan->family?->email }}</p></div>
                    <div><p class="font-semibold text-[#17313F]">{{ $plan->caregiver?->name }}</p><p class="text-sm text-[#526474]">{{ $plan->recipientName() }}</p></div>
                    <span class="font-semibold {{ $plan->status === 'active' ? 'text-emerald-700' : ($plan->status === 'paused' ? 'text-amber-800' : 'text-[#526474]') }}">{{ ucfirst(str_replace('_', ' ', $plan->status)) }}</span>
                    <div><p>{{ $plan->nextBooking?->scheduled_start_at?->format('M j, g:i A') ?: 'None' }}</p><p class="text-sm text-[#526474]">Payment: {{ ucfirst(str_replace('_', ' ', $plan->nextBooking?->payment?->status ?? 'not due')) }}</p></div>
                    <a href="{{ route('admin.care-plans.show', $plan) }}" wire:navigate class="hc-secondary-button min-h-11">Open</a>
                </article>
            @empty
                <p class="px-5 py-10 text-center text-base text-[#526474]">No recurring care plans matched.</p>
            @endforelse
        </div>
    </section>
    {{ $plans->links() }}
</div>
