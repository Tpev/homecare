<details class="group text-left">
    <summary class="cursor-pointer rounded-lg px-1 py-2 text-xs font-medium text-slate-600 hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600">
        More actions
    </summary>
    <div class="mt-1 space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
        <div>
            <p class="mb-2 text-xs font-semibold text-slate-600">Set request status</p>
            <div class="flex flex-wrap gap-1.5">
                <button type="button" wire:click="forceRequestStatus({{ $request->id }}, 'open')" class="rounded-lg border border-sky-200 bg-white px-2 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-50">Open</button>
                <button type="button" wire:click="forceRequestStatus({{ $request->id }}, 'filled')" class="rounded-lg border border-indigo-200 bg-white px-2 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Filled</button>
                <button type="button" wire:click="forceRequestStatus({{ $request->id }}, 'cancelled')" class="rounded-lg border border-rose-200 bg-white px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">Cancel</button>
                <button type="button" wire:click="forceRequestStatus({{ $request->id }}, 'expired')" class="rounded-lg border border-amber-200 bg-white px-2 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-50">Expire</button>
            </div>
        </div>
        @if ($request->booking)
            <div>
                <p class="mb-2 text-xs font-semibold text-slate-600">Set booking status</p>
                <div class="flex flex-wrap gap-1.5">
                    <button type="button" wire:click="forceBookingStatus({{ $request->id }}, 'scheduled')" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">Scheduled</button>
                    <button type="button" wire:click="forceBookingStatus({{ $request->id }}, 'completed')" class="rounded-lg border border-emerald-200 bg-white px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">Completed</button>
                    <button type="button" wire:click="forceBookingStatus({{ $request->id }}, 'cancelled')" class="rounded-lg border border-rose-200 bg-white px-2 py-1 text-xs text-rose-700 hover:bg-rose-50">Cancelled</button>
                </div>
            </div>
        @endif
        <div class="border-t border-slate-200 pt-3">
            <button type="button" wire:click="deleteRequest({{ $request->id }})" wire:confirm="Delete request #{{ $request->id }}? This cannot be undone." class="text-xs font-semibold text-red-700 underline decoration-red-300 underline-offset-2 hover:text-red-900">Delete request</button>
        </div>
    </div>
</details>
