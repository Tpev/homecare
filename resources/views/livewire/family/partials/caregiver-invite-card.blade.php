@php
    $photoUrl = ! empty($caregiver['profile_photo_path'])
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($caregiver['profile_photo_path'])
        : null;
    $statusTone = match ($caregiver['relationship_state']) {
        'available' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'pending', 'accepted', 'replied', 'hired' => 'border-blue-200 bg-blue-50 text-blue-800',
        'declined', 'expired', 'cancelled' => 'border-amber-200 bg-amber-50 text-amber-900',
        default => 'border-slate-200 bg-slate-50 text-slate-700',
    };
@endphp

<article class="rounded-2xl border border-[#DED6CA] bg-white p-4 shadow-sm" wire:key="invite-caregiver-card-{{ $caregiver['user_id'] }}-{{ $caregiver['relationship_state'] }}">
    <div class="flex min-w-0 items-start gap-3">
        @if ($photoUrl)
            <img src="{{ $photoUrl }}" alt="" class="h-14 w-14 shrink-0 rounded-full border border-[#DED6CA] object-cover">
        @else
            <div aria-hidden="true" class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border border-[#BDD4F7] bg-[#EAF6F6] text-base font-semibold text-[#0F3D3E]">
                {{ $caregiver['initials'] }}
            </div>
        @endif

        <div class="min-w-0 flex-1">
            <h4 class="break-words font-display text-lg font-semibold leading-6 text-[#17313F]">{{ $caregiver['name'] }}</h4>
            <p class="mt-0.5 text-sm text-[#607080]">
                {{ collect([$caregiver['city'], $caregiver['state']])->filter()->implode(', ') ?: 'Location not listed' }}
            </p>
            <p class="mt-1 text-sm font-medium text-[#324457]">{{ $caregiver['availability'] }}</p>
        </div>
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-[#4B5B6B]">
        @if ($caregiver['identity_verified'])
            <span class="rounded-full bg-[#E8F0FF] px-2.5 py-1 font-medium text-[#315B98]">Identity verified</span>
        @endif
        @if ($caregiver['background_check'])
            <span class="rounded-full bg-emerald-100 px-2.5 py-1 font-medium text-emerald-800">Background check</span>
        @endif
        @if ($caregiver['top_caregiver'])
            <span class="rounded-full bg-amber-100 px-2.5 py-1 font-medium text-amber-900">Top caregiver</span>
        @endif
        @if ($caregiver['reviews_count'] > 0)
            <span aria-label="{{ number_format($caregiver['average_rating'], 1) }} stars from {{ $caregiver['reviews_count'] }} reviews">
                {{ number_format($caregiver['average_rating'], 1) }} stars · {{ $caregiver['reviews_count'] }} review{{ $caregiver['reviews_count'] === 1 ? '' : 's' }}
            </span>
        @else
            <span>No reviews yet</span>
        @endif
    </div>

    <div class="mt-3 rounded-xl border px-3 py-2 text-sm {{ $statusTone }}">
        <p class="font-semibold">{{ $caregiver['status_label'] }}</p>
        <p class="mt-0.5 text-xs leading-5">{{ $caregiver['status_detail'] }}</p>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
        <a
            href="{{ $caregiver['profile_url'] }}"
            wire:navigate
            class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#B7ADA0] bg-white px-4 py-2 text-center text-sm font-semibold text-[#0F3D3E] transition hover:bg-[#F5F1EB] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF] focus:ring-offset-2"
        >
            View profile
        </a>

        @if ($caregiver['can_invite'])
            <button
                type="button"
                wire:click="beginCaregiverInvitation({{ $caregiver['user_id'] }})"
                class="min-h-11 rounded-xl bg-[#0F3D3E] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#17313F] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF] focus:ring-offset-2"
            >
                Invite {{ $caregiver['first_name'] }}
            </button>
        @elseif ($caregiver['can_reinvite'])
            <button
                type="button"
                wire:click="beginCaregiverInvitation({{ $caregiver['user_id'] }}, true)"
                class="min-h-11 rounded-xl bg-[#0F3D3E] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#17313F] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF] focus:ring-offset-2"
            >
                Invite {{ $caregiver['first_name'] }} again
            </button>
        @elseif ($caregiver['reply_url'])
            <a
                href="{{ $caregiver['reply_url'] }}"
                wire:navigate
                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#0F3D3E] px-4 py-2 text-center text-sm font-semibold text-white transition hover:bg-[#17313F] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF] focus:ring-offset-2"
            >
                View reply
            </a>
        @else
            <div class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#DED6CA] bg-[#F5F1EB] px-4 py-2 text-center text-sm font-semibold text-[#607080]">
                {{ $caregiver['status_label'] }}
            </div>
        @endif
    </div>
</article>
