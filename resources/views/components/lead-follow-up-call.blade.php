@props(['lead'])

@if (\App\Support\FamilyLeadOutreach::canQueueFollowUpCall($lead))
    @php($queued = \App\Support\FamilyLeadOutreach::hasQueuedFollowUpCall($lead))
    <section class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-slate-950">Follow-up call</h3>
                <p class="mt-1 text-sm text-slate-600">
                    @if ($queued)
                        {{ \App\Support\FamilyLeadOutreach::isCallable($lead) ? 'In the calling queue now.' : 'Scheduled for '.$lead->next_follow_up_at->format('M j, g:i A').'.' }}
                    @else
                        Add to the calling queue now.
                    @endif
                    Stage stays {{ $lead->stageLabel() }}.
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    {{ $lead->assignedAdmin ? 'Assigned to '.$lead->assignedAdmin->name.'.' : 'Available for a caller to claim.' }}
                    Missed calls follow the usual retry schedule.
                </p>
            </div>
            <button type="button" wire:click="queueFollowUpCall" wire:loading.attr="disabled" wire:target="queueFollowUpCall" @disabled($queued) class="min-h-11 rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-default disabled:opacity-60">
                {{ $queued ? 'Call queued' : 'Queue follow-up call' }}
            </button>
        </div>
    </section>
@endif
@error('followUpCall') <p role="alert" class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
