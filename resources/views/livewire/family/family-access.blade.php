<div class="hc-page py-8 sm:py-12">
    <div class="mx-auto max-w-3xl space-y-6">
        <header class="space-y-2">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#B95745]">Family access</p>
            <h1 class="font-display text-3xl font-semibold text-[#17313F]" tabindex="-1">People helping manage care</h1>
            <p class="max-w-2xl text-sm leading-6 text-[#5D6D67]">Invite someone you trust to help manage care. They will use their own email and password, and you can remove their access at any time.</p>
            @if (! $isOwner)
                <p class="text-sm text-[#68756F]">You are helping manage care with {{ $account->owner->name }}.</p>
            @endif
        </header>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status" aria-live="polite">{{ session('status') }}</div>
        @endif

        <section class="overflow-hidden rounded-2xl border border-[#E3D6C5] bg-white shadow-sm" aria-labelledby="people-with-access">
            <div class="border-b border-[#E9E1D7] px-5 py-4">
                <h2 id="people-with-access" class="font-display text-xl font-semibold">People with access</h2>
            </div>

            <div class="divide-y divide-[#EEE7DE]">
                @foreach ($account->activeMemberships as $accountMember)
                    <article class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-[#17313F]">{{ $accountMember->user->name }}</p>
                                <span class="rounded-full bg-[#F2EEE8] px-2.5 py-1 text-xs font-semibold text-[#526474]">{{ $accountMember->isOwner() ? 'Account owner' : 'Family member' }}</span>
                            </div>
                            <p class="mt-1 break-all text-sm text-[#68756F]">{{ $accountMember->user->email }}</p>
                        </div>

                        @if ($isOwner && ! $accountMember->isOwner())
                            <button type="button" wire:click="$set('removingMemberId', {{ $accountMember->id }})" class="min-h-11 self-start rounded-xl border border-rose-200 px-4 text-sm font-semibold text-rose-700 hover:bg-rose-50 sm:self-auto">Remove access</button>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        @if ($isOwner)
            <section class="rounded-2xl border border-[#E3D6C5] bg-white p-5 shadow-sm" aria-labelledby="pending-invitations">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 id="pending-invitations" class="font-display text-xl font-semibold">Pending invitations</h2>
                        <p class="mt-1 text-sm text-[#68756F]">Private invitations expire after seven days.</p>
                    </div>
                    <x-button wire:click="$set('showInviteForm', true)" color="blue" class="min-h-11 justify-center">Invite someone</x-button>
                </div>

                @if ($account->invitations->isEmpty())
                    <p class="mt-5 rounded-xl bg-[#F7F2EA] px-4 py-3 text-sm text-[#68756F]">No pending invitations.</p>
                @else
                    <div class="mt-5 divide-y divide-[#EEE7DE] border-t border-[#EEE7DE]">
                        @foreach ($account->invitations as $invitation)
                            <article class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="break-all font-semibold">{{ $invitation->email_normalized }}</p>
                                    <p class="mt-1 text-sm text-[#68756F]">
                                        @if ($invitation->expires_at->isPast())
                                            Expired
                                        @else
                                            Invitation sent &middot; expires {{ $invitation->expires_at->format('F j, Y') }}
                                        @endif
                                    </p>
                                </div>
                                <button type="button" wire:click="$set('managedInvitationId', {{ $invitation->id }})" class="min-h-11 self-start rounded-xl border border-[#D7CEC2] px-4 text-sm font-semibold hover:bg-[#F7F2EA] sm:self-auto">{{ $invitation->expires_at->isPast() ? 'Send a new invitation' : 'Manage invitation' }}</button>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @else
            <section class="rounded-2xl border border-[#E3D6C5] bg-white p-5 shadow-sm">
                <h2 class="font-display text-xl font-semibold">Your access</h2>
                <p class="mt-2 text-sm leading-6 text-[#68756F]">Leaving ends your access immediately. You will need a new invitation to return.</p>
                <button type="button" wire:click="$set('confirmLeave', true)" class="mt-4 min-h-11 rounded-xl border border-rose-200 px-4 text-sm font-semibold text-rose-700 hover:bg-rose-50">Leave this family account</button>
            </section>
        @endif
    </div>

    @if ($showInviteForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-[#17313F]/50 p-4" role="dialog" aria-modal="true" aria-labelledby="invite-heading" x-data x-init="$nextTick(() => $refs.email.focus())">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                <h2 id="invite-heading" class="font-display text-2xl font-semibold" tabindex="-1">Invite someone</h2>
                <p class="mt-2 text-sm text-[#68756F]">We will send them a private invitation to help manage care.</p>
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">This person will be able to schedule care and approve care-related charges using your family's saved payment method. Only you can change the payment method or invite other people.</div>
                <form wire:submit="sendInvitation" class="mt-5 space-y-4">
                    <div>
                        <x-input x-ref="email" type="email" label="Their email address" wire:model="inviteEmail" autocomplete="email" required />
                        <x-input-error :messages="$errors->get('inviteEmail')" class="mt-2" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-button type="submit" color="blue" class="min-h-11 w-full justify-center">Send invitation</x-button>
                        <button type="button" wire:click="$set('showInviteForm', false)" class="min-h-11 rounded-xl border border-[#D7CEC2] font-semibold">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($managedInvitationId)
        @php($managedInvitation = $account->invitations->firstWhere('id', $managedInvitationId))
        @if ($managedInvitation)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-[#17313F]/50 p-4" role="dialog" aria-modal="true" aria-labelledby="manage-invitation-heading" x-data x-init="$nextTick(() => $refs.heading.focus())">
                <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                    <h2 x-ref="heading" id="manage-invitation-heading" class="font-display text-2xl font-semibold" tabindex="-1">Manage invitation</h2>
                    <p class="mt-2 break-all text-sm text-[#68756F]">{{ $managedInvitation->email_normalized }}</p>
                    <div class="mt-5 grid gap-3">
                        <x-button wire:click="resendInvitation({{ $managedInvitation->id }})" color="blue" class="min-h-11 justify-center">{{ $managedInvitation->expires_at->isPast() ? 'Send a new invitation' : 'Send again' }}</x-button>
                        <button type="button" wire:click="cancelInvitation({{ $managedInvitation->id }})" class="min-h-11 rounded-xl border border-rose-200 font-semibold text-rose-700">Cancel invitation</button>
                        <button type="button" wire:click="$set('managedInvitationId', null)" class="min-h-11 rounded-xl border border-[#D7CEC2] font-semibold">Back</button>
                    </div>
                </div>
            </div>
        @endif
    @endif

    @if ($removingMemberId)
        @php($removingMember = $account->activeMemberships->firstWhere('id', $removingMemberId))
        @if ($removingMember)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-[#17313F]/50 p-4" role="dialog" aria-modal="true" aria-labelledby="remove-member-heading" x-data x-init="$nextTick(() => $refs.heading.focus())">
                <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                    <h2 x-ref="heading" id="remove-member-heading" class="font-display text-2xl font-semibold" tabindex="-1">Remove {{ $removingMember->user->name }}'s access?</h2>
                    <p class="mt-3 text-sm leading-6 text-[#68756F]">{{ $removingMember->user->name }} will no longer be able to see this family's care, visits, messages, or billing history. Their past messages and actions will remain in the care record.</p>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <button type="button" wire:click="removeAccess" class="min-h-11 rounded-xl bg-rose-700 px-4 font-semibold text-white">Remove {{ $removingMember->user->name }}</button>
                        <button type="button" wire:click="$set('removingMemberId', null)" class="min-h-11 rounded-xl border border-[#D7CEC2] font-semibold">Keep access</button>
                    </div>
                </div>
            </div>
        @endif
    @endif

    @if ($confirmLeave)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-[#17313F]/50 p-4" role="dialog" aria-modal="true" aria-labelledby="leave-family-heading" x-data x-init="$nextTick(() => $refs.heading.focus())">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                <h2 x-ref="heading" id="leave-family-heading" class="font-display text-2xl font-semibold" tabindex="-1">Leave this family account?</h2>
                <p class="mt-3 text-sm leading-6 text-[#68756F]">Your access will end immediately. You must receive a new invitation if you want to return.</p>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <button type="button" wire:click="leaveAccount" class="min-h-11 rounded-xl bg-rose-700 px-4 font-semibold text-white">Leave family account</button>
                    <button type="button" wire:click="$set('confirmLeave', false)" class="min-h-11 rounded-xl border border-[#D7CEC2] font-semibold">Keep access</button>
                </div>
            </div>
        </div>
    @endif
</div>
