<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
    <h1 class="text-2xl font-semibold">Family onboarding</h1>
    <div class="rounded-xl border bg-white p-4 text-sm">
        <p class="font-semibold">New family signups automatically start onboarding. Existing families and invited members are excluded.</p>
        <p class="mt-2 text-slate-600">Completed means the onboarding form was submitted. A care request can contain care details without an onboarding submission. Not enrolled means no onboarding record exists; it does not mean the family abandoned the form.</p>
    </div>
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach(['Enrolled' => $total, 'Completed' => $completed, 'Visits awaiting follow-up' => $pendingVisits, 'Email issues' => $emailIssues] as $label => $count)
            <div class="rounded-xl border bg-white p-4"><p class="text-sm text-slate-600">{{ $label }}</p><p class="mt-2 text-2xl font-semibold">{{ $count }}</p></div>
        @endforeach
    </div>
    <p class="text-sm text-slate-600">In progress by step: @for($step = 1; $step <= 5; $step++)<span class="mr-4">{{ $step }}: {{ $steps[$step] ?? 0 }}</span>@endfor</p>
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block text-sm font-medium">Search family
            <input type="search" wire:model.live.debounce.300ms="search" maxlength="255" placeholder="Name, email, or family account ID" class="mt-1 block w-full rounded-lg border-slate-300">
        </label>
        <label class="block text-sm font-medium">Show
            <select wire:model.live="filter" class="mt-1 block w-full rounded-lg border-slate-300">
                <option value="all">All family accounts</option><option value="visits">Welcome visits needing follow-up</option><option value="email">Email delivery issues</option><option value="in_progress">In progress</option><option value="completed">Completed</option><option value="not_enrolled">Not enrolled</option><option value="exempted">Exempted</option>
            </select>
        </label>
    </div>
    <div class="overflow-x-auto rounded-xl border bg-white">
        <table class="w-full text-left text-sm"><thead class="bg-slate-50"><tr><th class="p-4">Family</th><th class="p-4">Onboarding</th><th class="p-4">Latest request</th><th class="p-4">Welcome visit</th><th class="p-4">Admin email</th></tr></thead><tbody>
            @forelse($families as $family)
                @php($row = $family->onboarding)
                <tr class="border-t">
                    <td class="p-4">
                        @if($row || $family->owner)
                            <a class="font-semibold underline" href="{{ $row ? route('admin.family-onboarding.show', $row) : route('admin.users.show', $family->owner) }}">{{ $row?->initiatedBy?->name ?? $family->owner?->name ?? 'Deleted user' }} · #{{ $family->id }}</a>
                        @else
                            <span>Deleted user · #{{ $family->id }}</span>
                        @endif
                        <p class="text-xs text-slate-500">{{ $row?->initiatedBy?->email ?? $family->owner?->email }}</p>
                        <p class="text-xs text-slate-500">{{ ($row?->created_at ?? $family->created_at)->format('M j, Y') }}</p>
                    </td>
                    <td class="p-4">
                        {{ $row ? ucfirst(str_replace('_', ' ', $row->status)) : 'Not enrolled' }}
                        @if($row?->status === 'in_progress') · Step {{ $row->current_step }}@endif
                        @if($row?->completed_at)<p class="text-xs text-slate-500">{{ $row->completed_at->format('M j, Y g:i A T') }}</p>@endif
                    </td>
                    <td class="p-4">@if($family->latest_request_id)<a class="underline" href="{{ route('admin.requests.show', $family->latest_request_id) }}">Request #{{ $family->latest_request_id }}</a>@else — @endif</td>
                    <td class="p-4">{{ $row?->welcomeVisit?->status ?? ($row?->completed_at ? 'Declined' : '—') }}</td>
                    <td class="p-4">{{ $row?->deliveries->where('status', '!=', 'superseded')->pluck('status')->unique()->implode(', ') ?: '—' }}</td>
                </tr>
            @empty<tr><td class="p-6 text-slate-500" colspan="5">No families match this search and filter. <a href="{{ route('admin.family-onboarding.index') }}" class="underline">Clear search and filters</a></td></tr>@endforelse
        </tbody></table>
    </div>
    {{ $families->links() }}
</div>
