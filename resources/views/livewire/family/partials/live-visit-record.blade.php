<section class="hc-surface p-5 sm:p-6" aria-labelledby="live-care-record-title">
    <div class="flex flex-wrap items-center justify-between gap-3"><h2 id="live-care-record-title" class="text-2xl font-semibold">Care during this visit</h2><span class="hc-care-status">{{ $booking->taskChecks->where('is_completed', true)->count() }} of {{ $booking->taskChecks->count() }} tasks done</span></div>
    <div class="mt-5 grid gap-6 lg:grid-cols-2">
        <div><h3 class="text-lg font-semibold">Care tasks & notes</h3><div class="mt-3 space-y-3">
            @forelse ($booking->taskChecks as $taskCheck)
                <div class="rounded-xl border border-hc-border p-3"><p class="font-semibold">{{ $taskCheck->is_completed ? 'Completed · ' : 'To do · ' }}{{ $taskCheck->label }}</p>@if ($taskCheck->notes)<p class="mt-2 text-sm text-hc-muted">{{ $taskCheck->notes }}</p>@endif</div>
            @empty<p class="text-sm">No task checks recorded yet.</p>@endforelse
        </div></div>
        <div><h3 class="text-lg font-semibold">Visit activity</h3><ol class="mt-3 space-y-3">
            @forelse ($booking->events->sortByDesc('happened_at') as $event)
                <li class="border-l-2 border-hc-border pl-3"><p class="text-sm font-semibold">{{ ucfirst(str_replace('_', ' ', $event->event_type)) }}</p><p class="hc-care-meta">{{ $event->happened_at?->format('M j, g:i A') }}{{ $event->actor?->name ? ' · '.$event->actor->name : '' }}</p></li>
            @empty<li class="text-sm">No activity recorded yet.</li>@endforelse
        </ol></div>
    </div>
</section>
