<x-app-layout><div class="hc-page max-w-3xl space-y-6 py-8">
    <a href="{{ route('family.care-hours.show', $account) }}" wire:navigate class="hc-care-text-link">← Care hours</a>
    <header><h1 class="font-display text-3xl font-semibold text-[#23483F]">{{ $eligibilityReason ? 'Care hours' : 'Buy hours' }} for {{ $account->display_name }}</h1>@unless($eligibilityReason)<p class="mt-2 text-[#485E53]">$31 per paid hour, including the hourly platform fee.</p>@endunless</header>
    @include('care-hours.partials.messages')
    @if($eligibilityReason)
        <section class="hc-surface space-y-3 p-5"><p>{{ $eligibilityReason }}</p><a href="{{ route('support.index') }}" class="hc-secondary-button">Contact support</a></section>
    @else
        @include('care-hours.partials.packages', ['checkoutUrl' => route('family.care-hours.checkout', $account)])
    @endif
</div></x-app-layout>
