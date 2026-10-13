<section class="mt-5 border-t border-slate-200 pt-5" aria-label="Review decision">
    <h3 class="font-semibold text-slate-900">Screening outcome & account decision</h3>
    @if ($completed)
        <div class="mt-3 rounded-xl bg-slate-50 p-4 text-sm">
            <p><strong>{{ str($selectedCase->outcome)->headline() }}</strong> · {{ $selectedCase->decision === 'approve' ? 'Account approved' : 'New work suspended' }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $selectedCase->completed_at?->format('M j, Y g:i A') }} · {{ $selectedCase->reviewer?->name ?? 'Administrator' }}</p>
            <p class="mt-3 whitespace-pre-line text-slate-700">{{ $selectedCase->note }}</p>
            @if ($profile->screening_next_due_at)<p class="mt-3 text-xs text-slate-500">Next screening due {{ $profile->screening_next_due_at->format('M j, Y') }}.</p>@endif
        </div>
        <button type="button" wire:click="startFollowUp" wire:loading.attr="disabled" class="mt-3 min-h-11 rounded-xl border border-teal-300 px-4 py-2 text-sm font-semibold text-teal-800">Start follow-up review</button>
    @else
        <div class="mt-3 space-y-3">
            <label class="block text-sm font-medium text-slate-700" for="screening-outcome">Outcome</label>
            <select id="screening-outcome" wire:model="outcome" @disabled(! $hasClaim) class="min-h-11 w-full rounded-xl border-slate-300 text-sm disabled:bg-slate-100">
                <option value="">Choose after reviewing the evidence</option>
                <option value="no_match">No Match</option>
                <option value="potential_match">Potential Match</option>
                <option value="match">Match</option>
            </select>
            <label class="block text-sm font-medium text-slate-700" for="screening-note">Decision or investigation note</label>
            <textarea id="screening-note" wire:model="note" rows="4" maxlength="5000" @disabled(! $hasClaim) placeholder="Explain the evidence reviewed, candidate records ruled out or confirmed, and your decision or next investigation step." class="w-full rounded-xl border-slate-300 text-sm disabled:bg-slate-100"></textarea>
            <p class="text-xs text-slate-500">A reason is required for suspension, pending investigations, matches, and ruling out candidate records. Optional for a No Match approval with no candidates.</p>
            <p class="text-xs text-slate-500">Suspension blocks new work, retains login, and flags existing commitments for admin review. Keeping a review pending never changes access.</p>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                <button type="button" wire:click="decide('approve')" wire:loading.attr="disabled" @disabled(! $canDecide) class="min-h-11 rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-45">Approve & Next</button>
                <button type="button" wire:click="decide('suspend')" wire:confirm="Suspend new work for this caregiver? Login will remain available. Existing commitments will be flagged for admin review." wire:loading.attr="disabled" @disabled(! $canDecide) class="min-h-11 rounded-xl bg-rose-700 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-45">Suspend & Next</button>
                <button type="button" wire:click="keepPending" wire:loading.attr="disabled" @disabled(! $hasClaim) class="min-h-11 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-45">Keep Pending</button>
            </div>
            @if (! $profile->screening_suspended_at)
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <p class="mb-2 text-xs text-slate-500">If new work must be restricted before screening is complete, enter the reason above and suspend explicitly. The screening review stays open.</p>
                    <button type="button" wire:click="suspendNewWork" wire:confirm="Suspend new work while keeping this screening review open? Login remains available and existing commitments will be flagged for admin review." wire:loading.attr="disabled" @disabled(! $hasClaim) class="min-h-11 rounded-xl border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-800 disabled:cursor-not-allowed disabled:opacity-45">Suspend new work now</button>
                </div>
            @endif
        </div>
    @endif
</section>
