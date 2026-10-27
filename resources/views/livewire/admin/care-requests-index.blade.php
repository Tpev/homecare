<div class="hc-page py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @error('delete')
        <x-alert color="red">{{ $message }}</x-alert>
    @enderror

    @error('booking')
        <x-alert color="red">{{ $message }}</x-alert>
    @enderror

    <x-card>
        <x-slot:header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Request Operations</h1>
                    <p class="mt-1 text-sm text-slate-600">Service dates, times and duration at a glance. Each schedule shows its time zone.</p>
                </div>
                <div class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-500">Showing {{ $requests->total() }} request(s)</div>
            </div>
        </x-slot:header>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">All</p>
                <p class="mt-1 text-2xl font-black text-slate-900">{{ number_format($summary['all']) }}</p>
            </div>
            <div class="rounded-xl border border-sky-200 bg-sky-50 p-3">
                <p class="text-xs uppercase tracking-[0.14em] text-sky-700">Open</p>
                <p class="mt-1 text-2xl font-black text-sky-900">{{ number_format($summary['open']) }}</p>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-3">
                <p class="text-xs uppercase tracking-[0.14em] text-indigo-700">Filled</p>
                <p class="mt-1 text-2xl font-black text-indigo-900">{{ number_format($summary['filled']) }}</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
                <p class="text-xs uppercase tracking-[0.14em] text-rose-700">Cancelled</p>
                <p class="mt-1 text-2xl font-black text-rose-900">{{ number_format($summary['cancelled']) }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                <p class="text-xs uppercase tracking-[0.14em] text-emerald-700">With booking</p>
                <p class="mt-1 text-2xl font-black text-emerald-900">{{ number_format($summary['with_booking']) }}</p>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.5fr)_7rem]">
            <div class="min-w-0 sm:col-span-2 lg:col-span-1">
                <x-input label="Search" placeholder="Title, family, email, city, ZIP" wire:model.blur="q" />
            </div>
            <div class="min-w-0">
                <x-select.styled label="Status" wire:model.live="status" :options="$statusOptions" />
            </div>
            <div class="min-w-0">
                <x-select.styled label="Type" wire:model.live="requestType" :options="$typeOptions" />
            </div>
            <div class="min-w-0">
                <x-select.styled label="Sort" wire:model.live="sort" :options="$sortOptions" />
            </div>
            <div class="min-w-0">
                <x-select.styled
                    label="Rows"
                    wire:model.live="perPage"
                    :options="[
                        ['label' => '25', 'value' => 25],
                        ['label' => '50', 'value' => 50],
                        ['label' => '100', 'value' => 100],
                    ]"
                />
            </div>
        </div>

        @if ($sort === 'start_soon')
            <p class="mt-3 text-xs text-slate-500">Next care date first, including recurring visits. Past, inactive and unscheduled requests appear last.</p>
        @endif

        <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2 xl:hidden">
            @forelse ($requests as $request)
                @php($schedule = $schedules[$request->id])
                <article wire:key="request-card-{{ $request->id }}" class="flex min-w-0 flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div>
                        <a href="{{ route('admin.requests.show', $request) }}" wire:navigate class="break-words text-base font-semibold text-slate-900 hover:text-cyan-800">{{ $request->title ?: 'Untitled request' }}</a>
                        <p class="mt-1 text-xs text-slate-500">#{{ $request->id }} · {{ $request->request_type === \App\Models\CareRequest::TYPE_RECURRING ? 'Recurring' : 'One-time' }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ collect([$request->city, $request->state, $request->zip])->filter()->implode(', ') ?: 'Location not set' }}</p>
                    </div>

                    <div class="my-3">
                        @include('livewire.admin.partials.care-request-list-schedule')
                    </div>

                    <div class="mb-3 text-xs text-slate-600">
                        <p>Family: <span class="font-medium text-slate-900">{{ $request->family?->name ?: 'Unknown' }}</span></p>
                        <p class="break-all text-slate-500">{{ $request->family?->email ?: 'No email' }}</p>
                        <p class="mt-1">Recipient: {{ $request->recipient?->full_name ?: 'Not specified' }}</p>
                    </div>

                    @include('livewire.admin.partials.care-request-list-staffing')

                    <div class="mt-auto pt-4">
                        <p class="mb-2 text-xs text-slate-500">Request submitted: {{ $request->created_at?->format('M j, Y · g:i A T') ?: 'Date unavailable' }}</p>
                        <a href="{{ route('admin.requests.show', $request) }}" wire:navigate class="block rounded-xl border border-cyan-200 bg-cyan-50 px-3 py-2 text-center text-sm font-semibold text-cyan-800 hover:bg-cyan-100">Open request</a>
                        @include('livewire.admin.partials.care-request-list-actions')
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500 md:col-span-2">No requests found for the current filters.</div>
            @endforelse
        </div>

        <div class="mt-4 hidden overflow-x-auto rounded-xl border border-slate-200 xl:block">
            <table class="w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="w-1/4 px-4 py-3">Request & family</th>
                        <th class="w-2/5 px-4 py-3 text-sky-900">Service date & time</th>
                        <th class="px-4 py-3">Staffing & status</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($requests as $request)
                        @php($schedule = $schedules[$request->id])
                        <tr wire:key="request-row-{{ $request->id }}">
                            <td class="px-4 py-4 align-top">
                                <a href="{{ route('admin.requests.show', $request) }}" wire:navigate class="break-words font-semibold text-slate-900 hover:text-cyan-800">{{ $request->title ?: 'Untitled request' }}</a>
                                <p class="mt-1 text-xs text-slate-500">#{{ $request->id }} · {{ $request->request_type === \App\Models\CareRequest::TYPE_RECURRING ? 'Recurring' : 'One-time' }}</p>
                                <p class="text-xs text-slate-600">{{ collect([$request->city, $request->state, $request->zip])->filter()->implode(', ') ?: 'Location not set' }}</p>
                                <p class="mt-3 text-xs font-medium text-slate-900">{{ $request->family?->name ?: 'Unknown family' }}</p>
                                <p class="break-all text-xs text-slate-500">{{ $request->family?->email ?: 'No email' }}</p>
                                <p class="mt-1 text-xs text-slate-600">Recipient: {{ $request->recipient?->full_name ?: 'Not specified' }}</p>
                                <p class="mt-2 text-xs text-slate-500">Request submitted: {{ $request->created_at?->format('M j, Y · g:i A T') ?: 'Date unavailable' }}</p>
                            </td>
                            <td class="px-4 py-4 align-top">
                                @include('livewire.admin.partials.care-request-list-schedule')
                            </td>
                            <td class="px-4 py-4 align-top">
                                @include('livewire.admin.partials.care-request-list-staffing')
                            </td>
                            <td class="w-36 px-4 py-4 align-top">
                                <a href="{{ route('admin.requests.show', $request) }}" wire:navigate class="inline-flex whitespace-nowrap rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2 text-xs font-semibold text-cyan-800 hover:bg-cyan-100">Open request</a>
                                @include('livewire.admin.partials.care-request-list-actions')
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No requests found for the current filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <div class="pt-2">{{ $requests->links() }}</div>
        </x-slot:footer>
    </x-card>
</div>
