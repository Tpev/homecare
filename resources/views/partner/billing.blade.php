<x-app-layout>
    <div class="pw">

@php
            $card = $billing['card'] ?? null;
            $ready = (bool) ($billing['ready'] ?? false);
            $approved = $profile->status === 'approved';
            $subscriptionLabel = $subscription ? match($subscription->status) {
                'active' => 'Active', 'trialing' => 'Trial', 'past_due' => 'Payment overdue', 'unpaid' => 'Payment needed',
                'incomplete' => 'Payment incomplete', 'checkout_pending' => 'Checkout in progress', 'checkout_creating' => 'Checkout started',
                'canceled' => 'Canceled', 'incomplete_expired', 'checkout_expired' => 'Checkout expired', default => ucfirst(str_replace('_', ' ', $subscription->status)),
            } : 'No monthly subscription';

@endphp
        <header class="pw-heading"><div><p class="pw-eyebrow">{{ $profile->business_name }}</p><h1 class="pw-title">Your plan & billing.</h1><p class="pw-subtitle">A clear view of your payment method, subscriptions and invoices.</p></div><a href="#lead-packages" class="pw-button pw-button--secondary">Explore lead packages <span aria-hidden="true">↓</span></a></header>
        <x-partner-workspace-nav />

@if(session('status'))<div role="status" class="pw-notice pw-notice--success">{{ session('status') }}</div>
@endif

@if($errors->any())<div role="alert" class="pw-notice pw-notice--error">
@foreach($errors->all() as $error)<p>{{ $error }}</p>
@endforeach</div>
@endif

        <div class="pw-billing-grid">
            <section class="pw-panel pw-panel-inner" aria-labelledby="payment-method-heading">
                <div class="flex items-center justify-between gap-3"><h2 id="payment-method-heading" class="pw-panel-title">Payment method</h2><span class="pw-status {{ $ready && !$billingUnavailable ? '' : 'pw-status--muted' }}">{{ $billingUnavailable ? 'Check unavailable' : ($ready ? 'Ready' : 'Not set up') }}</span></div>

@if($billingUnavailable)
                    <p class="pw-panel-caption" role="alert">We could not check your saved payment method. You can still add or update it securely below.</p>

@elseif($ready && $card)
                    <div class="pw-payment-card">
                        <div class="pw-payment-card-top"><strong>{{ ($card['type'] ?? '') === 'link' ? 'Link' : strtoupper($card['brand'] ?? 'CARD') }}</strong><span>Saved payment method</span></div>

@if(($card['type'] ?? '') === 'link')<p class="pw-card-digits" style="letter-spacing:0">Ready with Link</p><p class="pw-card-expiry">Your saved payment method is ready to use.</p>

@else<p class="pw-card-digits">•••• &nbsp; •••• &nbsp; {{ $card['last4'] ?? '' }}</p><p class="pw-card-expiry">EXPIRES &nbsp; {{ str_pad((string)($card['exp_month'] ?? ''), 2, '0', STR_PAD_LEFT) }} / {{ $card['exp_year'] ?? '' }}</p>
@endif
                    </div>

@else
                    <div class="pw-payment-card"><div class="pw-payment-card-top"><strong>Your next step</strong><span>Secure payment setup</span></div><p style="font:500 27px/1.25 'Source Serif 4',serif;margin-top:25px;max-width:270px">A card on file.<br>One less thing to do.</p><p class="pw-card-expiry">Add a payment method for your partner account.</p></div>

@endif
                <form method="POST" action="{{ route('partner.billing.setup') }}" class="pw-billing-action">
@csrf<button type="submit" class="pw-button pw-button--secondary">{{ $ready ? 'Update payment method' : 'Add payment method securely' }} <span aria-hidden="true">↗</span></button></form>
                <p class="pw-panel-caption" style="text-align:center">Your payment details are handled securely by Stripe.</p>
            </section>
            <section class="pw-panel pw-panel-inner" aria-labelledby="monthly-plan-heading">
                <div class="flex items-center justify-between gap-3"><h2 id="monthly-plan-heading" class="pw-panel-title">Monthly plan</h2>
@if($subscription)<span class="pw-status {{ $subscription->status === 'active' ? '' : 'pw-status--muted' }}">{{ $subscriptionLabel }}</span>
@endif</div>

@if ($subscription)
                    <p class="pw-plan-stat">{{ number_format($subscription->quantity) }} <small>lead credits / month</small></p>
                    <p class="pw-plan-price">Plan price: ${{ number_format($subscription->quantity * $subscription->unit_price_cents / 100, 0) }} / month</p>
                    <dl>

@if($subscription->current_period_end)<div class="pw-definition-row"><dt>{{ $subscription->cancel_at_period_end ? 'Renewal stops on' : 'Current period ends' }}</dt><dd>{{ $subscription->current_period_end->format('M j, Y') }}</dd></div>
@endif
                        <div class="pw-definition-row"><dt>Unused credits</dt><dd>Carry forward</dd></div>
                    </dl>

@if ($subscription->cancel_at_period_end)
                        <p class="pw-panel-caption" style="margin-top:18px">Your monthly renewal is canceled. Unused purchased credits stay available.</p>

