@props(['options', 'canPurchase' => false, 'canSubscribe' => false, 'approved' => false, 'paused' => false])
@if (! $approved)
    <p class="pw-notice">{{ $paused ? 'Package purchases are paused for your partnership. Contact our team to discuss next steps.' : 'Your profile needs to be approved before you can buy a lead package. Our team will review your services and coverage.' }}</p>
@endif
<div class="pw-packages">

@foreach ($options as $plan => $option)

@php
            $quantity = (int) $option['quantity'];
            $amount = (int) $option['amount_cents'];
            $unitAmount = (int) $option['unit_price_cents'];
            $monthly = $plan === 'monthly';
            $available = $canPurchase && (! $monthly || $canSubscribe);

@endphp
        <article class="pw-package {{ $monthly ? 'pw-package--first' : '' }}">
            <div>
                <p class="pw-eyebrow" style="margin-bottom:15px">{{ $option['name'] }}</p>
                <h3 class="pw-package-number">${{ number_format($unitAmount / 100, 0) }}<span style="font:400 12px Inter,sans-serif;letter-spacing:0;color:var(--pw-muted)"> / lead</span></h3>
                <p class="pw-package-unit">{{ $quantity }} lead credits{{ $monthly ? ' every month' : '' }}</p>
            </div>
            <p class="pw-package-price">${{ number_format($amount / 100, 0) }} <small>{{ $monthly ? '/ month' : 'upfront' }}</small></p>
            <p class="pw-package-copy">
@if($monthly)Renews monthly. 25 new credits each month.
@elseif($plan === 'annual')300 credits paid upfront for the year. No automatic renewal.
@else One payment. No recurring subscription.
@endif Unused credits carry forward.</p>

@if ($available)
                <form method="POST" action="{{ route('partner.billing.subscribe') }}">

@csrf
                    <input type="hidden" name="plan" value="{{ $plan }}">
                    <button type="submit" class="pw-button {{ $monthly ? '' : 'pw-button--secondary' }}" aria-label="Choose {{ $option['name'] }} for ${{ number_format($amount / 100, 0) }} {{ $monthly ? 'per month' : 'upfront' }}">{{ $monthly ? 'Start monthly plan' : 'Choose package' }} <span aria-hidden="true">→</span></button>
                </form>

@else
                <button type="button" class="pw-button" disabled>{{ $paused ? 'Purchases paused' : (! $approved ? 'Available after approval' : 'Monthly plan in progress') }}</button>

@endif
        </article>

@endforeach
</div>
<p class="pw-package-footnote">Credits work across your selected service categories. Our team assigns leads based on your services and coverage. Review the total in secure checkout.</p>
