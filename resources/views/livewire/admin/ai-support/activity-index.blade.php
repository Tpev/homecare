<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <a href="{{ route('admin.ai-support.index') }}" wire:navigate class="text-sm font-semibold text-emerald-700 hover:underline">&larr; AI Support overview</a>
        <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-950">AI Support activity</h1>
        <p class="mt-2 text-sm text-slate-600">Content-free control-plane audit. Support transcript text remains only in the canonical support conversation.</p>
    </div>
    <x-card>
        <label class="text-sm font-semibold text-slate-800">Event family
            <select wire:model.live="family" class="mt-1 min-h-11 w-full max-w-xs rounded-xl border-slate-300 text-base">
                <option value="">All</option><option value="pilot_access">Pilot access</option><option value="control">Controls</option><option value="knowledge_base">Knowledge base</option>
            </select>
        </label>
    </x-card>
    <div class="space-y-3">
        @forelse($events as $event)
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" wire:key="audit-event-{{ $event->id }}">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="font-bold text-slate-950">{{ str($event->action)->replace('_', ' ')->headline() }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $event->actor?->name ?: 'System/former user' }}{{ $event->targetUser ? ' → '.$event->targetUser->name : '' }}</p>
                        @if($event->reason)<p class="mt-1 text-sm text-slate-700">{{ $event->reason }}</p>@endif
                        <p class="mt-1 text-xs text-slate-500">{{ $event->event_family }} · {{ $event->reason_code ?: 'no reason code' }} · policy {{ $event->policy_version }}</p>
                    </div>
                    <div class="text-left sm:text-right"><x-badge :text="strtoupper($event->result)" :color="$event->result === 'succeeded' ? 'green' : 'red'" /><p class="mt-2 text-xs text-slate-500">{{ $event->occurred_at->format('M j, Y g:i A') }}</p></div>
                </div>
            </article>
        @empty
            <x-card><p class="text-sm text-slate-600">No audit events match this view.</p></x-card>
        @endforelse
    </div>
    {{ $events->links() }}
</div>
