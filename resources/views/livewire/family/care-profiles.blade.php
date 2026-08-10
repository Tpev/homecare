<div class="hc-page py-8 sm:py-12">
    <div class="mx-auto max-w-4xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#B95745]">Care profiles</p>
                <h1 class="mt-2 font-display text-3xl font-semibold text-[#17313F]" tabindex="-1">People receiving care</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-[#5D6D67]">Tell caregivers a little about the person they may support. This is optional, and you choose what to share.</p>
            </div>
            <a href="{{ route('family.care-profiles.create') }}" wire:navigate class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[#17313F] px-5 font-semibold text-white shadow-sm hover:bg-[#23483F]">Add a person</a>
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status" aria-live="polite">{{ session('status') }}</div>
        @endif

        @if ($activeProfiles->isEmpty())
            <section class="rounded-3xl border border-[#DCCFBE] bg-white px-6 py-10 text-center shadow-sm">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[#F3E9DB] text-2xl" aria-hidden="true">&#9825;</div>
                <h2 class="mt-4 font-display text-2xl font-semibold text-[#17313F]">Help a caregiver get to know the person</h2>
                <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-[#607080]">A short care profile can explain communication, routine, comfort, and what makes care go well. You can skip any question and finish later.</p>
                <a href="{{ route('family.care-profiles.create') }}" wire:navigate class="mt-6 inline-flex min-h-12 items-center justify-center rounded-xl bg-[#17313F] px-6 font-semibold text-white">Create a care profile</a>
            </section>
        @else
            <section class="grid gap-4 sm:grid-cols-2" aria-label="Active care profiles">
                @foreach ($activeProfiles as $profile)
                    <article class="flex flex-col rounded-2xl border border-[#DCCFBE] bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="font-display text-xl font-semibold text-[#17313F]">{{ $profile->displayName() }}</h2>
                                <p class="mt-1 text-sm text-[#607080]">{{ $profile->recipient_is_requester ? 'Care for me' : ($profile->relationship_to_family ?: 'Person receiving care') }}</p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $profile->status === 'ready' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">{{ $profile->status === 'ready' ? 'Ready to use' : 'Draft' }}</span>
                        </div>
                        <p class="mt-4 text-xs text-[#6A7784]">
                            @if ($profile->last_reviewed_at)
                                Last reviewed {{ $profile->last_reviewed_at->format('F j, Y') }}
                            @else
                                Finish when you are ready
                            @endif
                            @if ($profile->updatedBy) &middot; updated by {{ $profile->updatedBy->name }} @endif
                        </p>
                        <div class="mt-auto flex flex-wrap gap-2 pt-5">
                            <a href="{{ route('family.care-profiles.edit', $profile) }}" wire:navigate class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-[#17313F] px-4 text-sm font-semibold text-white">View or edit</a>
                            @if ((int) $defaultProfileId !== (int) $profile->id)
                                <button type="button" wire:click="makeDefault({{ $profile->id }})" class="min-h-11 rounded-xl border border-[#D7CEC2] px-3 text-sm font-semibold text-[#17313F]">Suggest first</button>
                            @else
                                <span class="inline-flex min-h-11 items-center rounded-xl bg-[#F4EFE8] px-3 text-sm font-semibold text-[#526474]">Suggested first</span>
                            @endif
                            <button type="button" wire:click="$set('archivingProfileId', {{ $profile->id }})" class="min-h-11 rounded-xl border border-rose-200 px-3 text-sm font-semibold text-rose-700">Archive</button>
                        </div>
                    </article>
                @endforeach
            </section>
        @endif

        @if ($archivedProfiles->isNotEmpty())
            <details class="rounded-2xl border border-[#DCCFBE] bg-white p-5">
                <summary class="cursor-pointer font-display text-lg font-semibold text-[#17313F]">Archived profiles ({{ $archivedProfiles->count() }})</summary>
                <div class="mt-4 divide-y divide-[#EEE5DA]">
                    @foreach ($archivedProfiles as $profile)
                        <div class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div><p class="font-semibold text-[#17313F]">{{ $profile->displayName() }}</p><p class="text-sm text-[#607080]">Archived {{ $profile->archived_at?->format('F j, Y') }}</p></div>
                            <button type="button" wire:click="restore({{ $profile->id }})" class="min-h-11 rounded-xl border border-[#D7CEC2] px-4 text-sm font-semibold">Restore profile</button>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    </div>

    @if ($archivingProfileId)
        @php($archivingProfile = $profiles->firstWhere('id', $archivingProfileId))
        @if ($archivingProfile)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-[#17313F]/50 p-4" role="dialog" aria-modal="true" aria-labelledby="archive-profile-heading">
                <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                    <h2 id="archive-profile-heading" class="font-display text-2xl font-semibold text-[#17313F]">Archive {{ $archivingProfile->displayName() }}'s care profile?</h2>
                    <p class="mt-3 text-sm leading-6 text-[#607080]">You will not be able to use it for new care. Caregivers connected to existing care will keep the information already shared with them.</p>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <button type="button" wire:click="archive" class="min-h-11 rounded-xl bg-rose-700 px-4 font-semibold text-white">Archive profile</button>
                        <button type="button" wire:click="$set('archivingProfileId', null)" class="min-h-11 rounded-xl border border-[#D7CEC2] font-semibold">Keep profile</button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
