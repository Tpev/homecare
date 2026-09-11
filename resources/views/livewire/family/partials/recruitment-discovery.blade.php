<section class="hc-recruit-browser" aria-labelledby="find-caregivers-heading">
    <div class="hc-recruit-discovery-heading"><h2 id="find-caregivers-heading">Find caregivers</h2><p>Search profiles, send invitations and track replies.</p><p class="hc-recruit-caption">{{ $recruitmentSchedule }}</p></div>
    <nav class="hc-recruit-subnav" aria-label="Caregiver discovery">
        <button type="button" wire:click="setCaregiverView('search')" @if ($caregiverView === 'search') aria-current="page" @endif>Search</button>
        <button type="button" wire:click="setCaregiverView('invited')" @if ($caregiverView === 'invited') aria-current="page" @endif>Invited <span>{{ $requestItem->invitations->count() }}</span></button>
        <button type="button" wire:click="setCaregiverView('saved')" @if ($caregiverView === 'saved') aria-current="page" @endif>Saved profiles</button>
    </nav>
    @if ($requestDatePassed || $requestItem->status !== 'open')
        <div class="hc-recruit-notice" role="status">{{ $requestDatePassed ? 'This requested date has passed. Existing invitations remain here; choose a new date before inviting again.' : 'This request is no longer open. Your invitations and caregiver history remain available.' }}</div>
    @endif
    @if ($caregiverInviteFeedback)<div role="{{ $caregiverInviteFeedback['type'] === 'error' ? 'alert' : 'status' }}" class="hc-recruit-notice">{{ $caregiverInviteFeedback['message'] }}</div>@endif
    @if ($caregiverView === 'invited')
        <div class="hc-recruit-result-heading"><h3>People you invited</h3><p>Invitations and applications have separate statuses.</p></div>
        <div class="hc-candidate-list">
            @forelse ($requestItem->invitations->sortByDesc('created_at') as $invitation)
                @php
                    $inviteState = $invitation->isExpired() ? 'expired' : $invitation->status;
                    $inviteCard = $invitationCards[$invitation->caregiver_user_id] ?? null;
                @endphp
                <article class="hc-invited-row" data-testid="invitation-row">
                    <div class="hc-candidate-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::of($invitation->caregiver?->name ?: 'Caregiver')->explode(' ')->take(2)->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->implode('') }}</div>
                    <div><h3>{{ $invitation->caregiver?->name ?: 'Caregiver' }}</h3><p class="hc-recruit-caption">Sent {{ $invitation->created_at?->format('M j, Y · g:i A') }}@if ($invitation->responded_at) · Replied {{ $invitation->responded_at->format('M j, Y · g:i A') }}@endif</p><span class="hc-candidate-status">{{ match($inviteState) { 'pending' => 'Invitation sent', 'accepted' => 'Invitation accepted', default => ucfirst($inviteState) } }}</span></div>
                    <div class="hc-candidate-actions">@if ($inviteCard)
                        <a href="{{ $inviteCard['profile_url'] }}" wire:navigate class="hc-secondary-button">View profile</a>
                        @if ($inviteCard['reply_url'])<a href="{{ $inviteCard['reply_url'] }}" wire:navigate class="hc-primary-button">View reply</a>
                        @elseif ($inviteCard['can_reinvite'] && ! $requestDatePassed)<button type="button" data-invite-trigger wire:click="beginCaregiverInvitation({{ $inviteCard['user_id'] }}, true)" class="hc-primary-button">Invite again</button>@endif
                    @endif</div>
                    <details class="hc-invitation-message"><summary>Invitation details</summary>
                        @if ($invitation->message)<p><strong>Invitation message</strong></p><p>{{ $invitation->message }}</p>@endif
                        <p>Last updated {{ $invitation->updated_at?->format('M j, Y · g:i A') }}</p>
                        @if ($invitation->effectiveExpiresAt())<p>Expires {{ $invitation->effectiveExpiresAt()->format('M j, Y · g:i A') }}</p>@endif
                    </details>
                </article>
            @empty
                <div class="hc-recruit-empty"><span class="hc-recruit-empty-icon" aria-hidden="true">↗</span><h3>No invitations sent yet</h3><p>Search for a caregiver and review your message before sending. {{ $requestItem->is_private ? 'This private request is only shared by invitation.' : 'Caregivers can also apply without an invitation.' }}</p><button type="button" wire:click="setCaregiverView('search')" class="hc-primary-button">Find caregivers</button></div>
            @endforelse
        </div>
    @else
        <div class="hc-recruit-searchbar">
            @if ($caregiverView === 'search')
                <label class="hc-recruit-search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg><span class="sr-only">Search caregivers by name, city, or certification</span><input type="search" wire:model.live.debounce.350ms="caregiverSearch" placeholder="Name, city or certification" aria-describedby="discovery-search-hint"></label>
                @if (trim($caregiverSearch) !== '')<button type="button" wire:click="clearCaregiverSearch" class="hc-recruit-link">Clear</button>@endif
            @else
                <p class="hc-recruit-caption">Profiles saved while browsing caregivers. Your request shortlist is under Review applicants.</p>
            @endif
            <details class="hc-recruit-filter"><summary>Filters @if ($certificationCriteria->hasSelections())<span class="hc-recruit-filter-dot" aria-label="Active filters"></span>@endif</summary><div class="hc-recruit-filter-panel"><p id="discovery-search-hint" class="hc-recruit-caption">Search from 2 characters. Schedule matches indicate overlap, not a confirmed booking.</p><x-caregiver-certification-filter :options="$certificationOptions" :selected="$certificationTypes" :verification="$certificationVerification" id-prefix="recruitment-discovery-certifications" /></div></details>
        </div>
        <p class="sr-only" role="status" aria-live="polite"><span wire:loading wire:target="caregiverSearch,certificationTypes,certificationVerification">Searching caregivers</span><span wire:loading.remove wire:target="caregiverSearch,certificationTypes,certificationVerification">{{ $caregiverSearchResults->count() }} search results</span></p>
        <div class="hc-candidate-list" wire:loading.class="opacity-60" wire:target="caregiverSearch,certificationTypes,certificationVerification">
            @if ($caregiverView === 'saved')
                @forelse ($savedDiscoveryCaregivers as $caregiver)@include('livewire.family.partials.caregiver-invite-card')@empty<div class="hc-recruit-empty"><h3>No saved profiles match</h3><p>Saved profiles that match these filters will appear here. Find caregivers to explore your options.</p><button type="button" wire:click="setCaregiverView('search')" class="hc-secondary-button">Search caregivers</button></div>@endforelse
                @if ($savedDiscoveryLimitReached)<p class="hc-recruit-caption">Showing up to 12 saved profiles. Search by name to find another caregiver.</p>@endif
            @elseif (trim($caregiverSearch) === '')
                @forelse ($caregiverInitialSections as $section)
                    @if ($section['caregivers']->isNotEmpty())
                        <div class="hc-recruit-result-heading"><h3>{{ $section['title'] }}</h3><p>{{ match($section['key']) { 'recommended' => 'Based on location and overlapping schedules.', 'previous' => 'Caregivers previously booked by your family.', default => $section['description'] } }}</p></div>
                        @foreach ($section['caregivers'] as $caregiver)@include('livewire.family.partials.caregiver-invite-card')@endforeach
                    @endif
                @empty @endforelse
                @if (collect($caregiverInitialSections)->sum(fn ($section) => $section['caregivers']->count()) === 0)<div class="hc-recruit-empty"><h3>{{ $certificationCriteria->hasSelections() ? 'No caregivers match these filters' : 'Search for a caregiver' }}</h3><p>{{ $certificationCriteria->hasSelections() ? 'Try removing a certification filter to broaden the results.' : 'Search by name, city or certification to find someone for your request.' }}</p>@if ($certificationCriteria->hasSelections())<button type="button" wire:click="clearCertificationFilters" class="hc-secondary-button">Clear certifications</button>@endif</div>@endif
            @elseif (mb_strlen(trim($caregiverSearch)) < 2)
                <div class="hc-recruit-empty"><h3>Keep typing to search</h3><p>Enter at least 2 letters.</p></div>
            @else
                <div class="hc-recruit-result-heading"><h3>Search results</h3><p>{{ $caregiverSearchResults->count() }} matching caregiver{{ $caregiverSearchResults->count() === 1 ? '' : 's' }}{{ $caregiverSearchResults->count() === 12 ? ' · Showing the first 12 results' : '' }}</p></div>
                @forelse ($caregiverSearchResults as $caregiver)@include('livewire.family.partials.caregiver-invite-card')@empty<div class="hc-recruit-empty"><h3>No caregivers found</h3><p>Try another name or city{{ $certificationCriteria->hasSelections() ? ', or remove a certification filter' : '' }}.</p><button type="button" wire:click="clearCaregiverSearch" class="hc-secondary-button">Clear search</button></div>@endforelse
            @endif
        </div>
    @endif
</section>
