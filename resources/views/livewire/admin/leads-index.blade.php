<div class="p-6 space-y-6" x-data="{ openId: null }">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Admin • Leads</h1>
            <p class="text-sm text-slate-500">Landing form submissions.</p>
        </div>
        <div class="hidden rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 sm:block">
            {{ $leads->total() }} leads
        </div>
    </div>

    {{-- Filters --}}
    <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
        <div class="md:col-span-5">
            <input
                type="text"
                wire:model.live="q"
                placeholder="Search email, phone, name, company, location, zip..."
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300"
            />
        </div>

        <div class="md:col-span-2">
            <select
                wire:model.live="type"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300"
            >
                <option value="all">All types</option>
                <option value="family">Family</option>
                <option value="caregiver">Caregiver</option>
                <option value="agency">Agency</option>
                <option value="general">General</option>
            </select>
        </div>

        <div class="md:col-span-2">
            <select
                wire:model.live="status"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300"
            >
                <option value="all">All status</option>
                <option value="new">New</option>
                <option value="contacted">Contacted</option>
                <option value="qualified">Qualified</option>
                <option value="closed">Closed</option>
            </select>
        </div>

        <div class="md:col-span-2">
            <select
                wire:model.live="perPage"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-300"
            >
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
                <option value="100">100 / page</option>
            </select>
        </div>

        <div class="md:col-span-1 flex md:justify-end">
            <button
                type="button"
                wire:click="$refresh"
                class="w-full md:w-auto rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50"
            >
                Refresh
            </button>
        </div>
    </div>

    {{-- Mobile cards --}}
    <div class="space-y-3 lg:hidden">
        @forelse($leads as $lead)
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $lead->name ?? 'Unknown lead' }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $lead->created_at->format('Y-m-d H:i') }}</p>
                    </div>
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs
                        @if($lead->status === 'new') bg-blue-50 text-blue-700
                        @elseif($lead->status === 'contacted') bg-amber-50 text-amber-700
                        @elseif($lead->status === 'qualified') bg-emerald-50 text-emerald-700
                        @else bg-slate-100 text-slate-700
                        @endif
                    ">
                        {{ $lead->status }}
                    </span>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-slate-600">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="uppercase tracking-[0.14em] text-slate-500">Type</p>
                        <p class="mt-1 font-medium text-slate-900">{{ $lead->lead_type }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="uppercase tracking-[0.14em] text-slate-500">Location</p>
                        <p class="mt-1 font-medium text-slate-900">{{ $lead->location ?? '—' }}</p>
                    </div>
                </div>
                <div class="mt-3 space-y-1 text-sm text-slate-700">
                    <p>{{ $lead->email ?? '—' }}</p>
                    <p>{{ $lead->phone ?? '—' }}</p>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button type="button" wire:click="openLead({{ $lead->id }})" class="rounded-xl bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        View
                    </button>
                    <div class="grid grid-cols-2 gap-2 col-span-2">
                        <button type="button" wire:click="updateStatus({{ $lead->id }}, 'contacted')" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">
                            Contacted
                        </button>
                        <button type="button" wire:click="updateStatus({{ $lead->id }}, 'qualified')" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50">
                            Qualified
                        </button>
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500">No leads found.</div>
        @endforelse
    </div>

    {{-- Table --}}
    <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white lg:block">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="text-left px-4 py-3 cursor-pointer select-none" wire:click="setSort('created_at')">Date</th>
                        <th class="text-left px-4 py-3 cursor-pointer select-none" wire:click="setSort('lead_type')">Type</th>
                        <th class="text-left px-4 py-3 cursor-pointer select-none" wire:click="setSort('status')">Status</th>
                        <th class="text-left px-4 py-3 cursor-pointer select-none" wire:click="setSort('name')">Name</th>
                        <th class="text-left px-4 py-3 cursor-pointer select-none" wire:click="setSort('email')">Email</th>
                        <th class="text-left px-4 py-3">Phone</th>
                        <th class="text-left px-4 py-3 cursor-pointer select-none" wire:click="setSort('company')">Company</th>
                        <th class="text-left px-4 py-3 cursor-pointer select-none" wire:click="setSort('location')">Location</th>
                        <th class="text-left px-4 py-3 cursor-pointer select-none" wire:click="setSort('zip')">ZIP</th>
                        <th class="text-right px-4 py-3">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse($leads as $lead)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 whitespace-nowrap text-slate-700">
                                {{ $lead->created_at->format('Y-m-d H:i') }}
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs bg-slate-100 text-slate-700">
                                    {{ $lead->lead_type }}
                                </span>
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs
                                    @if($lead->status === 'new') bg-blue-50 text-blue-700
                                    @elseif($lead->status === 'contacted') bg-amber-50 text-amber-700
                                    @elseif($lead->status === 'qualified') bg-emerald-50 text-emerald-700
                                    @else bg-slate-100 text-slate-700
                                    @endif
                                ">
                                    {{ $lead->status }}
                                </span>
                            </td>

                            <td class="px-4 py-3 text-slate-900">{{ $lead->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $lead->email ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $lead->phone ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $lead->company ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $lead->location ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $lead->zip ?? '—' }}</td>

                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-2" x-data="{ open: false }">
                                    <button
                                        type="button"
                                        wire:click="openLead({{ $lead->id }})"
                                        class="rounded-xl bg-slate-900 text-white px-3 py-1.5 text-xs hover:bg-slate-800"
                                    >
                                        View
                                    </button>

                                    {{-- Dropdown --}}
                                    <div class="relative">
                                        <button
                                            type="button"
                                            @click="open = !open"
                                            class="rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs hover:bg-slate-50"
                                            aria-haspopup="true"
                                            aria-expanded="false"
                                        >
                                            ⋮
                                        </button>

                                        <div
                                            x-cloak
                                            x-show="open"
                                            @click.outside="open = false"
                                            class="absolute right-0 mt-2 w-48 rounded-xl border border-slate-200 bg-white shadow-lg overflow-hidden z-20"
                                        >
                                            <button type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50"
                                                    wire:click="updateStatus({{ $lead->id }}, 'new')" @click="open=false">
                                                Mark as New
                                            </button>
                                            <button type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50"
                                                    wire:click="updateStatus({{ $lead->id }}, 'contacted')" @click="open=false">
                                                Mark as Contacted
                                            </button>
                                            <button type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50"
                                                    wire:click="updateStatus({{ $lead->id }}, 'qualified')" @click="open=false">
                                                Mark as Qualified
                                            </button>
                                            <button type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50"
                                                    wire:click="updateStatus({{ $lead->id }}, 'closed')" @click="open=false">
                                                Mark as Closed
                                            </button>

                                            <div class="h-px bg-slate-200"></div>

                                            <button
                                                type="button"
                                                class="w-full text-left px-3 py-2 text-sm text-red-700 hover:bg-red-50"
                                                onclick="return confirm('Delete this lead?')"
                                                wire:click="deleteLead({{ $lead->id }})"
                                                @click="open=false"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-10 text-center text-slate-500" colspan="10">
                                No leads found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-200">
            {{ $leads->links() }}
        </div>
    </div>

    {{-- Modal (Tailwind + Alpine) --}}
    <div
        x-cloak
        x-data="{ show: false }"
        x-init="
            $watch('$wire.selectedLeadId', (v) => { show = !!v })
        "
    >
        <div x-show="show" class="fixed inset-0 z-50">
            <div class="absolute inset-0 bg-black/40" @click="$wire.closeLead()"></div>

            <div class="relative mx-3 mt-4 h-[calc(100vh-2rem)] w-auto overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-xl sm:mx-auto sm:mt-10 sm:h-auto sm:max-h-[85vh] sm:w-full sm:max-w-3xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-lg font-bold text-slate-900">Lead details</div>
                        @if($this->selectedLead)
                            <div class="text-sm text-slate-500">
                                #{{ $this->selectedLead->id }} • {{ $this->selectedLead->created_at->format('Y-m-d H:i') }}
                            </div>
                        @endif
                    </div>

                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm hover:bg-slate-50"
                        wire:click="closeLead"
                    >
                        Close
                    </button>
                </div>

                @if($this->selectedLead)
                    <div class="mt-4 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="rounded-xl border border-slate-200 p-3">
                                <div class="text-xs text-slate-500">Type</div>
                                <div class="font-semibold text-slate-900">{{ $this->selectedLead->lead_type }}</div>
                            </div>

                            <div class="rounded-xl border border-slate-200 p-3">
                                <div class="text-xs text-slate-500">Status</div>
                                <div class="font-semibold text-slate-900">{{ $this->selectedLead->status }}</div>
                            </div>

                            <div class="rounded-xl border border-slate-200 p-3">
                                <div class="text-xs text-slate-500">Email</div>
                                <div class="font-semibold text-slate-900">{{ $this->selectedLead->email ?? '—' }}</div>
                            </div>

                            <div class="rounded-xl border border-slate-200 p-3">
                                <div class="text-xs text-slate-500">Phone</div>
                                <div class="font-semibold text-slate-900">{{ $this->selectedLead->phone ?? '—' }}</div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="text-sm font-semibold text-slate-900 mb-2">Raw payload</div>
                            <pre class="text-xs whitespace-pre-wrap text-slate-700">{{ json_encode($this->selectedLead->data ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>

                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="text-sm font-semibold text-slate-900 mb-2">Tracking</div>
                            <div class="text-sm text-slate-700 space-y-1">
                                <div><span class="text-slate-500">Source:</span> {{ $this->selectedLead->source_url ?? '—' }}</div>
                                <div><span class="text-slate-500">Referrer:</span> {{ $this->selectedLead->referrer_url ?? '—' }}</div>
                                <div><span class="text-slate-500">IP:</span> {{ $this->selectedLead->ip ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="mt-4 text-slate-500">No lead selected.</div>
                @endif
            </div>
        </div>
    </div>
</div>
