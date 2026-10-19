<div class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="relative overflow-hidden rounded-3xl bg-[#0F3D3E] p-6 text-white sm:p-8">
            <div class="pointer-events-none absolute -right-12 -top-20 h-64 w-64 rounded-full border-[40px] border-white/5" aria-hidden="true"></div>
            <div class="relative flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-200">Community care · Admin</p>
                    <h1 class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">Care assistance</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-white/80">A little understanding can make a meaningful difference. Review each application with care and help families find their next step.</p>
                </div>
                <div class="shrink-0 rounded-2xl border border-white/15 bg-white/10 px-5 py-4">
                    <p class="text-xs font-medium text-emerald-100">Program rate</p>
                    <p class="mt-1 text-3xl font-bold">$23<span class="ml-1 text-sm font-normal text-white/80">/ hour</span></p>
                    <p class="mt-2 text-xs text-white/70">Review goal: about 5 business days</p>
                </div>
            </div>
        </header>

        <section aria-label="Application overview" class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold text-slate-500">All applications</p>
                <p class="mt-2 text-3xl font-bold tabular-nums text-slate-950">{{ $total }}</p>
            </div>
            @foreach ($statusLabels as $value => $label)
                <div class="rounded-2xl border p-4 shadow-sm {{ $value === 'new' ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white' }}">
                    <p class="text-xs font-semibold {{ $value === 'new' ? 'text-emerald-800' : 'text-slate-500' }}">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums {{ $value === 'new' ? 'text-emerald-900' : 'text-slate-950' }}">{{ $counts[$value] ?? 0 }}</p>
                </div>
            @endforeach
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="applications-heading">
            <div class="border-b border-slate-200 p-5 sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 id="applications-heading" class="text-lg font-bold text-slate-950">Application inbox</h2>
                        <p class="mt-1 text-sm text-slate-500">New applications appear first, oldest first within each status.</p>
                    </div>
                    <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4"/></svg>
                        Private · Administrators only
                    </span>
                </div>
                <div class="mt-5 grid gap-4 sm:grid-cols-[minmax(0,1fr)_240px]">
                    <div>
                        <label for="assistance-search" class="text-sm font-semibold text-slate-700">Search applications</label>
                        <input id="assistance-search" type="search" wire:model.live.debounce.300ms="search" maxlength="255" placeholder="Name, email, city, ZIP code, or application ID" class="mt-2 min-h-11 w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:border-emerald-600 focus:ring-emerald-600">
                    </div>
                    <div>
                        <label for="assistance-status" class="text-sm font-semibold text-slate-700">Status</label>
                        <select id="assistance-status" wire:model.live="status" class="mt-2 min-h-11 w-full rounded-xl border-slate-300 bg-slate-50 text-sm focus:border-emerald-600 focus:ring-emerald-600">
                            <option value="all">All statuses</option>
                            @foreach ($statusLabels as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto" wire:loading.class="opacity-60" wire:target="search,status,clearFilters,gotoPage,nextPage,previousPage">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th scope="col" class="px-6 py-4">Applicant</th>
                            <th scope="col" class="px-4 py-4">Care location</th>
                            <th scope="col" class="px-4 py-4">Submitted</th>
                            <th scope="col" class="px-4 py-4">Status</th>
                            <th scope="col" class="px-6 py-4"><span class="sr-only">Review application</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($applications as $application)
                            @php
                                $badgeClasses = match ($application->status) {
                                    'new' => 'bg-emerald-100 text-emerald-900',
                                    'under_review' => 'bg-sky-100 text-sky-900',
                                    'needs_information' => 'bg-amber-100 text-amber-900',
                                    'approved' => 'bg-teal-100 text-teal-900',
                                    'declined' => 'bg-rose-50 text-rose-800',
                                    default => 'bg-slate-100 text-slate-700',
                                };
                            @endphp
                            <tr wire:key="assistance-{{ $application->id }}" class="transition hover:bg-slate-50/80">
                                <td class="max-w-xs px-6 py-5">
                                    <a href="{{ route('admin.care-assistance.show', $application) }}" wire:navigate class="font-semibold text-slate-950 hover:text-emerald-800 hover:underline">{{ $application->name }}</a>
                                    <p class="mt-1 break-all text-xs text-slate-500">{{ $application->email }}</p>
                                    <p class="mt-1 text-xs text-slate-400">Application #{{ $application->id }}</p>
                                </td>
                                <td class="px-4 py-5 text-slate-700">
                                    <p>{{ $application->city }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $application->zip }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-5 text-slate-700">
                                    <time datetime="{{ $application->created_at?->toIso8601String() }}">{{ $application->created_at?->format('M j, Y') }}</time>
                                    <p class="mt-1 text-xs text-slate-500">{{ $application->created_at?->diffForHumans() }}</p>
                                </td>
                                <td class="px-4 py-5">
                                    <span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClasses }}">{{ $statusLabels[$application->status] ?? $application->status }}</span>
                                    @if ($application->reviewed_at)
                                        <p class="mt-2 text-xs text-slate-500">{{ $application->reviewer?->name ?? 'Former administrator' }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <a href="{{ route('admin.care-assistance.show', $application) }}" wire:navigate class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-slate-200 px-3 text-xs font-semibold text-[#0F3D3E] hover:border-emerald-300 hover:bg-emerald-50" aria-label="Review application from {{ $application->name }}">
                                        Review <span aria-hidden="true">&rarr;</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-16 text-center">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M9 10h6M9 14h6"/></svg>
                                    </div>
                                    <h3 class="mt-4 font-semibold text-slate-900">{{ $total ? 'No matching applications' : 'A place for every application' }}</h3>
                                    <p class="mt-2 text-sm text-slate-500">{{ $total ? 'Try another search or choose a different status.' : 'When someone applies for care assistance, their application will appear here.' }}</p>
                                    @if ($search !== '' || $status !== 'all')
                                        <button type="button" wire:click="clearFilters" class="mt-4 min-h-10 rounded-xl px-4 text-sm font-semibold text-emerald-800 underline underline-offset-4">Clear search and filters</button>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($applications->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">{{ $applications->links() }}</div>
            @endif
        </section>
        <p class="text-center text-xs leading-5 text-slate-500">Review decisions are saved internally. Contact applicants directly to share their outcome and arrange the next steps.</p>
    </div>
</div>
