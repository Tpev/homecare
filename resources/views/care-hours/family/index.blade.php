<x-app-layout>
    @php
        $buyFlow = $buyFlow ?? false;
        $access = app(\App\Services\CareHours\CareHourAccess::class);
        $icons = [
            'arrow' => '<path d="M5 12h14m-5-5 5 5-5 5"/>',
            'back' => '<path d="M19 12H5m5-5-5 5 5 5"/>',
            'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3 2"/>',
            'gift' => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v8h14v-8M12 8v12"/><path d="M12 8H8.5a3 3 0 1 1 3-3L12 8Zm0 0h3.5a3 3 0 1 0-3-3L12 8Z"/>',
            'code' => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m9 10-2 2 2 2m6-4 2 2-2 2m-3-5-2 8"/>',
            'check' => '<path d="m5 12 4 4L19 6"/>',
            'person' => '<circle cx="12" cy="8" r="3"/><path d="M5 20v-2a7 7 0 0 1 14 0v2"/>',
            'activity' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        ];
    @endphp
    <div class="hc-prepaid-page">
        <a href="{{ $buyFlow ? route('family.care-hours.index') : route('family.requests.index') }}" wire:navigate class="hc-prepaid-back">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">{!! $icons['back'] !!}</svg>
            {{ $buyFlow ? 'Prepaid care hours' : 'Back to Care' }}
        </a>

        @if($buyFlow)
            <header class="hc-prepaid-heading">
                <h1>Buy prepaid care</h1>
                <p>{{ $purchaseTargets->count() > 1 ? 'Choose a care balance to continue.' : 'A little care, ready for later.' }}</p>
            </header>
            @include('care-hours.partials.messages')
            <div class="hc-prepaid-targets">
                @forelse($purchaseTargets as $target)
                    <form action="{{ route('family.care-hours.buy.start') }}" method="POST" class="hc-prepaid-target">
                        @csrf
                        <input type="hidden" name="target" value="{{ $target['key'] }}">
                        <div class="hc-prepaid-target-heading">
                            <span class="hc-prepaid-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">{!! $icons['person'] !!}</svg></span>
                            <div><h2>{{ $target['name'] }}</h2><p>{{ $target['detail'] ?? ($target['type'] === 'account' ? 'Your saved care balance' : 'Set up prepaid care') }}</p></div>
                        </div>
                        @if(! empty($target['review_url']))
                            <a href="{{ $target['review_url'] }}" wire:navigate class="hc-care-text-link text-sm">Review care profile <span aria-hidden="true">↗</span></a>
                        @endif
                        @if($target['requires_balance_choice'])
                            <label for="balance-{{ $loop->index }}" class="hc-prepaid-field-label">Use an existing balance or start a separate one</label>
                            <select id="balance-{{ $loop->index }}" name="balance_target" required class="hc-prepaid-select">
                                <option value="">Choose a balance</option>
                                @foreach($accounts->where('active', true)->where('currency', 'usd') as $existing)
                                    <option value="{{ $existing->id }}">{{ $existing->display_name }}’s existing balance</option>
                                @endforeach
                                <option value="new">A different person — new balance</option>
                            </select>
                            <p class="hc-prepaid-note">Keep the same person’s hours together.</p>
                        @endif
                        <button type="submit" class="hc-prepaid-button hc-prepaid-button-green">Continue to packages <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">{!! $icons['arrow'] !!}</svg></button>
                    </form>
                @empty
                    <section class="hc-prepaid-target hc-prepaid-empty-target">
                        <span class="hc-prepaid-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">{!! $icons['person'] !!}</svg></span>
                        @if($accounts->isEmpty() && $access->canEnroll(auth()->user()))
                            <h2>Add a care profile to get started</h2>
                            <p>Your prepaid hours will stay with that person.</p>
                            <a href="{{ $profileCreateUrl }}" wire:navigate class="hc-prepaid-button hc-prepaid-button-green">Add a care profile <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">{!! $icons['arrow'] !!}</svg></a>
                        @else
                            <h2>No balance is available for new purchases</h2>
                            <p>Contact us for help with your prepaid care.</p>
                            <a href="{{ route('support.index') }}" wire:navigate class="hc-prepaid-button hc-prepaid-button-green">Contact support</a>
                        @endif
                    </section>
                @endforelse
            </div>
        @else
            <header class="hc-prepaid-heading">
                <h1>Prepaid care hours</h1>
                <p>Buy care now. Use it when you need it.</p>
            </header>
            @include('care-hours.partials.messages')

            <section class="hc-prepaid-balance-card" aria-label="Prepaid care balance">
                <div class="hc-prepaid-credit">
                    <div class="hc-prepaid-credit-top">
                        <p class="hc-prepaid-eyebrow">Available credit</p>
                        <svg class="hc-prepaid-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">{!! $icons['clock'] !!}</svg>
                    </div>
                    <p class="hc-prepaid-credit-value" data-testid="prepaid-available-credit">{{ \App\Models\CareHourAccount::formatMinutes($availableMinutes) }}</p>
                    <p class="hc-prepaid-credit-caption">{{ $availableMinutes > 0 ? 'Ready to use for eligible care.' : 'Your next care visit starts here.' }}</p>
                    @if($reservedMinutes > 0 || $heldMinutes > 0)
                        <div class="hc-prepaid-credit-meta">
                            @if($reservedMinutes > 0)<span>{{ \App\Models\CareHourAccount::formatMinutes($reservedMinutes) }} reserved</span>@endif
                            @if($heldMinutes > 0)<span>{{ \App\Models\CareHourAccount::formatMinutes($heldMinutes) }} on hold</span>@endif
                        </div>
                    @endif
                    @if($access->canBuy(auth()->user()))
                        <form action="{{ route('family.care-hours.buy.start') }}" method="POST" class="hc-prepaid-buy-action">
                            @csrf
                            <button type="submit" class="hc-prepaid-button hc-prepaid-button-white">Buy prepaid care <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">{!! $icons['arrow'] !!}</svg></button>
                        </form>
                    @else
                        <p class="hc-prepaid-paused">New purchases are currently unavailable.</p>
                    @endif
                </div>
                <div class="hc-prepaid-explainer">
                    <p class="hc-prepaid-eyebrow">Care at your pace</p>
                    <h2>A little planning.<br> Care when you need it.</h2>
                    <ul>
                        <li><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">{!! $icons['check'] !!}</svg></span>Your hours never expire</li>
                        <li><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">{!! $icons['check'] !!}</svg></span>All hourly platform fees included</li>
                        <li><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">{!! $icons['check'] !!}</svg></span>Applied automatically when you book eligible care</li>
                    </ul>
                </div>
            </section>

            @if($access->allows(auth()->user()))
                <section class="hc-prepaid-gift-actions" aria-label="Gift care hours">
                    @if($access->canBuyGift(auth()->user()))
                        <a href="{{ route('care-hours.gifts.index') }}" class="hc-prepaid-gift-card">
                            <span class="hc-prepaid-icon hc-prepaid-icon-gift"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">{!! $icons['gift'] !!}</svg></span>
                            <span class="hc-prepaid-gift-copy"><strong>Give care as a gift</strong><span>Buy hours for someone you love.</span></span>
                            <svg class="hc-prepaid-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">{!! $icons['arrow'] !!}</svg>
                        </a>
                    @endif
                    <a href="{{ route('care-hours.claim') }}" class="hc-prepaid-gift-card">
                        <span class="hc-prepaid-icon hc-prepaid-icon-code"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">{!! $icons['code'] !!}</svg></span>
                        <span class="hc-prepaid-gift-copy"><strong>Have a gift code?</strong><span>Add your gift to your care balance.</span></span>
                        <svg class="hc-prepaid-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">{!! $icons['arrow'] !!}</svg>
                    </a>
                </section>
            @endif

            <section class="hc-prepaid-activity" aria-label="Prepaid care activity">
                <div class="hc-prepaid-section-heading"><h2>{{ $accounts->count() > 1 ? 'Your care balances' : 'Your activity' }}</h2>@if($accounts->count() === 1)<a href="{{ route('family.care-hours.show', $accounts->first()) }}" wire:navigate>View activity <span aria-hidden="true">→</span></a>@endif</div>
                @if($accounts->count() > 1)<p class="hc-prepaid-note">Credit stays with each person’s care balance.</p>@endif
                @forelse($accounts as $account)
                    <a href="{{ route('family.care-hours.show', $account) }}" wire:navigate class="hc-prepaid-account-row">
                        <span class="hc-prepaid-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">{!! $icons['activity'] !!}</svg></span>
                        <span class="hc-prepaid-account-name"><strong>{{ $account->display_name }}</strong><span>{{ $account->active ? 'Purchases, visits & receipts' : 'Inactive balance' }}</span></span>
                        <span class="hc-prepaid-account-amount">{{ \App\Models\CareHourAccount::formatMinutes($balances[$account->id]['available_minutes']) }}<span>{{ $account->active ? 'available' : 'credit' }}</span></span>
                        <svg class="hc-prepaid-card-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">{!! $icons['arrow'] !!}</svg>
                    </a>
                @empty
                    <div class="hc-prepaid-empty-activity"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">{!! $icons['activity'] !!}</svg><p>Your purchases and care-hour usage will appear here.</p></div>
                @endforelse
            </section>
        @endif
    </div>
</x-app-layout>
