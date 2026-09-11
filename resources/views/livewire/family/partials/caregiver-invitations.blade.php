@if ($requestItem->status === \App\Models\CareRequest::STATUS_OPEN)
    <section class="mb-5 rounded-2xl border border-[#8FB7AB] bg-[#F2F8F4] p-4 sm:p-5" aria-labelledby="invite-known-caregiver-heading">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="min-w-0">
                <p class="hc-brand-kicker">Looking for a specific person?</p>
                <h3 id="invite-known-caregiver-heading" class="mt-1 font-display text-xl font-semibold text-[#23483F]">Invite someone you know</h3>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-[#485E53]">Search by name and invite them without leaving this request or choosing the request again.</p>
            </div>
            <button
                x-ref="inviteCaregiverTrigger"
                id="invite-known-caregiver-button"
                type="button"
                wire:click="openCaregiverInvitePanel"
                class="inline-flex min-h-12 w-full shrink-0 items-center justify-center rounded-xl bg-[#23483F] px-5 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-[#23483F] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF] focus:ring-offset-2 md:w-auto"
            >
                Search and invite
            </button>
        </div>
    </section>
@endif

@php
    $currentInvitations = $requestItem->invitations
        ->filter(fn ($invitation) => in_array($invitation->status, [
            \App\Models\CareRequestInvitation::STATUS_PENDING,
            \App\Models\CareRequestInvitation::STATUS_ACCEPTED,
        ], true) && ! $invitation->isExpired())
        ->sortByDesc('created_at');
    $historicalInvitations = $requestItem->invitations
        ->reject(fn ($invitation) => $currentInvitations->contains('id', $invitation->id))
        ->sortByDesc('created_at');
@endphp

<section class="mb-5" aria-labelledby="people-invited-heading">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <h3 id="people-invited-heading" class="font-display text-lg font-semibold text-[#23483F]">People you invited</h3>
            <p class="mt-1 text-sm text-[#485E53]">Invitations are separate from caregivers who replied.</p>
        </div>
        <span class="text-sm text-[#485E53]">{{ $requestItem->invitations->count() }} total</span>
    </div>

    @if ($currentInvitations->isNotEmpty())
        <ul class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2" role="list">
            @foreach ($currentInvitations as $invitation)
                @php
                    $invitationLabel = $invitation->status === \App\Models\CareRequestInvitation::STATUS_ACCEPTED
                        ? 'Accepted invitation'
                        : 'Invitation sent';
                @endphp
                <li class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <p class="break-words font-semibold text-[#23483F]">{{ $invitation->caregiver?->name ?: 'Caregiver' }}</p>
                        <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-blue-800">{{ $invitationLabel }}</span>
                    </div>
                    <p class="mt-1 text-sm text-[#485E53]">
                        {{ $invitation->status === \App\Models\CareRequestInvitation::STATUS_PENDING ? 'Sent '.$invitation->created_at?->diffForHumans() : 'Replied '.$invitation->responded_at?->diffForHumans() }}
                    </p>
                </li>
            @endforeach
        </ul>
    @else
        <div class="mt-3 rounded-xl border border-dashed border-[#D6CCBE] bg-[#FFFCF8] px-4 py-4 text-sm text-[#485E53]">
            No active invitations yet.
        </div>
    @endif

    @if ($historicalInvitations->isNotEmpty())
        <details class="mt-3 rounded-xl border border-[#E4DDD3] bg-[#FFFCF8] px-4 py-3">
            <summary class="min-h-11 cursor-pointer list-none py-2 text-sm font-semibold text-[#23483F] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF] [&::-webkit-details-marker]:hidden">
                Past invitations ({{ $historicalInvitations->count() }})
            </summary>
            <ul class="space-y-2 border-t border-[#E4DDD3] pt-3" role="list">
                @foreach ($historicalInvitations as $invitation)
                    @php
                        $historicalStatus = $invitation->isExpired()
                            ? \App\Models\CareRequestInvitation::STATUS_EXPIRED
                            : $invitation->status;
                    @endphp
                    <li class="flex flex-col gap-1 rounded-lg bg-white px-3 py-2 text-sm sm:flex-row sm:items-center sm:justify-between">
                        <span class="break-words font-medium text-[#23483F]">{{ $invitation->caregiver?->name ?: 'Caregiver' }}</span>
                        <span class="text-[#485E53]">{{ ucfirst($historicalStatus) }} · {{ $invitation->updated_at?->format('M j, Y') }}</span>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</section>
