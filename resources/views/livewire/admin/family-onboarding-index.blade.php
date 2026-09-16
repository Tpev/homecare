<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
    <h1 class="text-2xl font-semibold">Family onboarding</h1>
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach(['Enrolled' => $total, 'Completed' => $completed, 'Visits awaiting follow-up' => $pendingVisits, 'Email issues' => $emailIssues] as $label => $count)
            <div class="rounded-xl border bg-white p-4"><p class="text-sm text-slate-600">{{ $label }}</p><p class="mt-2 text-2xl font-semibold">{{ $count }}</p></div>
        @endforeach
    </div>
    <p class="text-sm text-slate-600">In progress by step: @for($step = 1; $step <= 5; $step++)<span class="mr-4">{{ $step }}: {{ $steps[$step] ?? 0 }}</span>@endfor</p>
    <label class="block text-sm font-medium">Show
        <select wire:model.live="filter" class="ml-3 rounded-lg border-slate-300">
            <option value="all">All families</option><option value="visits">Welcome visits needing follow-up</option><option value="email">Email delivery issues</option><option value="in_progress">In progress</option><option value="completed">Completed</option><option value="exempted">Exempted</option>
        </select>
    </label>
    <div class="overflow-x-auto rounded-xl border bg-white">
        <table class="w-full text-left text-sm"><thead class="bg-slate-50"><tr><th class="p-4">Family</th><th class="p-4">Onboarding</th><th class="p-4">Welcome visit</th><th class="p-4">Admin email</th></tr></thead><tbody>
            @forelse($onboardings as $row)
                <tr class="border-t"><td class="p-4"><a class="font-semibold underline" href="{{ route('admin.family-onboarding.show', $row) }}">{{ $row->initiatedBy?->name ?? 'Deleted user' }} · #{{ $row->family_account_id }}</a><p class="text-xs text-slate-500">{{ $row->created_at->format('M j, Y') }}</p></td><td class="p-4">{{ str_replace('_', ' ', $row->status) }}@if($row->status === 'in_progress') · Step {{ $row->current_step }}@endif</td><td class="p-4">{{ $row->welcomeVisit?->status ?? ($row->completed_at ? 'Declined' : '—') }}</td><td class="p-4">{{ $row->deliveries->where('status', '!=', 'superseded')->pluck('status')->unique()->implode(', ') ?: '—' }}</td></tr>
            @empty<tr><td class="p-6 text-slate-500" colspan="4">No families match this filter.</td></tr>@endforelse
        </tbody></table>
    </div>
    {{ $onboardings->links() }}
</div>
