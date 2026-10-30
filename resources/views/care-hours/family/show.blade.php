<x-app-layout>
    <div class="hc-page max-w-3xl space-y-6 py-8">
        <a href="{{ route('family.care-hours.index') }}" wire:navigate class="hc-care-text-link">← Prepaid care hours</a>
        <header><h1 class="font-display text-3xl font-semibold text-[#23483F]">Prepaid care hours</h1><p class="mt-2 text-[#485E53]">Your family balance</p></header>
        @include('care-hours.partials.messages')
        @include('care-hours.partials.gift-links')
        @if($eligibilityReason)<p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-950">{{ $eligibilityReason }} <a href="{{ route('support.index') }}" class="underline">Contact support</a>.</p>@endif
        <section class="hc-surface space-y-4 p-6" aria-label="Care hours balance">
            <div><p class="text-sm text-[#485E53]">Available credit</p><p class="mt-1 font-display text-4xl font-semibold text-[#23483F]">{{ \App\Models\CareHourAccount::formatMinutes($balance['available_minutes']) }}</p></div>
            @if ($balance['reserved_minutes'] > 0)<p class="text-sm text-[#485E53]">{{ \App\Models\CareHourAccount::formatMinutes($balance['reserved_minutes']) }} reserved for upcoming visits or hours awaiting approval.</p>@endif
            @if ($balance['held_minutes'] > 0)<p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-950">{{ \App\Models\CareHourAccount::formatMinutes($balance['held_minutes']) }} temporarily on hold. <a href="{{ route('support.index') }}" class="underline">Contact support</a>.</p>@endif
            <div class="flex flex-wrap gap-3">
                <a href="{{ \App\Support\CareHourRequestHandoff::url($account, auth()->user()) }}" wire:navigate class="hc-secondary-button">Find care</a>
                @if(! $eligibilityReason && app(\App\Services\CareHours\CareHourAccess::class)->canBuy(auth()->user()) && $account->active)<a href="{{ route('family.care-hours.packages', $account) }}" wire:navigate class="hc-primary-button">Buy prepaid care</a>@endif
            </div>
            <p class="text-sm text-[#485E53]">Use these hours for anyone in your family account. Available hours are selected first for eligible care.</p>
        </section>
        @if ($isOwner && (app(\App\Services\CareHours\CareHourAccess::class)->canContribute(auth()->user()) || $links->whereNull('revoked_at')->isNotEmpty()))
            <details id="contributions" class="hc-surface p-5" @if(session('contribution_url')) open @endif>
                <summary class="cursor-pointer font-semibold">{{ app(\App\Services\CareHours\CareHourAccess::class)->canContribute(auth()->user()) ? 'Let someone contribute' : 'Manage contribution links' }}</summary>
                @if(app(\App\Services\CareHours\CareHourAccess::class)->canContribute(auth()->user()))<p class="mt-3 text-sm text-[#485E53]">Your private link lets someone add hours to your family balance. Contributors cannot see your balance or care details.</p>@endif
                @if(session('contribution_url') && app(\App\Services\CareHours\CareHourAccess::class)->canContribute(auth()->user()))
                    <label for="contribution-url" class="mt-4 block text-sm font-semibold">Your private link</label>
                    <input id="contribution-url" readonly value="{{ session('contribution_url') }}" class="mt-2 min-h-12 w-full rounded-xl border-[#D8E1D7]" onclick="this.select()" />
                    <button type="button" x-data="{ copied: false }" x-on:click="navigator.clipboard.writeText(@js(session('contribution_url'))).then(() => copied = true)" x-text="copied ? 'Copied' : 'Copy link'" class="hc-secondary-button mt-3">Copy link</button>
                @endif
                @if(! $eligibilityReason && app(\App\Services\CareHours\CareHourAccess::class)->canContribute(auth()->user()) && $account->active)
                    <form action="{{ route('family.care-hours.link.create', $account) }}" method="POST" class="mt-4">@csrf<button type="submit" class="hc-secondary-button">Create private link</button></form>
                @endif
                @foreach($links->whereNull('revoked_at') as $link)
                    <form action="{{ route('family.care-hours.link.revoke', [$account, $link]) }}" method="POST" class="mt-4 flex items-center justify-between gap-4 border-t border-[#D8E1D7] pt-3">@csrf @method('DELETE')<span class="text-sm">Link created {{ $link->created_at->format('M j, Y') }}</span><button class="text-sm font-semibold text-rose-800 underline" type="submit">Disable link</button></form>
                @endforeach
            </details>
        @endif
        <section class="hc-surface p-5"><h2 class="font-display text-xl font-semibold">Contributions and usage</h2>
            <div class="mt-3 divide-y divide-[#D8E1D7]">
                @forelse ($history as $entry)
                    <div class="flex flex-col items-start justify-between gap-2 py-4 pr-14 sm:flex-row sm:gap-4 sm:pr-0"><div><p class="font-medium">{{ $entry->description ?: ucfirst(str_replace('_', ' ', $entry->type)) }}</p><p class="mt-1 text-sm text-[#485E53]">{{ $entry->created_at->format('M j, Y') }}@if($entry->care_booking_id) · Visit #{{ $entry->care_booking_id }}@endif</p></div><span class="shrink-0 text-sm font-semibold">{{ \App\Models\CareHourAccount::formatMinutes(abs($entry->minutes)) }}</span></div>
                @empty<p class="py-4 text-sm text-[#485E53]">Purchases and care-hour usage will appear here.</p>@endforelse
            </div>{{ $history->links() }}
        </section>
        @if($purchases->isNotEmpty())<details class="hc-surface p-5"><summary class="cursor-pointer font-semibold">Purchases</summary><div class="mt-3 divide-y divide-[#D8E1D7]">@foreach($purchases as $purchase)<div class="flex justify-between gap-4 py-3 text-sm"><span>{{ $purchase->created_at->format('M j, Y') }} · {{ \App\Models\CareHourAccount::formatMinutes($purchase->total_minutes) }} · ${{ number_format($purchase->amount_cents / 100, 2) }}</span>@if((int) $purchase->buyer_user_id === (int) auth()->id())<a class="underline" href="{{ route('family.care-hours.purchase', $purchase->public_id) }}">Receipt</a>@else<span>Contribution</span>@endif</div>@endforeach</div></details>@endif
    </div>
</x-app-layout>