@elseif ($canCancel)
                        <details style="margin-top:15px"><summary class="pw-link" style="cursor:pointer">Cancel monthly renewal</summary><p class="pw-panel-caption">Stop future monthly renewals. Your unused purchased credits will remain available.</p><form method="POST" action="{{ route('partner.billing.cancel') }}" style="margin-top:13px">
@csrf<button type="submit" class="pw-button pw-button--secondary">Confirm cancellation</button></form></details>

@elseif($subscription->status === 'checkout_pending')
                        <p class="pw-panel-caption" style="margin-top:18px">Your checkout is still in progress. Credits appear after payment is confirmed.</p>

@if($approved)<form method="POST" action="{{ route('partner.billing.subscribe') }}" style="margin-top:15px">
@csrf
<input type="hidden" name="plan" value="monthly"><button type="submit" class="pw-button">Resume secure checkout <span aria-hidden="true">↗</span></button></form>
@endif

@elseif($subscription->status === 'checkout_creating' && $approved)
                        <p class="pw-panel-caption" style="margin-top:18px">We have saved your checkout request. Continue to retry the same checkout.</p>
                        <form method="POST" action="{{ route('partner.billing.subscribe') }}" style="margin-top:15px">
@csrf<input type="hidden" name="plan" value="monthly"><button type="submit" class="pw-button">Continue checkout <span aria-hidden="true">→</span></button></form>

@endif

@else
                    <div class="pw-empty" style="padding:33px 10px 10px"><span class="pw-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 2v6M16 2v6M8 15h3M8 18h6"/></svg></span><h3>A steady flow of connections.</h3><p>A monthly plan adds 25 lead credits for $750 each month. You can also choose a one-time or annual package below.</p></div>

@endif
            </section>
        </div>

        <section class="pw-panel pw-section" aria-labelledby="invoices-heading">
            <div class="pw-panel-head"><div><h2 id="invoices-heading" class="pw-panel-title">Invoices & receipts</h2><p class="pw-panel-caption">Your lead purchases and subscription payments.</p></div>
@unless($invoicesUnavailable)<span class="pw-counter">{{ $invoices->count() }}</span>
@endunless</div>

@if($invoicesUnavailable)
                <div class="pw-notice" role="alert" style="margin:0 24px 24px">We could not load your invoices right now. Please try this page again shortly.</div>

@elseif($invoices->isEmpty())
                <div class="pw-empty" style="padding-top:23px"><h3>Your billing history starts here.</h3><p>Invoices and receipts will appear after your first lead purchase.</p></div>

@else
                <div class="pw-invoice-head" aria-hidden="true"><span>Invoice</span><span>Date</span><span>Amount</span><span>Status</span><span style="text-align:right">Document</span></div>

@foreach($invoices as $invoice)
                    <article class="pw-invoice-row">
                        <div><strong>{{ $invoice['number'] ?: 'Payment receipt' }}</strong><p class="pw-invoice-description">{{ $invoice['description'] }}</p></div>
                        <p><span class="pw-invoice-mobile-label">Date</span>{{ $invoice['date']?->format('M j, Y') }}</p>
                        <p><span class="pw-invoice-mobile-label">Amount</span><strong>{{ strtoupper($invoice['currency']) === 'USD' ? '$' : strtoupper($invoice['currency']).' ' }}{{ number_format($invoice['amount_cents'] / 100, 2) }}</strong></p>
                        <div><span class="pw-invoice-mobile-label">Status</span><span class="pw-invoice-status {{ in_array($invoice['status'], ['paid','succeeded']) ? '' : 'pw-invoice-status--other' }}">{{ ucfirst(str_replace('_', ' ', $invoice['status'])) }}</span></div>

                        <div class="flex flex-col items-start gap-2">
                            @if($invoice['hosted_url'])
                                <a href="{{ $invoice['hosted_url'] }}" target="_blank" rel="noopener noreferrer" class="pw-link">{{ $invoice['status'] === 'open' ? 'Pay invoice' : 'View invoice' }} <span aria-hidden="true">↗</span></a>
                            @endif
                            @if($invoice['pdf_url'])
                                <a href="{{ $invoice['pdf_url'] }}" target="_blank" rel="noopener noreferrer" class="pw-link">Download PDF <span aria-hidden="true">↗</span></a>
                            @endif
                            @if(! $invoice['hosted_url'] && ! $invoice['pdf_url'])
                                <span class="pw-lead-meta">Not available yet</span>
                            @endif
                        </div>
                    </article>

@endforeach

@endif
        </section>
        <section id="lead-packages" class="pw-section" aria-labelledby="billing-packages-heading">
            <div class="pw-section-heading"><div><p class="pw-eyebrow">Choose your pace</p><h2 id="billing-packages-heading" style="margin-top:7px">Lead packages, clearly priced.</h2><p>All packages work across your selected services. Unused credits carry forward.</p></div></div>
            <x-partner-package-options :options="$packageOptions" :can-purchase="$canPurchase" :can-subscribe="$canSubscribe" :approved="$approved" :paused="$profile->status === 'paused'" />
        </section>
        <footer class="pw-bottom-note"><span>Questions about a payment or invoice?</span><a href="mailto:hello@carelolo.com" class="pw-link">Contact LoLo <span aria-hidden="true">↗</span></a></footer>
    </div>
</x-app-layout>
