<div class="pw">

@php
        $card = $billing['card'] ?? null;
        $billingReady = (bool) ($billing['ready'] ?? false);
        $approved = $profile->status === 'approved';
        $firstName = collect(preg_split('/\s+/', trim($profile->contact_name)))->first();

@endphp
    <header class="pw-heading">
        <div>
            <p class="pw-eyebrow">{{ $profile->business_name }}</p>
            <h1 class="pw-title">Welcome back, {{ $firstName }}.</h1>
            <p class="pw-subtitle">Your connections, your coverage. Everything in one place.</p>
        </div>
        <span class="pw-status {{ $approved ? '' : 'pw-status--muted' }}">{{ $profile->statusLabel() }} partner</span>
    </header>
    <x-partner-workspace-nav />

@if (session('partner-profile-saved'))<div role="status" class="pw-notice pw-notice--success">Your partner profile has been updated.</div>
@endif

@if (session('status'))<div role="status" class="pw-notice pw-notice--success">{{ session('status') }}</div>
@endif

@if (! $approved)
        <div class="pw-notice" role="status">{{ $profile->status === 'paused' ? 'Your partnership is paused. You can view existing leads and update your profile. Contact our team when you are ready to discuss next steps.' : 'Thanks for joining LoLo. Your registration is complete and our team will review your profile. You can explore the workspace and keep your details up to date.' }}</div>

@endif
    <section class="pw-hero" aria-label="Your lead credit balance">
        <svg class="pw-hero-art" viewBox="0 0 340 340" fill="none" aria-hidden="true"><circle cx="170" cy="170" r="160" stroke="currentColor"/><circle cx="170" cy="170" r="113" stroke="currentColor"/><circle cx="170" cy="170" r="64" stroke="currentColor"/><path d="M10 170H330M170 10V330" stroke="currentColor"/><circle cx="170" cy="57" r="7" fill="currentColor"/><circle cx="283" cy="170" r="7" fill="currentColor"/></svg>
        <div class="pw-hero-copy">
            <p class="pw-eyebrow">Grow with LoLo</p>
            <h2>Your next connection<br>starts here.</h2>
            <p>Connect with people looking for your services, in the communities you cover. Our team handles lead assignments so you can focus on the conversation.</p>
            <div class="pw-hero-actions">
                <a href="#lead-packages" class="pw-button pw-button--light">Explore lead packages <span aria-hidden="true">→</span></a>
                <a href="{{ route('partner.leads.index') }}" wire:navigate class="pw-link">View your leads <span aria-hidden="true">↗</span></a>
            </div>
        </div>
        <div class="pw-balance">
            <p class="pw-balance-label">Available lead credits</p><p class="pw-balance-value">{{ number_format($remainingCredits) }}</p>
            <p class="pw-balance-note">Unused credits stay in your balance until assigned.</p>
            <div class="pw-balance-footer"><div><strong>{{ number_format($deliveredCount) }}</strong><span>Leads delivered</span></div><div><strong>{{ count($profile->categories ?? []) }}</strong><span>Service categories</span></div></div>
        </div>
    </section>
    <div class="pw-columns">
        <section class="pw-panel" aria-labelledby="latest-leads-heading">
            <div class="pw-panel-head"><div><h2 id="latest-leads-heading" class="pw-panel-title">Latest leads</h2><p class="pw-panel-caption">Your most recent introductions from LoLo.</p></div><a href="{{ route('partner.leads.index') }}" wire:navigate class="pw-link">View all <span aria-hidden="true">→</span></a></div>

@forelse ($latestLeads as $lead)
                <a href="{{ route('partner.leads.index') }}#lead-{{ $lead->id }}" wire:navigate class="pw-lead-row">
                    <span class="pw-avatar" aria-hidden="true">{{ collect(preg_split('/\s+/', trim($lead->contact_name)))->filter()->take(2)->map(fn($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') }}</span>
                    <div class="pw-lead-main"><p class="pw-lead-name">{{ $lead->contact_name }}</p><p class="pw-lead-meta">{{ $lead->categoryLabel() }}<br>{{ $lead->location ?: $lead->zip }}</p></div>
                    <p class="pw-lead-date"><span>Delivered</span>{{ $lead->assigned_at?->format('M j') }}</p>
                </a>

@empty
                <div class="pw-empty">
                    <span class="pw-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3"><rect x="3" y="5" width="18" height="15" rx="3"/><path d="M3 10h5l2 3h4l2-3h5M8 2v6M16 2v6"/></svg></span>
                    <h3>Your first connection awaits.</h3><p>When our team assigns a lead, their contact details and service needs will appear right here.</p>
                    <a href="{{ route('partner.profile.edit') }}" wire:navigate class="pw-link">Review your services & coverage <span aria-hidden="true">→</span></a>
                </div>

@endforelse
        </section>
        <aside class="pw-stack">
            <section class="pw-panel pw-panel-inner">
                <h2 class="pw-small-title">Your payment method</h2>
                <div class="pw-payment-line"><span class="pw-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20M6 15h3"/></svg></span><div>
                    <p class="pw-lead-name">
@if($billingUnavailable)Check unavailable
@elseif($billingReady && $card){{ ($card['type'] ?? '') === 'link' ? 'Link payment method' : ucfirst($card['brand'] ?? 'Card').' •••• '.($card['last4'] ?? '') }}
@else No card saved yet
@endif</p>
                    <p class="pw-lead-meta">{{ $billingUnavailable ? 'Open billing to check your card.' : ($billingReady ? 'Ready for your next purchase.' : 'Set up once, then you’re ready.') }}</p>
                </div></div>
                <a href="{{ route('partner.billing.show') }}" wire:navigate class="pw-link">{{ $billingReady ? 'Manage billing' : 'Add payment method' }} <span aria-hidden="true">→</span></a>
            </section>
            <section class="pw-panel pw-panel-inner">
                <div class="flex items-center justify-between gap-3"><h2 class="pw-small-title">Your focus</h2><a href="{{ route('partner.profile.edit') }}" wire:navigate class="pw-link">Edit</a></div>
                <div class="pw-chip-list">
@foreach($profile->categoryLabels() as $category)<span class="pw-chip">{{ $category }}</span>
@endforeach</div>
                <div class="pw-divider"></div><p class="pw-detail-label">Service area</p><p class="pw-detail-value">{{ implode(', ', $profile->coverageStateLabels()) }}</p>
                <p class="pw-lead-meta">{{ $profile->coverage_type === 'entire_states' ? 'All areas in the selected states' : $profile->coverage_areas }}</p>
            </section>
        </aside>
    </div>
    <section id="lead-packages" class="pw-section" aria-labelledby="packages-heading">
        <div class="pw-section-heading"><div><p class="pw-eyebrow">Room to grow</p><h2 id="packages-heading" style="margin-top:7px">Choose your lead package.</h2><p>Choose a one-time package, a monthly plan or a prepaid year.</p></div><a href="{{ route('partner.billing.show') }}" wire:navigate class="pw-link">Plan & billing <span aria-hidden="true">→</span></a></div>
        <x-partner-package-options :options="$packageOptions" :can-purchase="$canPurchase" :can-subscribe="$canSubscribe" :approved="$approved" :paused="$profile->status === 'paused'" />
    </section>
    <footer class="pw-bottom-note"><span>Your services and coverage guide every assignment.</span><a href="mailto:hello@carelolo.com" class="pw-link">Talk to the LoLo team <span aria-hidden="true">↗</span></a></footer>
</div>
