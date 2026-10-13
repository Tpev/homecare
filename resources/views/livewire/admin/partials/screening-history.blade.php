<section class="rounded-2xl border border-slate-200 bg-white p-5" aria-label="Screening and moderation history">
    <h2 class="font-semibold text-slate-900">Review history & audit trail</h2>
    <p class="mt-1 text-xs text-slate-500">Private admin evidence. Decisions and corrections are retained as separate events.</p>
    <details class="mt-4" open>
        <summary class="cursor-pointer text-sm font-semibold text-slate-700">This case · {{ $selectedCase->events->count() }} audit events</summary>
        <ol class="mt-3 space-y-3">
            @forelse ($selectedCase->events->sortByDesc('id') as $event)
                <li class="rounded-xl bg-slate-50 p-3 text-xs">
                    <div class="flex flex-wrap justify-between gap-2"><strong class="text-slate-800">{{ str($event->event_type)->headline() }}</strong><span class="text-slate-500">{{ $event->occurred_at?->format('M j, Y g:i:s A') }}</span></div>
                    <p class="mt-1 text-slate-500">{{ $event->actor_user_id ? 'Admin #'.$event->actor_user_id : 'System' }} · Event #{{ $event->id }}</p>
                    <details class="mt-2"><summary class="cursor-pointer text-slate-600">Evidence</summary><pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-all rounded-lg bg-white p-3 text-slate-700">{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
                </li>
            @empty
                <li class="text-sm text-slate-500">No events recorded yet.</li>
            @endforelse
        </ol>
    </details>
    <details class="mt-5 border-t border-slate-100 pt-4">
        <summary class="cursor-pointer text-sm font-semibold text-slate-700">Other screening cases</summary>
        <div class="mt-3 space-y-2">
            @forelse ($history as $previous)
                <button type="button" wire:click="selectCase({{ $previous->id }})" class="block w-full rounded-xl border border-slate-200 p-3 text-left text-sm hover:bg-slate-50">
                    <span class="font-semibold text-slate-800">Case #{{ $previous->id }} · {{ str($previous->kind)->headline() }} · {{ str($previous->status)->headline() }}</span>
                    <span class="mt-1 block text-xs text-slate-500">{{ $previous->completed_at?->format('M j, Y g:i A') ?? 'Open review' }}{{ $previous->outcome ? ' · '.str($previous->outcome)->headline() : '' }}{{ $previous->decision ? ' · '.str($previous->decision)->headline() : '' }}{{ $previous->reviewer ? ' · '.$previous->reviewer->name : '' }}</span>
                </button>
            @empty
                <p class="text-sm text-slate-500">No earlier screening cases.</p>
            @endforelse
        </div>
    </details>
    <details class="mt-5 border-t border-slate-100 pt-4">
        <summary class="cursor-pointer text-sm font-semibold text-slate-700">Profile & credential moderation history</summary>
        <ol class="mt-3 space-y-3">
            @forelse ($moderationLogs as $log)
                <li class="rounded-xl bg-slate-50 p-3 text-xs">
                    <p class="font-semibold text-slate-800">{{ str($log->action)->headline() }}</p>
                    <p class="mt-1 text-slate-500">{{ $log->created_at?->format('M j, Y g:i A') }} · {{ $log->actor?->name ?? 'System' }}</p>
                    <p class="mt-2 whitespace-pre-line text-slate-700">{{ $log->note }}</p>
                </li>
            @empty
                <li class="text-sm text-slate-500">No previous moderation entries.</li>
            @endforelse
        </ol>
    </details>
</section>
