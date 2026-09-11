@php
    $photoUrl = ! empty($caregiver['profile_photo_path']) ? \Illuminate\Support\Facades\Storage::disk('public')->url($caregiver['profile_photo_path']) : null;
    $inviteAllowedHere = $requestItem->status === \App\Models\CareRequest::STATUS_OPEN && ! $requestDatePassed;
    $availabilityLabel = str_replace(['Matches the requested time', 'Matches the recurring schedule', 'Matches '], ['Overlaps your requested time', 'Overlaps your requested days', 'Overlaps '], $caregiver['availability']);
    $matchingEvidence = ($suggestedCaregivers ?? collect())->firstWhere('user_id', $caregiver['user_id']);
@endphp
<article class="hc-candidate hc-discovery-candidate" data-testid="discovery-row" wire:key="invite-caregiver-card-{{ $caregiver['user_id'] }}-{{ $caregiver['relationship_state'] }}">
    <div class="hc-candidate-avatar" aria-hidden="true">
        @if($photoUrl)<img src="{{ $photoUrl }}" alt="">@else{{ $caregiver['initials'] }}@endif
    </div>
    <div class="hc-candidate-identity">
        <h3 class="hc-candidate-name"><a href="{{ $caregiver['profile_url'] }}" wire:navigate>{{ $caregiver['name'] }}</a></h3>
        <p class="hc-candidate-meta">{{ collect([$caregiver['city'], $caregiver['state']])->filter()->implode(', ') ?: 'Location not listed' }}</p>
        <div class="hc-candidate-metrics">
            @if($caregiver['reviews_count'] > 0)
                <span>★ {{ number_format($caregiver['average_rating'], 1) }} · {{ $caregiver['reviews_count'] }} {{ \Illuminate\Support\Str::plural('review', $caregiver['reviews_count']) }}</span>
            @else <span>No reviews yet</span> @endif
        </div>
        <div class="hc-candidate-trust">
            @if($caregiver['identity_verified'])<span>Identity verified</span>@endif
            @if($caregiver['background_check'])<span>Background check</span>@endif
            @if($caregiver['top_caregiver'])<span>Top caregiver</span>@endif
        </div>
    </div>
    <div class="hc-candidate-side">
        <div class="hc-candidate-actions">
            <a href="{{ $caregiver['profile_url'] }}" wire:navigate class="hc-secondary-button">View profile</a>
            @if($caregiver['can_invite'] && $inviteAllowedHere)
                <button type="button" data-invite-trigger wire:click="beginCaregiverInvitation({{ $caregiver['user_id'] }})" class="hc-primary-button">Invite {{ $caregiver['first_name'] }}</button>
            @elseif($caregiver['can_reinvite'] && $inviteAllowedHere)
                <button type="button" data-invite-trigger wire:click="beginCaregiverInvitation({{ $caregiver['user_id'] }}, true)" class="hc-primary-button">Invite again</button>
            @elseif($caregiver['reply_url'])
                <a href="{{ $caregiver['reply_url'] }}" wire:navigate class="hc-primary-button">View reply</a>
            @endif
        </div>
    </div>
    <div class="hc-candidate-detail">
        <p class="hc-candidate-meta">{{ $availabilityLabel }}</p>
        <x-caregiver-certification-tags :summary="$caregiver['certification_summary']" :show-label="false" compact />
        @if(! empty($caregiver['care_experience_tags']))
            <div class="hc-candidate-trust" aria-label="Care experience">
                @foreach($caregiver['care_experience_tags'] as $tag)<span class="hc-recruit-chip">{{ $tag['label'] }}</span>@endforeach
            </div>
        @endif
        @if($matchingEvidence)
            <details class="hc-profile-disclosure">
                <summary>Why suggested</summary>
                <p>{{ $matchingEvidence['proximity'] }} · Match score {{ $matchingEvidence['score'] }}</p>
                <p>{{ implode(' · ', $matchingEvidence['reasons']) }}</p>
            </details>
        @endif
        <p class="hc-candidate-meta"><strong>{{ $caregiver['status_label'] }}</strong> · {{ $caregiver['status_detail'] }}</p>
    </div>
</article>
