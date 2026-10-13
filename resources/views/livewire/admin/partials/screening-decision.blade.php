<section class="mt-5 border-t border-slate-200 pt-5" aria-label="Review decision">
    <h3 class="font-semibold text-slate-900">Review decision</h3>
    @if ($completed)
        <div class="mt-3 rounded-xl bg-slate-50 p-4 text-sm">
            <p><strong>{{ $selectedCase->decision === 'approve' ? 'Account approved' : 'New work suspended' }}</strong>@if ($selectedCase->outcome) · {{ str($selectedCase->outcome)->headline() }}@endif</p>
            <p class="mt-1 text-xs text-slate-500">{{ $selectedCase->completed_at?->format('M j, Y g:i A') }} · {{ $selectedCase->reviewer?->name ?? 'Administrator' }}</p>
            @if ($selectedCase->note)<p class="mt-3 whitespace-pre-line text-slate-700">{{ $selectedCase->note }}</p>@endif
            @if ($profile->screening_next_due_at)<p class="mt-3 text-xs text-slate-500">Next screening due {{ $profile->screening_next_due_at->format('M j, Y') }}.</p>@endif
        </div>
        <button type="button" wire:click="startFollowUp" wire:loading.attr="disabled" @disabled(! $hasCurrentIdentity) class="mt-3 min-h-11 rounded-xl border border-teal-300 px-4 py-2 text-sm font-semibold text-teal-800 disabled:cursor-not-allowed disabled:opacity-50">{{ $isSuspended ? 'Review to restore account' : 'Start follow-up review' }}</button>
    @else
        <div class="mt-3 space-y-3">
            <label class="block text-sm font-medium text-slate-700" for="screening-outcome">Outcome <span class="font-normal text-slate-500">(optional)</span></label>
            <select id="screening-outcome" wire:model="outcome" @disabled(! $hasClaim) class="min-h-11 w-full rounded-xl border-slate-300 text-sm disabled:bg-slate-100">
                <option value="">Not specified</option>
                <option value="no_match">No Match</option>
                <option value="potential_match">Potential Match</option>
                <option value="match">Match</option>
            </select>
            <label class="block text-sm font-medium text-slate-700" for="screening-note">Decision or investigation note <span class="font-normal text-slate-500">(optional)</span></label>
            <textarea id="screening-note" wire:model="note" rows="3" maxlength="5000" @disabled(! $hasClaim) placeholder="Add a note" class="w-full rounded-xl border-slate-300 text-sm disabled:bg-slate-100"></textarea>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                <button type="button" wire:click="decide('approve')" wire:loading.attr="disabled" @disabled(! $canDecide) class="min-h-11 rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-45">{{ $isSuspended ? 'Approve & restore account' : 'Approve & Next' }}</button>
                <button type="button" wire:click="decide('suspend')" wire:confirm="Suspend new work for this caregiver? Login will remain available. Existing commitments will be flagged for admin review." wire:loading.attr="disabled" @disabled(! $canDecide) class="min-h-11 rounded-xl bg-rose-700 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-45">Suspend & Next</button>
                <button type="button" wire:click="keepPending" wire:loading.attr="disabled" @disabled(! $hasClaim) class="min-h-11 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-45">Keep Pending</button>
            </div>
            @if (! $isSuspended)
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <p class="mb-2 text-xs text-slate-500">Suspend new work and keep this review open.</p>
                    <button type="button" wire:click="suspendNewWork" wire:confirm="Suspend new work while keeping this screening review open? Login remains available and existing commitments will be flagged for admin review." wire:loading.attr="disabled" @disabled(! $hasClaim) class="min-h-11 rounded-xl border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-800 disabled:cursor-not-allowed disabled:opacity-45">Suspend new work now</button>
                </div>
            @endif
        </div>
    @endif
</section>
