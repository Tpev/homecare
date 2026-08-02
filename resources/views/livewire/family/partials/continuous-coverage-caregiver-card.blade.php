@php
    $person = $caregiver->user;
    $member = $rosterByCaregiver->get($caregiver->user_id);
    $photoUrl = $caregiver->profile_photo_path
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($caregiver->profile_photo_path)
        : null;
    $initials = \Illuminate\Support\Str::of((string) $person?->name)
        ->trim()
        ->explode(' ')
        ->filter()
        ->take(2)
        ->map(fn ($part) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) $part, 0, 1)))
        ->implode('');
    $firstName = trim((string) \Illuminate\Support\Str::of((string) $person?->name)->before(' ')) ?: 'caregiver';
    $canInvite = ! $member || $member->status === \App\Models\ContinuousCoverageRosterMember::STATUS_REMOVED;
    $canInvite = $canInvite && (bool) $caregiver->is_accepting_new_clients;
    [$statusLabel, $statusDetail, $statusTone] = match ($member?->status) {
        \App\Models\ContinuousCoverageRosterMember::STATUS_APPLIED => [
            'Application waiting for review',
            'Review this caregiver in the applications section before adding them.',
            'border-violet-200 bg-violet-50 text-violet-900',
        ],
        \App\Models\ContinuousCoverageRosterMember::STATUS_FAMILY_APPROVED => [
            'Care-team invitation sent',
            'Waiting for the caregiver to accept your family’s invitation.',
            'border-blue-200 bg-blue-50 text-blue-900',
        ],
        \App\Models\ContinuousCoverageRosterMember::STATUS_ACTIVE => [
            'Already on this care team',
            'This caregiver can receive eligible coverage offers.',
            'border-emerald-200 bg-emerald-50 text-emerald-900',
        ],
        \App\Models\ContinuousCoverageRosterMember::STATUS_PAUSED => [
            'Care-team member paused',
            'Resume this caregiver from their care-team card before sending new offers.',
            'border-amber-200 bg-amber-50 text-amber-900',
        ],
        \App\Models\ContinuousCoverageRosterMember::STATUS_REMOVED => [
            'Previously removed',
            $caregiver->is_accepting_new_clients
                ? 'You can invite this caregiver to rejoin the care team.'
                : 'This caregiver is not accepting new clients right now.',
            'border-amber-200 bg-amber-50 text-amber-900',
        ],
        default => $caregiver->is_accepting_new_clients
            ? ['Available to invite', 'Accepting new clients.', 'border-emerald-200 bg-emerald-50 text-emerald-900']
            : ['Not accepting new clients', 'You can view the profile, but an invitation is unavailable right now.', 'border-slate-200 bg-slate-50 text-slate-700'],
    };
    $showAction = $showAction ?? true;
@endphp

<article class="rounded-2xl border border-[#DED6CA] bg-white p-4 shadow-sm" wire:key="coverage-caregiver-card-{{ $caregiver->user_id }}-{{ $member?->status ?: 'available' }}-{{ $showAction ? 'action' : 'summary' }}">
    <div class="flex min-w-0 items-start gap-3">
        @if ($photoUrl)
            <img src="{{ $photoUrl }}" alt="" class="h-14 w-14 shrink-0 rounded-full border border-[#DED6CA] object-cover">
        @else
            <div aria-hidden="true" class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border border-[#BDD4F7] bg-[#EAF6F6] text-base font-semibold text-[#0F3D3E]">
                {{ $initials }}
            </div>
        @endif

        <div class="min-w-0 flex-1">
            <h4 class="break-words font-display text-lg font-semibold leading-6 text-[#17313F]">{{ $person?->name }}</h4>
            <p class="mt-0.5 text-sm text-[#607080]">
                {{ collect([$person?->city, $person?->state])->filter()->implode(', ') ?: 'Location not listed' }}
            </p>
            @if (! is_null($caregiver->years_experience))
                <p class="mt-1 text-sm font-medium text-[#324457]">{{ $caregiver->years_experience }} year{{ (int) $caregiver->years_experience === 1 ? '' : 's' }} of experience</p>
            @endif
        </div>
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-[#4B5B6B]">
        @if ($caregiver->hasIdentityVerifiedBadge())
            <span class="rounded-full bg-[#E8F0FF] px-2.5 py-1 font-medium text-[#315B98]">Identity verified</span>
        @endif
        @if ($caregiver->hasBackgroundCheckBadge())
            <span class="rounded-full bg-emerald-100 px-2.5 py-1 font-medium text-emerald-800">Background check</span>
        @endif
        @if ($caregiver->hasTopCaregiverBadge())
            <span class="rounded-full bg-amber-100 px-2.5 py-1 font-medium text-amber-900">Top caregiver</span>
        @endif
        @if ((int) $caregiver->reviews_count > 0)
            <span aria-label="{{ number_format((float) $caregiver->average_rating, 1) }} stars from {{ $caregiver->reviews_count }} reviews">
                {{ number_format((float) $caregiver->average_rating, 1) }} stars · {{ $caregiver->reviews_count }} review{{ (int) $caregiver->reviews_count === 1 ? '' : 's' }}
            </span>
        @else
            <span>No reviews yet</span>
        @endif
    </div>

    @if (filled($caregiver->bio))
        <p class="mt-3 text-sm leading-6 text-[#526474]">{{ \Illuminate\Support\Str::limit($caregiver->bio, 150) }}</p>
    @endif

    @if ($caregiver->skills->isNotEmpty())
        <div class="mt-3 flex flex-wrap gap-2" aria-label="Caregiver skills">
            @foreach ($caregiver->skills->take(3) as $skill)
                <span class="rounded-full border border-[#D8D0C5] bg-[#F7F2EA] px-2.5 py-1 text-xs font-medium text-[#4B5B6B]">{{ $skill->name }}</span>
            @endforeach
        </div>
    @endif

    <div class="mt-3 rounded-xl border px-3 py-2 text-sm {{ $statusTone }}">
        <p class="font-semibold">{{ $statusLabel }}</p>
        <p class="mt-0.5 text-xs leading-5">{{ $statusDetail }}</p>
    </div>

    @if ($showAction)
        <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
            @if ($caregiver->slug)
                <a
                    href="{{ route('caregivers.show', $caregiver->slug) }}"
                    wire:navigate
                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#B7ADA0] bg-white px-4 py-2 text-center text-sm font-semibold text-[#0F3D3E] transition hover:bg-[#F5F1EB] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF] focus:ring-offset-2"
                >
                    View profile
                </a>
            @else
                <div class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#DED6CA] bg-[#F5F1EB] px-4 py-2 text-center text-sm font-semibold text-[#607080]">Profile unavailable</div>
            @endif

            @if ($canInvite)
                <button
                    type="button"
                    wire:click="selectCaregiverForRoster({{ $caregiver->user_id }})"
                    class="min-h-11 rounded-xl bg-[#0F3D3E] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#17313F] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF] focus:ring-offset-2"
                >
                    {{ $member?->status === \App\Models\ContinuousCoverageRosterMember::STATUS_REMOVED ? 'Invite '.$firstName.' again' : 'Invite '.$firstName }}
                </button>
            @else
                <div class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#DED6CA] bg-[#F5F1EB] px-4 py-2 text-center text-sm font-semibold text-[#607080]">
                    {{ $statusLabel }}
                </div>
            @endif
        </div>
    @endif
</article>
