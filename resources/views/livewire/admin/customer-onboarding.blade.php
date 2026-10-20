<div class="co-workspace" x-data>
    @if(session('onboarding_status'))
        <div class="co-notice" role="status">{{ session('onboarding_status') }}</div>
    @endif
    @if($errors->has('payment') || $errors->has('access') || $errors->has('link'))
        <div class="co-notice co-notice--error" role="alert">
            @foreach(['payment', 'access', 'link'] as $errorKey) @error($errorKey)<p>{{ $message }}</p>@enderror @endforeach
        </div>
    @endif

    @if(! $selected)
        <header class="co-heading">
            <div><p class="co-eyebrow">FAMILY OPERATIONS</p><h1>Customer onboarding</h1><p class="co-subtitle">A clear next step for every family, from first contact to first hire.</p></div>
            <button type="button" class="co-button co-button--primary" wire:click="startCreate" aria-label="Add family"><span aria-hidden="true">＋</span> Add family</button>
        </header>
    @else
        <button type="button" class="co-back" wire:click="closeFamily">← Back to families</button>
        <header class="co-heading co-heading--detail">
            <div class="co-identity">
                <span class="co-avatar co-avatar--large" aria-hidden="true">{{ mb_strtoupper(mb_substr($selected['name'], 0, 1)) }}</span>
                <div><p class="co-eyebrow">CUSTOMER ONBOARDING</p><h1>{{ $selected['name'] }}</h1><p class="co-subtitle">{{ $selected['email'] ?: 'Email not provided' }}</p></div>
            </div>
            @if($account)
                <a class="co-button" href="{{ route('admin.users.show', $account->owner_user_id) }}" wire:navigate>View account ↗</a>
            @else
                <button type="button" class="co-button co-button--primary" wire:click="startCreate">Create family account</button>
            @endif
        </header>
    @endif

    @if($showCreate)
        <section class="co-panel co-create" aria-labelledby="co-create-title">
            <div class="co-section-heading"><div><p class="co-eyebrow">PREPARE FOR THEIR VISIT</p><h2 id="co-create-title">{{ $creatingForLeadId ? 'Create their family account' : 'Add a family' }}</h2></div><button class="co-button co-button--quiet" type="button" wire:click="cancelCreate">Cancel</button></div>
            <p class="co-muted">Use the main contact’s details. We’ll reuse their existing family account if they already have one.</p>
            <form wire:submit="createFamily" class="co-create-form">
                <label class="co-field">Contact name<input wire:model="name" type="text" autocomplete="name" maxlength="255" required>@error('name')<span class="co-field-error">{{ $message }}</span>@enderror</label>
                <label class="co-field">Email<input wire:model="email" type="email" autocomplete="email" maxlength="255" required>@error('email')<span class="co-field-error">{{ $message }}</span>@enderror</label>
                <label class="co-field">Phone <span class="co-optional">optional</span><input wire:model="phone" type="tel" autocomplete="tel" maxlength="30">@error('phone')<span class="co-field-error">{{ $message }}</span>@enderror</label>
                <div class="co-create-footer"><p class="co-muted">You can prepare a payment link right away. Account access can be emailed separately.</p><button type="submit" class="co-button co-button--primary" wire:loading.attr="disabled" wire:target="createFamily"><span wire:loading.remove wire:target="createFamily">Create family account</span><span wire:loading wire:target="createFamily">Preparing account…</span></button></div>
            </form>
        </section>
    @endif

    @if(! $selected)
        <div class="co-metrics" aria-label="Family overview">
            @foreach([
                ['all', 'All families', $summary['total'], 'Your complete family directory'],
                ['no_account', 'Need an account', $summary['no_account'], 'Ready to prepare for a visit'],
                ['payment_missing', 'Need a payment method', $summary['payment_missing'], 'An account, with no card saved'],
                ['hired', 'First hire completed', $summary['hired'], 'Families who have hired care'],
            ] as [$metricFilter, $metricTitle, $metricValue, $metricHint])
                <button class="co-metric {{ $filter === $metricFilter ? 'is-selected' : '' }}" type="button" wire:click="setFilter('{{ $metricFilter }}')" aria-pressed="{{ $filter === $metricFilter ? 'true' : 'false' }}">
                    <span class="co-metric__label">{{ $metricTitle }}</span><span class="co-metric__value">{{ number_format($metricValue) }}</span><span class="co-metric__hint">{{ $metricHint }}</span>
                </button>
            @endforeach
        </div>
        <section class="co-panel co-directory" aria-label="All families and their progress">
            <div class="co-toolbar">
                <label class="co-search"><span class="sr-only">Search families</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/></svg><input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, email or phone"></label>
                <label class="co-filter"><span class="sr-only">Filter families</span><select wire:model.live="filter"><option value="all">All progress</option><option value="no_account">Needs an account</option><option value="payment_missing">Needs payment method</option><option value="payment_unknown">Payment not checked</option><option value="no_request">No request posted</option><option value="no_hire">No hire yet</option><option value="hired">First hire completed</option></select></label>
                <span class="co-result-count">{{ number_format($families->total()) }} {{ $families->total() === 1 ? 'family' : 'families' }}</span>
            </div>
            <div class="co-table-wrap">
                <table class="co-table">
                    <thead><tr><th>Family</th><th>Account</th><th>Payment method</th><th>Request posted</th><th>Caregiver hired</th><th><span class="sr-only">Next action</span></th></tr></thead>
                    <tbody>
                        @forelse($families as $family)
                            @php
                                $nextAction = match (true) {
                                    $family['account_status'] === 'closed' => 'View history',
                                    ! $family['account_id'] => 'Create account',
                                    $family['payment_status'] === 'unknown' => 'Check payment',
                                    $family['payment_status'] !== 'ready' => 'Set up payment',
                                    ! $family['request_posted'] => 'Review next step',
                                    ! $family['hired'] => 'Review request',
                                    default => 'View family',
                                };
                            @endphp
                            <tr wire:key="customer-{{ $family['key'] }}">
                                <td><div class="co-identity"><span class="co-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($family['name'], 0, 1)) }}</span><div><button class="co-name" type="button" wire:click="openFamily('{{ $family['key'] }}')">{{ $family['name'] }}</button><span class="co-email">{{ $family['email'] ?: ($family['phone'] ?: 'Contact details needed') }}</span></div></div></td>
                                <td data-label="Account"><x-customer-onboarding-status :state="$family['account_status'] === 'active' ? 'complete' : 'pending'" :label="match ($family['account_status']) { 'active' => 'Created', 'closed' => 'Closed', default => 'Not created' }" /></td>
                                <td data-label="Payment"><x-customer-onboarding-status :state="$family['payment_status'] === 'ready' ? 'complete' : 'pending'" :label="match ($family['payment_status']) { 'ready' => 'Saved', 'missing' => 'Not added', 'unknown' => 'Not checked', default => '—' }" /></td>
                                <td data-label="Request"><x-customer-onboarding-status :state="$family['request_posted'] ? 'complete' : 'pending'" :label="$family['request_posted'] ? 'Posted' : 'Not yet'" /></td>
                                <td data-label="Hire"><x-customer-onboarding-status :state="$family['hired'] ? 'complete' : 'pending'" :label="$family['hired'] ? 'Hired' : 'Not yet'" /></td>
                                <td class="co-table-action"><button type="button" class="co-row-action" aria-label="Open {{ $family['name'] }}" wire:click="openFamily('{{ $family['key'] }}')" wire:loading.attr="disabled" wire:target="openFamily">{{ $nextAction }} <span aria-hidden="true">→</span></button></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="co-empty"><span class="co-empty__symbol" aria-hidden="true">◎</span><h2>{{ $search || $filter !== 'all' ? 'No families match this view' : 'A good start for every family' }}</h2><p>{{ $search || $filter !== 'all' ? 'Try a different name or progress filter.' : 'Add your first family to prepare their account and payment link.' }}</p>@if($search || $filter !== 'all')<button class="co-button" type="button" wire:click="clearFilters">Clear search and filters</button>@else<button class="co-button co-button--primary" type="button" wire:click="startCreate">Add family</button>@endif</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($families->hasPages())<div class="co-pagination">{{ $families->links() }}</div>@endif
            <div class="co-directory-foot"><span class="co-dot" aria-hidden="true"></span>Progress comes from family activity. Posted requests and first hires remain recorded after care ends.@if($summary['payment_unknown'])<button class="co-text-button" type="button" wire:click="setFilter('payment_unknown')">{{ $summary['payment_unknown'] }} payment {{ $summary['payment_unknown'] === 1 ? 'status' : 'statuses' }} to check</button>@endif</div>
        </section>
    @else
        @php
            $closed = $selected['account_status'] === 'closed';
            $paymentReady = ($payment['status'] ?? '') === 'ready';
            $linkActive = $paymentLink && $paymentLink->status() === 'active';
        @endphp
        @if($closed)<div class="co-notice">This family account is closed. Its onboarding history is available below.</div>@endif
        <div class="co-progress" aria-label="Family milestones">
            @foreach([
                [$account !== null, 'Account', $account ? ($closed ? 'Closed account' : 'Created') : 'Not created'],
                [$paymentReady, 'Payment method', $paymentReady ? 'Saved securely' : (($payment['status'] ?? '') === 'unknown' ? 'Not checked' : 'Not added')],
                [$selected['request_posted'], 'Care request', $selected['request_posted'] ? 'Published' : 'Not posted yet'],
                [$selected['hired'], 'First hire', $selected['hired'] ? 'Confirmed' : 'Not hired yet'],
            ] as $step => [$done, $label, $description])
                <div class="co-progress__step {{ $done ? 'is-complete' : '' }}"><span class="co-progress__number" aria-hidden="true">{{ $done ? '✓' : $step + 1 }}</span><div><strong>{{ $label }}</strong><span>{{ $description }}</span></div></div>
            @endforeach
        </div>
        <div class="co-detail-grid">
            <div class="co-detail-main">
                @if(! $account)
                    <section class="co-panel co-panel-body"><p class="co-eyebrow">NEXT STEP</p><h2>Give this family a head start</h2><p class="co-muted">Prepare their account before the visit. Then share a secure link so they can add a payment method without signing in.</p><button class="co-button co-button--primary" type="button" wire:click="startCreate">Create family account</button></section>
                @else
                    <section class="co-panel co-payment" @if($linkActive) wire:poll.10s.visible @endif aria-labelledby="co-payment-title">
                        <div class="co-section-heading"><div><p class="co-eyebrow">READY FOR THE VISIT</p><h2 id="co-payment-title">Payment setup</h2></div><x-customer-onboarding-status :state="$paymentReady ? 'complete' : 'pending'" :label="$paymentReady ? 'Payment method ready' : (($payment['status'] ?? '') === 'unknown' ? 'Not checked' : 'Awaiting payment method')" /></div>
                        @if($paymentReady)
                            <div class="co-saved-card"><span class="co-card-icon" aria-hidden="true">▤</span><div><strong>{{ ucfirst(data_get($payment, 'card.brand', 'Payment method')) }}@if(data_get($payment, 'card.last4')) ending {{ data_get($payment, 'card.last4') }}@endif</strong><span>Saved to this family account for future care.</span></div><span class="co-check" aria-hidden="true">✓</span></div>
                        @else
                            <p class="co-muted">The family enters their card securely with Stripe. No LoLo login is needed, and saving a card does not collect a payment.</p>
                        @endif
                        @if($paymentLinkUrl && ! $closed)
                            <div class="co-share" x-data="customerPaymentLink(@js($paymentLinkUrl))" wire:key="payment-link-{{ $paymentLink->id }}">
                                <label class="co-field" for="co-payment-link">Payment setup link<input id="co-payment-link" x-ref="linkInput" type="text" value="{{ $paymentLinkUrl }}" readonly spellcheck="false" x-on:click="$el.select()"></label>
                                <div class="co-actions"><button class="co-button co-button--primary" type="button" x-on:click="copy()" x-text="copied ? 'Copied!' : 'Copy link'">Copy link</button><button class="co-button" type="button" x-on:click="toggleQr()" x-bind:aria-expanded="showQr" x-text="showQr ? 'Hide QR code' : 'Show QR code'">Show QR code</button><button class="co-text-button co-text-button--danger" type="button" wire:click="revokePaymentLink" wire:confirm="Revoke this payment link? The family will need a new link to continue." wire:loading.attr="disabled">Revoke link</button></div>
                                <p class="co-small">Expires {{ $paymentLink->expires_at->format('M j, Y \a\t g:i A T') }} · Closes after successful setup.</p>
                                <div class="co-qr" x-show="showQr" x-cloak wire:ignore><canvas x-ref="qrCanvas" role="img" aria-label="Scan to open this family's payment setup link"></canvas><p>Scan with the family’s phone camera</p></div>
                                <p class="co-field-error" x-show="qrError" x-text="qrError" x-cloak role="status"></p>
                                <span class="sr-only" aria-live="polite" x-text="copied ? 'Payment link copied to clipboard' : ''"></span>
                            </div>
                        @elseif(! $closed)
                            @if($paymentLink && in_array($paymentLink->status(), ['expired', 'revoked']))<p class="co-small">The previous link {{ $paymentLink->status() === 'expired' ? 'has expired' : 'was revoked' }}. Generate a fresh link when the family is ready.</p>@endif
                            <button class="co-button {{ $paymentReady ? '' : 'co-button--primary' }}" type="button" wire:click="generatePaymentLink" wire:loading.attr="disabled" wire:target="generatePaymentLink"><span wire:loading.remove wire:target="generatePaymentLink">{{ $paymentReady ? 'Create replacement card link' : 'Generate payment link' }}</span><span wire:loading wire:target="generatePaymentLink">Preparing link…</span></button>
                        @endif
                        <div class="co-payment-footer"><p class="co-small">@if($payment['checked_at']) Last checked {{ $payment['checked_at']->diffForHumans() }} @else Payment status has not been checked yet. @endif</p>@unless($closed)<button class="co-text-button" type="button" wire:click="refreshPaymentStatus" wire:loading.attr="disabled" wire:target="refreshPaymentStatus"><span wire:loading.remove wire:target="refreshPaymentStatus">Refresh payment status</span><span wire:loading wire:target="refreshPaymentStatus">Checking…</span></button>@endunless</div>
                    </section>
                @endif
                <section class="co-panel co-panel-body">
                    <div class="co-section-heading"><h2>Moving toward care</h2></div>
                    <div class="co-care-row"><span class="co-step-dot {{ $selected['request_posted'] ? 'is-complete' : '' }}" aria-hidden="true">{{ $selected['request_posted'] ? '✓' : '3' }}</span><div><h3>Care request</h3><p class="co-muted">{{ $selected['request_posted'] ? 'A care request has been published.' : 'Help the family describe their care needs and publish a request.' }}</p></div>@if($selected['latest_request_id'])<a class="co-text-button" href="{{ route('admin.requests.show', $selected['latest_request_id']) }}" wire:navigate>View request ↗</a>@endif</div>
                    <div class="co-care-row"><span class="co-step-dot {{ $selected['hired'] ? 'is-complete' : '' }}" aria-hidden="true">{{ $selected['hired'] ? '✓' : '4' }}</span><div><h3>First caregiver hired</h3><p class="co-muted">{{ $selected['hired'] ? 'First hire confirmed'.($selected['first_hire_at'] ? ' on '.\Illuminate\Support\Carbon::parse($selected['first_hire_at'])->format('M j, Y').'.' : '.') : 'Once a hire is confirmed, this milestone will update automatically.' }}</p></div></div>
                </section>
                <section class="co-panel co-panel-body">
                    <h2>Team notes & activity</h2>
                    <form wire:submit="addNote" class="co-note-form"><label class="sr-only" for="co-team-note">Internal note</label><textarea id="co-team-note" wire:model="note" rows="3" maxlength="2000" placeholder="What should the team know before the next conversation?"></textarea>@error('note')<p class="co-field-error">{{ $message }}</p>@enderror<div class="co-actions co-actions--end"><button type="submit" class="co-button" wire:loading.attr="disabled" wire:target="addNote">Add note</button></div></form>
                    <ol class="co-timeline">
                        @forelse($activity as $entry)
                            <li><span class="co-timeline__dot" aria-hidden="true"></span><div><strong>{{ $entry['title'] }}</strong>@if($entry['body'])<p class="co-activity-body">{{ $entry['body'] }}</p>@endif<p class="co-small">{{ $entry['at']?->format('M j, Y · g:i A T') }}@if($entry['actor']) · {{ $entry['actor'] }}@endif</p></div></li>
                        @empty<li><span class="co-timeline__dot" aria-hidden="true"></span><p class="co-muted">Account preparation, payment setup, and team notes will appear here.</p></li>@endforelse
                    </ol>
                </section>
            </div>
            <aside class="co-detail-aside">
                <section class="co-panel co-panel-body"><h2>Family contact</h2><dl class="co-contact"><div><dt>Main contact</dt><dd>{{ $selected['name'] }}</dd></div><div><dt>Email</dt><dd>@if($selected['email'])<a href="mailto:{{ $selected['email'] }}">{{ $selected['email'] }}</a>@else Not provided @endif</dd></div><div><dt>Phone</dt><dd>@if($selected['phone'])<a href="tel:{{ preg_replace('/[^+0-9]/', '', $selected['phone']) }}">{{ $selected['phone'] }}</a>@else Not provided @endif</dd></div>@if($lead?->assignedAdmin)<div><dt>Team contact</dt><dd>{{ $lead->assignedAdmin->name }}</dd></div>@endif</dl></section>
                @if($account && ! $closed)
                    <section class="co-panel co-panel-body"><p class="co-eyebrow">SEPARATE FROM PAYMENT SETUP</p><h2>Account access</h2><p class="co-muted">Send a secure email so the family can choose a password and sign in when they’re ready.</p><p class="co-small">{{ $account->owner?->email_verified_at ? 'Email address verified.' : 'Email address not yet verified.' }}</p><button type="button" class="co-button" wire:click="sendAccessEmail" wire:confirm="Send an account access email to {{ $selected['email'] }}?" wire:loading.attr="disabled" wire:target="sendAccessEmail">Send account access email</button></section>
                @endif
                <section class="co-panel co-panel-body"><h2>Care details & visit</h2>
                    @if($account?->onboarding)
                        @php($intake = $account->onboarding)
                        <p class="co-muted">{{ $intake->status === 'completed' ? 'Care details submitted.' : ($intake->status === 'exempted' ? 'Care intake is not required for this family.' : 'Care intake is still in progress.') }}</p>
                        @if($visit = $intake->welcomeVisit)<div class="co-visit"><strong>{{ $visit->confirmed_start_at ? $visit->confirmed_start_at->timezone($visit->timezone)->format('M j · g:i A') : $visit->preferred_date->format('M j').' · '.ucfirst($visit->preferred_range) }}</strong><span>{{ $visit->timezone }}</span><span>{{ ucfirst(str_replace('_', ' ', $visit->status)) }}</span></div>@endif
                        <a class="co-text-button" href="{{ route('admin.family-onboarding.show', $intake) }}" wire:navigate>Open care intake & visit ↗</a>
                    @else<p class="co-muted">Care intake and welcome visit details will appear here when available.</p>@endif
                    @if($lead?->next_follow_up_at)<p class="co-small">Next follow-up: {{ $lead->next_follow_up_at->format('M j, Y · g:i A T') }}</p>@endif
                </section>
            </aside>
        </div>
    @endif
</div>
