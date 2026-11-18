<div class="pw">
    <header class="pw-heading">
        <div><p class="pw-eyebrow">{{ $profile->business_name }}</p><h1 class="pw-title">Your next conversations.</h1><p class="pw-subtitle">Contact details and service needs for every lead assigned to your team.</p></div>
        <span class="pw-status">{{ number_format($remainingCredits) }} credits available</span>
    </header>
    <x-partner-workspace-nav />
    <div class="pw-section-heading" style="margin-top:7px"><div><h2>My leads <span class="pw-counter">{{ number_format($totalCount) }}</span></h2><p>Each introduction is assigned to your business for the listed service.</p></div><a href="{{ route('partner.dashboard') }}#lead-packages" wire:navigate class="pw-button pw-button--secondary">Explore packages <span aria-hidden="true">→</span></a></div>
    <div class="pw-filters">
        <div class="pw-field"><label for="partner-lead-search">Search leads</label><input id="partner-lead-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search by name, email or location" autocomplete="off"></div>
        <div class="pw-field"><label for="partner-lead-category">Service category</label><select id="partner-lead-category" wire:model.live="categoryFilter"><option value="">All services</option>
@foreach(\App\Support\PartnerOptions::categories() as $key => $label)<option value="{{ $key }}">{{ $label }}</option>
@endforeach</select></div>
    </div>
    <div wire:loading.delay class="pw-panel-caption" role="status" style="margin-bottom:12px">Updating your leads…</div>

@forelse ($leads as $lead)
        <article id="lead-{{ $lead->id }}" wire:key="partner-lead-{{ $lead->id }}" class="pw-panel pw-lead-card" x-data="{ open: window.location.hash === '#lead-{{ $lead->id }}' }" style="scroll-margin-top:110px">
            <button type="button" class="pw-lead-card-head" @click="open = !open" :aria-expanded="open" aria-controls="lead-detail-{{ $lead->id }}">
                <span class="pw-avatar" aria-hidden="true">{{ collect(preg_split('/\s+/', trim($lead->contact_name)))->filter()->take(2)->map(fn($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') }}</span>
                <div class="pw-lead-main"><h3 class="pw-lead-name">{{ $lead->contact_name }}</h3><p class="pw-lead-meta">{{ $lead->categoryLabel() }} · {{ $lead->location ?: $lead->zip }}</p></div>
                <p class="pw-lead-date"><span>Delivered</span>{{ $lead->assigned_at?->format('M j, Y') }}</p>
                <svg class="pw-lead-chevron" :style="open && 'transform:rotate(180deg)'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div id="lead-detail-{{ $lead->id }}" class="pw-lead-detail" x-show="open" x-cloak>
                <div class="pw-lead-detail-grid">
                    <div><p class="pw-detail-label">Phone</p>
@if($lead->contact_phone)<a href="tel:{{ preg_replace('/[^+0-9]/', '', $lead->contact_phone) }}" class="pw-link">{{ $lead->contact_phone }}</a>
@else<p class="pw-detail-value">Not provided</p>
@endif</div>
                    <div><p class="pw-detail-label">Email</p>
@if($lead->contact_email)<a href="mailto:{{ $lead->contact_email }}" class="pw-link">{{ $lead->contact_email }}</a>
@else<p class="pw-detail-value">Not provided</p>
@endif</div>
                    <div><p class="pw-detail-label">Service requested</p><p class="pw-detail-value">{{ $lead->categoryLabel() }}</p></div>
                    <div><p class="pw-detail-label">Location</p><p class="pw-detail-value">{{ $lead->location ?: 'Location not provided' }}
@if($lead->zip && $lead->zip !== $lead->location)<br>{{ $lead->zip }}
@endif</p></div>

@if ($lead->service_context)<div class="pw-lead-context"><p class="pw-detail-label" style="margin-bottom:8px">About this request</p><p class="whitespace-pre-line">{{ $lead->service_context }}</p></div>
@endif
                </div>
            </div>
        </article>

@empty
        <section class="pw-panel pw-empty">
            <span class="pw-empty-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M18 8h5M20.5 5.5v5"/><circle cx="9.5" cy="6.5" r="3.5"/></svg></span>
            <h3>{{ $totalCount > 0 ? 'No leads match your search.' : 'Your first lead will appear here.' }}</h3>
            <p>{{ $totalCount > 0 ? 'Try a different name, location or service category to find the right conversation.' : 'Our team will assign leads that fit your services and coverage. Their contact details will be ready here when they arrive.' }}</p>
            <a href="{{ route('partner.profile.edit') }}" wire:navigate class="pw-link">Review your business profile <span aria-hidden="true">→</span></a>
        </section>

@endforelse

@if ($leads->hasPages())<div style="margin-top:25px">{{ $leads->links() }}</div>
@endif
    <footer class="pw-bottom-note"><span>Need help with a lead?</span><a href="mailto:hello@carelolo.com" class="pw-link">Contact the LoLo team <span aria-hidden="true">↗</span></a></footer>
</div>
