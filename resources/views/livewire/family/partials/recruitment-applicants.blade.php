<section class="hc-recruit-browser" aria-labelledby="recruit-applicants-title" data-ai-target="family.request.applicants">
    <div class="hc-recruit-section-heading">
        <div>
            <h2 id="recruit-applicants-title">{{ $booking ? 'Selected caregiver' : 'Review applicants' }}</h2>
            <p class="hc-recruit-caption">{{ $booking ? 'Your selected caregiver and the full application history for this request.' : 'Compare experience, review application notes, and choose who feels right.' }}</p>
        </div>
        <button type="button" wire:click="setCaregiverView('{{ $requestItem->status === \App\Models\CareRequest::STATUS_OPEN ? 'search' : 'invited' }}')" class="hc-care-text-link">{{ $requestItem->status === \App\Models\CareRequest::STATUS_OPEN ? 'Find more caregivers' : 'Invitation history' }}</button>
    </div>
    <div class="hc-recruit-review-tools">
        <nav class="hc-recruit-subnav" aria-label="Application lists">
            <button type="button" wire:click="setApplicantView('all')" @if($applicationStatus === 'all') aria-current="page" @endif>All <span>{{ $requestItem->applications->count() }}</span></button>
            <button type="button" wire:click="setApplicantView('shortlisted')" @if($applicationStatus === 'shortlisted') aria-current="page" @endif>Shortlisted <span>{{ $requestItem->applications->where('status', 'shortlisted')->count() }}</span></button>
            <button type="button" wire:click="setApplicantView('past')" @if($applicationStatus === 'past') aria-current="page" @endif>Past <span>{{ $requestItem->applications->whereIn('status', ['rejected', 'withdrawn', 'not_selected'])->count() }}</span></button>
        </nav>
        <details class="hc-recruit-filters">
            <summary>Filter or sort caregivers</summary>
            <div class="hc-recruit-filter-panel">
    @if($applicationStatus === 'shortlisted')
        <p class="hc-recruit-caption">Shortlisted for this request. Accepting an invitation or starting a chat can also add someone here. Your saved profiles are in Find caregivers.</p>
    @endif
                <x-native-select-field label="Status" wire:model.live="applicationStatus" :options="array_merge($applicationStatusOptions, [['label' => 'Past applications', 'value' => 'past']])" />
                <x-native-select-field label="Sort" wire:model.live="applicationSort" :options="[
                    ['label' => 'Latest first', 'value' => 'latest'], ['label' => 'Oldest first', 'value' => 'oldest'],
                ]" />
                <x-caregiver-certification-filter :options="$certificationOptions" :selected="$applicationCertificationTypes" :verification="$applicationCertificationVerification"
                    types-model="applicationCertificationTypes" verification-model="applicationCertificationVerification"
                    clear-method="clearApplicationCertificationFilters" remove-method="removeApplicationCertificationFilter"
                    include-reported-method="includeReportedApplicationCertifications" id-prefix="request-applicant-certifications" />
            </div>
        </details>
    </div>
    <p class="sr-only" role="status" aria-live="polite">{{ $visibleApplicationCount }} caregivers match the applicant filters</p>
    <div id="caregiver-comparison-list" class="hc-candidate-list">
        @forelse($visibleApplications as $application)
            @php
                $caregiverProfile = $application->caregiver->caregiverProfile;
                $photoUrl = $caregiverProfile?->profile_photo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($caregiverProfile->profile_photo_path) : null;
                $profileHref = $caregiverProfile?->slug ? route('caregivers.show', ['slug' => $caregiverProfile->slug, 'careRequest' => $requestItem->id]) : null;
                $firstName = \Illuminate\Support\Str::of($application->caregiver->name)->before(' ')->trim();
                $yearsExperience = (int) ($caregiverProfile?->years_experience ?? 0);
                $reviewsCount = (int) ($caregiverProfile?->reviews_count ?? 0);
                $isActiveApplication = in_array($application->status, ['applied', 'shortlisted'], true);
                $applicationStatusLabel = match((string) $application->status) {
                    'applied' => 'Interested', 'shortlisted' => 'Shortlisted', 'hired' => 'Hired',
                    'rejected' => 'Declined', 'not_selected' => 'Not selected', 'withdrawn' => 'Withdrawn',
                    default => ucfirst(str_replace('_', ' ', $application->status)),
                };
                $applicationCertificationSummary = $caregiverProfile ? $caregiverProfile->publicCertificationSummary($applicationCertificationCriteria, 3) : ['tags' => [], 'hidden_count' => 0, 'total' => 0];
            @endphp
            <article class="hc-candidate" data-testid="applicant-row" data-application-id="{{ $application->id }}" wire:key="recruit-application-{{ $application->id }}">
                <div class="hc-candidate-avatar" aria-hidden="true">
                    @if($photoUrl)<img src="{{ $photoUrl }}" alt="">@else{{ \Illuminate\Support\Str::of($application->caregiver->name)->trim()->explode(' ')->take(2)->map(fn($part) => \Illuminate\Support\Str::substr($part, 0, 1))->implode('') }}@endif
                </div>
                <div class="hc-candidate-identity">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="hc-candidate-name">@if($profileHref)<a href="{{ $profileHref }}" wire:navigate>{{ $application->caregiver->name }}</a>@else{{ $application->caregiver->name }}@endif</h3>
                        <span class="hc-recruit-chip">{{ $applicationStatusLabel }}</span>
                    </div>
                    <p class="hc-candidate-meta">{{ collect([$application->caregiver->city, $application->caregiver->state])->filter()->join(', ') ?: 'Location not listed' }}</p>
                    <div class="hc-candidate-metrics">
                        <span>{{ $yearsExperience }} {{ \Illuminate\Support\Str::plural('year', $yearsExperience) }} experience</span>
                        @if($reviewsCount > 0 && $caregiverProfile?->average_rating)
                            <span>★ {{ number_format((float) $caregiverProfile->average_rating, 1) }} · {{ $reviewsCount }} {{ \Illuminate\Support\Str::plural('review', $reviewsCount) }}</span>
                        @else <span>No reviews yet</span> @endif
                        @if($caregiverProfile?->reliability_score)<span>Reliability {{ number_format((float) $caregiverProfile->reliability_score, 0) }}%</span>@endif
                    </div>
                    <div class="hc-candidate-trust">
                        @if($caregiverProfile?->hasIdentityVerifiedBadge())<span>Identity verified</span>@endif
                        @if($caregiverProfile?->hasBackgroundCheckBadge())<span>Background check</span>@endif
                        @if($caregiverProfile?->hasTopCaregiverBadge())<span>Top caregiver</span>@endif
                    </div>
                </div>
                <div class="hc-candidate-side">
                    <div class="hc-candidate-actions">
                        @if($profileHref)<a href="{{ $profileHref }}" wire:navigate class="hc-secondary-button">View profile</a>@endif
                        @if($requestItem->status === \App\Models\CareRequest::STATUS_OPEN)
                            <button type="button" data-testid="hire-caregiver" wire:click="reviewHire({{ $application->id }})" @disabled(! $hirePayment['ready'])
                                @if(! $hirePayment['ready']) aria-describedby="hire-payment-{{ $application->id }}" @endif
                                class="{{ $hirePayment['ready'] ? 'hc-primary-button' : 'hc-secondary-button !border-slate-200 !bg-slate-100 !text-slate-400 !shadow-none cursor-not-allowed' }}">{{ $isActiveApplication ? 'Hire '.$firstName : 'Reconsider & hire' }}</button>
                        @endif
                    </div>
                    @if($requestItem->status === \App\Models\CareRequest::STATUS_OPEN && ! $hirePayment['ready'])
                        <x-family-hire-payment-prompt :care-request="$requestItem" :application="$application" :unavailable="$hirePayment['unavailable']" :id="'hire-payment-'.$application->id" />
                        <button type="button" wire:click="reviewHire({{ $application->id }})" class="hc-care-text-link">Review care &amp; price</button>
                    @endif
                    @if($requestItem->status === \App\Models\CareRequest::STATUS_OPEN && in_array($application->status, ['applied', 'shortlisted', 'hired'], true))
                        <button type="button" wire:click="startConversation({{ $application->id }})" class="hc-care-text-link">{{ $application->conversation ? 'Open chat' : ($application->status === 'applied' ? 'Shortlist & chat' : 'Start chat') }}</button>
                    @elseif($application->conversation)
                        <a href="{{ route('messages.show', $application->conversation->id) }}" wire:navigate class="hc-care-text-link">Open chat</a>
                    @endif
                </div>
                <div class="hc-candidate-detail">
                    <x-caregiver-certification-tags :summary="$applicationCertificationSummary" :show-label="false" compact />
                    @if($application->cover_note)
                        <div class="hc-candidate-note"><p class="hc-candidate-meta">Application note</p><p class="whitespace-pre-line">{{ $application->cover_note }}</p></div>
                    @endif
                    <div class="hc-candidate-footer">
                        @if($caregiverProfile)
                            <details class="hc-profile-disclosure">
                                <summary>Profile details</summary>
                                <div class="hc-profile-content">
                                    <p>{{ $caregiverProfile->is_accepting_new_clients ? 'Accepting clients' : 'Limited availability' }}</p>
                                    @if($caregiverProfile->bio)<p class="whitespace-pre-line">{{ $caregiverProfile->bio }}</p>@endif
                                    @if($caregiverProfile->skills->isNotEmpty())<p><strong>Skills:</strong> {{ $caregiverProfile->skills->pluck('name')->join(', ') }}</p>@endif
                                    @if($caregiverProfile->languages->isNotEmpty())<p><strong>Languages:</strong> {{ $caregiverProfile->languages->pluck('name')->join(', ') }}</p>@endif
                                    <p>Application received {{ $application->created_at->format('M j, Y, g:i A') }}</p>
                                </div>
                            </details>
                        @endif
                        @if($requestItem->status === \App\Models\CareRequest::STATUS_OPEN && ! in_array($application->status, ['hired', 'withdrawn'], true))
                            <details class="hc-candidate-options">
                                <summary>More options for {{ $firstName }}</summary>
                                <div class="hc-candidate-actions">
                                    @if($isActiveApplication)<button type="button" wire:click="shortlist({{ $application->id }})" class="hc-secondary-button">Save for later</button>@endif
                                    <button type="button" wire:click="reject({{ $application->id }})" class="hc-secondary-button">Not this caregiver</button>
                                </div>
                            </details>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="hc-recruit-empty">
                <h3>{{ $requestItem->applications->isEmpty() ? 'No applications yet' : 'No matching applications' }}</h3>
                <p>{{ $requestItem->applications->isEmpty() ? 'Invite caregivers to take a look. Their applications will appear here when they respond.' : 'Try another list or remove a filter to see more caregivers.' }}</p>
                <div class="hc-candidate-actions">
                    @if($applicationStatus !== 'all')<button type="button" wire:click="setApplicantView('all')" class="hc-secondary-button">View all applications</button>@endif
                    @if($applicationCertificationCriteria->hasSelections())<button type="button" wire:click="clearApplicationCertificationFilters" class="hc-secondary-button">Clear certification filters</button>@endif
                    @if($requestItem->status === \App\Models\CareRequest::STATUS_OPEN)<button type="button" wire:click="setCaregiverView('search')" class="hc-primary-button">Find caregivers</button>@endif
                </div>
            </div>
        @endforelse
    </div>
</section>
