<div class="hc-page alp-page">
    <header class="alp-header">
        <div>
            <p class="alp-eyebrow">Partner network</p>
            <h1>Partner leads</h1>
            <p class="alp-intro">Review additional service requests and send each to the right partner.</p>
        </div>
        <a href="{{ route('admin.partners.index') }}" class="alp-button alp-button--secondary">Partner directory <span aria-hidden="true">↗</span></a>
    </header>

    @if(session('leadDelivered'))
        <div class="alp-notice alp-notice--success" role="status">
            <span class="alp-notice-icon" aria-hidden="true">✓</span>
            <p>{{ session('leadDelivered') }}</p>
        </div>
    @endif

    <section class="alp-stats" aria-label="Lead delivery filters">
        @foreach(['all' => ['All leads', 'Every family contact'], 'available' => ['Available', 'At least one unsent service'], 'sent' => ['Sent to partners', 'At least one service delivered'], 'no_services' => ['No matched service', 'No supported request recorded']] as $value => [$label, $description])
            <button type="button" wire:click="$set('status', '{{ $value }}')" class="alp-stat {{ $status === $value ? 'alp-stat--active' : '' }}" aria-pressed="{{ $status === $value ? 'true' : 'false' }}">
                <span class="alp-stat-label">{{ $label }}</span>
                <span class="alp-stat-value">{{ number_format($stats[$value] ?? 0) }}</span>
                <span class="alp-stat-description">{{ $description }}</span>
            </button>
        @endforeach
    </section>

    <section class="alp-workspace" aria-labelledby="lead-queue-heading">
        <div class="alp-filterbar">
            <div class="alp-field alp-field--search">
                <label for="partner-leads-search">Search leads</label>
                <div class="alp-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="10.7" cy="10.7" r="6.7"/><path d="m16 16 4.5 4.5"/></svg>
                    <input id="partner-leads-search" type="search" wire:model.live.debounce.300ms="q" maxlength="200" placeholder="Name, email, phone, location or ID">
                </div>
            </div>
            <div class="alp-field">
                <label for="partner-leads-category">Requested service</label>
                <select id="partner-leads-category" wire:model.live="category">
                    <option value="all">All services</option>
                    @foreach($categoryOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="alp-field">
                <label for="partner-leads-status">Delivery status</label>
                <select id="partner-leads-status" wire:model.live="status">
                    <option value="all">All leads</option>
                    <option value="available">Available services</option>
                    <option value="sent">Sent to partners</option>
                    <option value="no_services">No matched service</option>
                </select>
            </div>
        </div>
        <div class="alp-queue-heading">
            <div>
                <h2 id="lead-queue-heading">{{ number_format($leads->total()) }} {{ $leads->total() === 1 ? 'lead' : 'leads' }}</h2>
                <p>One partner per contact, per service.</p>
            </div>
            <div class="alp-queue-actions">
                <span wire:loading.delay wire:target="q,category,status,clearFilters" class="alp-muted" role="status">Updating…</span>
                @if($q !== '' || $category !== 'all' || $status !== 'all')
                    <button type="button" wire:click="clearFilters" class="alp-text-button">Clear filters</button>
                @endif
            </div>
        </div>

        @if($leads->count())
            <table class="alp-table">
                <caption class="sr-only">Family leads, their additional service requests, and partner delivery status</caption>
                <thead>
                    <tr><th scope="col">Contact &amp; location</th><th scope="col">Requested services</th><th scope="col">Received</th><th scope="col"><span class="sr-only">Review lead</span></th></tr>
                </thead>
                <tbody>
                    @foreach($leads as $lead)
                        @php
                            $leadCategories = $requestedCategories[$lead->id] ?? [];
                            $leadBlockedReason = $blockedReasons[$lead->id] ?? null;
                            $receivedAt = $lead->submitted_at ?? $lead->created_at;
                        @endphp
                        <tr wire:key="partner-lead-row-{{ $lead->id }}" class="{{ $selectedId === $lead->id ? 'alp-row--selected' : '' }}">
                            <td class="alp-contact-cell">
                                <button type="button" wire:click="selectLead({{ $lead->id }})" class="alp-contact-name">{{ $lead->name ?: 'Unnamed contact' }}</button>
                                <span class="alp-contact-detail">{{ $lead->email ?: ($lead->phone ?: 'Contact details missing') }}</span>
                                <span class="alp-contact-location">{{ implode(' · ', array_filter([$lead->location, $lead->zip])) ?: 'Location not recorded' }}</span>
                                @if($leadBlockedReason)
                                    <span class="alp-restricted">Sharing restricted</span>
                                @endif
                            </td>
                            <td class="alp-services-cell">
                                @if(count($leadCategories))
                                    <div class="alp-service-list">
                                        @foreach($leadCategories as $code)
                                            @php($delivery = $sentCategories[$lead->id][$code] ?? null)
                                            <button type="button" wire:click="selectLead({{ $lead->id }}, '{{ $code }}')" class="alp-service {{ $delivery ? 'alp-service--sent' : ($leadBlockedReason ? 'alp-service--restricted' : 'alp-service--available') }}" aria-label="Review {{ $categoryOptions[$code] ?? $code }} for {{ $lead->name ?: 'contact' }}">
                                                <span class="alp-service-title">{{ $categoryOptions[$code] ?? $code }}</span>
                                                <span class="alp-service-state">
                                                    <span class="alp-dot" aria-hidden="true"></span>
                                                    @if($delivery)
                                                        Sent · {{ $delivery['partner_name'] ?: 'Partner account' }}
                                                    @elseif($leadBlockedReason)
                                                        Sharing restricted
                                                    @else
                                                        Available to send
                                                    @endif
                                                </span>
                                            </button>
                                        @endforeach
                                    </div>
                                @elseif(count($lead->additionalServices()))
                                    <p class="alp-no-service">Other request recorded</p>
                                    <p class="alp-muted">No supported partner service</p>
                                @else
                                    <p class="alp-no-service">No additional services</p>
                                    <p class="alp-muted">No request recorded</p>
                                @endif
                            </td>
                            <td class="alp-date-cell">
                                <span class="alp-mobile-label">Received</span>
                                <span>{{ $receivedAt?->format('M j, Y') ?: 'Not recorded' }}</span>
                                <span class="alp-lead-id">#{{ $lead->id }}</span>
                            </td>
                            <td class="alp-action-cell">
                                <button type="button" wire:click="selectLead({{ $lead->id }})" class="alp-review-button" aria-label="Review lead {{ $lead->name ?: '#'.$lead->id }}">Review <span aria-hidden="true">→</span></button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="alp-pagination">{{ $leads->links() }}</div>
        @else
            <div class="alp-empty">
                <div class="alp-empty-icon" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M7 5h18v23H7zM11 11h10M11 16h10M11 21h6"/><path d="M12 5V3h8v2"/></svg></div>
                <h3>{{ $q !== '' || $category !== 'all' || $status !== 'all' ? 'No leads match these filters' : 'Your lead queue starts here' }}</h3>
                <p>{{ $q !== '' || $category !== 'all' || $status !== 'all' ? 'Try another service, delivery status or search to find the contact you need.' : 'Family leads will appear here with their additional service requests, ready for you to review.' }}</p>
                @if($q !== '' || $category !== 'all' || $status !== 'all')
                    <button type="button" wire:click="clearFilters" class="alp-button alp-button--secondary">Clear filters</button>
                @endif
            </div>
        @endif
    </section>
    <p class="alp-footnote">A lead requesting several services can appear in both Available and Sent. Sending a lead adds it to the partner’s account and uses one paid credit.</p>

    @if($selectedLead)
        <div class="alp-drawer-layer" wire:key="partner-lead-drawer-{{ $selectedLead->id }}"
            x-data="{
                previousFocus: document.activeElement,
                previousOverflow: document.body.style.overflow,
                init() { document.body.style.overflow = 'hidden'; this.$nextTick(() => this.$refs.drawerTitle.focus()); },
                destroy() { document.body.style.overflow = this.previousOverflow; if (this.previousFocus?.isConnected) this.previousFocus.focus({ preventScroll: true }); },
                trapFocus(event) {
                    const items = [...this.$refs.dialog.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex=\'-1\'])')].filter(el => el.getClientRects().length);
                    const first = items[0], last = items[items.length - 1];
                    if (!first) { event.preventDefault(); return; }
                    if (event.shiftKey && (document.activeElement === first || document.activeElement === this.$refs.drawerTitle)) { event.preventDefault(); last.focus(); }
                    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
                }
            }"
            x-on:keydown.escape.prevent.stop="$wire.closeLead()">
            <div class="alp-drawer-backdrop" aria-hidden="true" x-on:click="$wire.closeLead()"></div>
            <aside class="alp-drawer" role="dialog" aria-modal="true" aria-labelledby="partner-lead-detail-title" x-ref="dialog" x-on:keydown.tab="trapFocus($event)">
                <header class="alp-drawer-header">
                    <div>
                        <p class="alp-eyebrow">Lead #{{ $selectedLead->id }}</p>
                        <h2 id="partner-lead-detail-title" tabindex="-1" x-ref="drawerTitle">{{ $selectedLead->name ?: 'Unnamed contact' }}</h2>
                    </div>
                    <button type="button" wire:click="closeLead" class="alp-icon-button" aria-label="Close lead details"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
                </header>
                <div class="alp-drawer-body">
                    @if(session('leadDelivered'))
                        <div class="alp-notice alp-notice--success" role="status"><p>{{ session('leadDelivered') }}</p></div>
                    @endif
                    @if($errors->any())
                        <div class="alp-notice alp-notice--error" role="alert" tabindex="-1" x-init="$nextTick(() => $el.focus())">
                            <div><strong>Review before sending</strong><ul>
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul></div>
                        </div>
                    @endif

                    <section class="alp-contact-summary" aria-label="Contact details">
                        <dl>
                            <div><dt>Location</dt><dd>{{ implode(' · ', array_filter([$selectedLead->location, $selectedLead->zip])) ?: 'Not recorded' }}</dd></div>
                            <div><dt>Email</dt><dd>
                                @if($selectedLead->email)
                                    <a href="mailto:{{ $selectedLead->email }}">{{ $selectedLead->email }}</a>
                                @else
                                    Not recorded
                                @endif
                            </dd></div>
                            <div><dt>Phone</dt><dd>
                                @if($selectedLead->phone)
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $selectedLead->phone) }}">{{ $selectedLead->phone }}</a>
                                @else
                                    Not recorded
                                @endif
                            </dd></div>
                        </dl>
                    </section>

                    @if($selectedBlockedReason)
                        <div class="alp-notice alp-notice--warning"><div><strong>This lead cannot be shared</strong><p>{{ $selectedBlockedReason }}</p></div></div>
                    @endif

                    @php($reviewCategories = array_values(array_unique(array_merge($selectedCategories, array_keys($selectedSent)))))
                    @if(count($reviewCategories))
                        <section class="alp-drawer-section" aria-labelledby="lead-service-heading">
                            <div class="alp-step-heading"><span class="alp-step-number" aria-hidden="true">1</span><div><h3 id="lead-service-heading">Review the service</h3><p>Each requested service can go to one partner.</p></div></div>
                            <div class="alp-category-choices">
                                @foreach($reviewCategories as $code)
                                    @php($categoryDelivery = $selectedSent[$code] ?? null)
                                    <label class="alp-category-choice {{ $assignmentCategory === $code ? 'alp-category-choice--selected' : '' }}">
                                        <input type="radio" name="assignmentCategory" value="{{ $code }}" wire:model.live="assignmentCategory">
                                        <span><strong>{{ $categoryOptions[$code] ?? $code }}</strong><small>{{ $categoryDelivery ? 'Sent · '.($categoryDelivery['partner_name'] ?: 'Partner account') : ($selectedBlockedReason ? 'Sharing restricted' : 'Available to send') }}</small></span>
                                        @if($categoryDelivery)
                                            <span class="alp-check" aria-hidden="true">✓</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </section>

                        @php($currentDelivery = $selectedSent[$assignmentCategory] ?? null)
                        @if($currentDelivery)
                            <section class="alp-delivery-record" aria-labelledby="lead-delivery-heading">
                                <span class="alp-delivery-icon" aria-hidden="true">✓</span>
                                <div><h3 id="lead-delivery-heading">Already sent for this service</h3><p>{{ $currentDelivery['partner_name'] ?: 'Partner account' }}</p>
                                    @if(!empty($currentDelivery['assigned_at']))
                                        <p class="alp-muted">{{ \Illuminate\Support\Carbon::parse($currentDelivery['assigned_at'])->format('M j, Y · g:i a') }}</p>
                                    @endif
                                    <p class="alp-delivery-explanation">This contact cannot be sent again for {{ mb_strtolower($categoryOptions[$assignmentCategory] ?? 'this service') }}.</p>
                                </div>
                            </section>
                        @elseif(!$selectedBlockedReason && $assignmentCategory !== '')
                            <form wire:submit="assignLead">
                                <section class="alp-drawer-section" aria-labelledby="lead-partner-heading">
                                    <div class="alp-step-heading"><span class="alp-step-number" aria-hidden="true">2</span><div><h3 id="lead-partner-heading">Choose a partner</h3><p>Approved accounts offering this service, with paid credits.</p></div></div>
                                    <div class="alp-field">
                                        <label for="assignment-partner-search">Find a partner</label>
                                        <input id="assignment-partner-search" type="search" wire:model.live.debounce.300ms="partnerSearch" maxlength="200" placeholder="Business name or coverage area">
                                    </div>
                                    <div class="alp-partner-choices" aria-label="Eligible partners">
                                        @forelse($eligiblePartners as $partner)
                                            <label class="alp-partner-choice {{ (string) $assignmentPartnerId === (string) $partner->id ? 'alp-partner-choice--selected' : '' }}" wire:key="lead-partner-choice-{{ $partner->id }}">
                                                <input type="radio" name="assignmentPartnerId" value="{{ $partner->id }}" wire:model.live="assignmentPartnerId">
                                                <span class="alp-partner-content"><span class="alp-partner-heading"><strong>{{ $partner->business_name }}</strong><span class="alp-credit-badge">{{ number_format($partner->available_credits) }} {{ (int) $partner->available_credits === 1 ? 'credit' : 'credits' }}</span></span><span class="alp-partner-coverage">{{ $partner->coverageSummary() }}</span></span>
                                            </label>
                                        @empty
                                            <div class="alp-partner-empty"><strong>{{ $partnerSearch !== '' ? 'No partners match your search' : 'No eligible partner yet' }}</strong><p>{{ $partnerSearch !== '' ? 'Try another business name or coverage area.' : 'A partner needs an approved account, this service on their profile, and available paid credits.' }}</p><a href="{{ route('admin.partners.index') }}" class="alp-inline-link">View partner directory <span aria-hidden="true">↗</span></a></div>
                                        @endforelse
                                    </div>
                                    @if($partnerChoicesTruncated)
                                        <p class="alp-muted alp-search-hint">Showing the first 30 matches. Search by name or coverage area to narrow the list.</p>
                                    @endif
                                </section>

                                @if($selectedPartner)
                                    <section class="alp-drawer-section" aria-labelledby="lead-confirm-heading">
                                        <div class="alp-step-heading"><span class="alp-step-number" aria-hidden="true">3</span><div><h3 id="lead-confirm-heading">Review &amp; send</h3><p>Confirm this introduction is ready to share.</p></div></div>
                                        <div class="alp-send-summary"><span>Send to</span><strong>{{ $selectedPartner->business_name }}</strong><span>{{ $categoryOptions[$assignmentCategory] ?? $assignmentCategory }}</span><div><span>Paid credits</span><strong>{{ number_format($selectedPartnerCredits) }} → {{ number_format(max(0, $selectedPartnerCredits - 1)) }}</strong></div></div>
                                        <div class="alp-coverage-review"><strong>Check their coverage</strong><p>{{ $selectedPartner->coverageSummary() }}</p></div>
                                        <label class="alp-confirmation"><input type="checkbox" wire:model="sharingConfirmed"><span>I confirmed this contact agreed to share their contact details and this service request with {{ $selectedPartner->business_name }}.</span></label>
                                        <label class="alp-confirmation"><input type="checkbox" wire:model="geographyConfirmed"><span>I checked the contact’s location and confirmed this partner covers it.</span></label>
                                        <p class="alp-send-note">The contact and selected service will appear in the partner’s account. Other service requests stay separate.</p>
                                        <button type="submit" class="alp-button alp-button--send" wire:loading.attr="disabled" wire:target="assignLead,assignmentCategory,assignmentPartnerId">
                                            <span wire:loading.remove wire:target="assignLead">Send lead · 1 credit <span aria-hidden="true">→</span></span>
                                            <span wire:loading wire:target="assignLead">Sending lead…</span>
                                        </button>
                                    </section>
                                @endif
                            </form>
                        @endif
                    @else
                        <section class="alp-partner-empty"><h3>No supported service request</h3><p>This lead has no recorded request matching the partner service categories. A request needs to be recorded before the lead can be sent.</p></section>
                    @endif

                    @if(count($selectedLead->additionalServices()))
                        <details class="alp-original-requests" {{ count($selectedCategories) ? '' : 'open' }}>
                            <summary>Original service answers</summary>
                            <ul>
                                @foreach($selectedLead->additionalServices() as $answer)
                                    <li>{{ $answer }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            </aside>
        </div>
    @endif
</div>
