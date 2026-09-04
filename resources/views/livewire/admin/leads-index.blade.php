<div class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Admin CRM</p>
                <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-950">Lead command center</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">
                    Track family care leads and referral source recruiting from first touch to conversion.
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <button
                    type="button"
                    wire:click="toggleCreateForm"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800"
                >
                    {{ $showCreateForm ? 'Close new lead' : ($pipeline === \App\Models\Lead::TYPE_REFERRAL ? 'Add referral source' : 'Add family lead') }}
                </button>
            </div>
        </header>

        <section class="rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
            <div class="grid gap-2 md:grid-cols-2">
                @foreach ($pipelineOptions as $value => $label)
                    <button
                        type="button"
                        wire:click="setPipeline('{{ $value }}')"
                        class="rounded-xl px-4 py-3 text-left text-sm transition {{ $pipeline === $value ? 'bg-slate-950 text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-50' }}"
                    >
                        <span class="block font-semibold">{{ $label }}</span>
                        <span class="mt-1 block text-xs {{ $pipeline === $value ? 'text-slate-200' : 'text-slate-500' }}">
                            {{ $value === \App\Models\Lead::TYPE_REFERRAL ? 'PCPs, case managers, hospitals, community partners.' : 'People or families considering care.' }}
                        </span>
                    </button>
                @endforeach
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Total</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $stats['total'] }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Open</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $stats['open'] }}</p>
            </div>
            <div class="rounded-2xl border border-rose-100 bg-rose-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-rose-600">Follow-up due</p>
                <p class="mt-2 text-3xl font-bold text-rose-700">{{ $stats['due'] }}</p>
            </div>
            <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700">Unassigned</p>
                <p class="mt-2 text-3xl font-bold text-amber-800">{{ $stats['unassigned'] }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">{{ $pipeline === \App\Models\Lead::TYPE_REFERRAL ? 'Active sources' : 'Converted' }}</p>
                <p class="mt-2 text-3xl font-bold text-emerald-800">{{ $stats['converted'] }}</p>
            </div>
        </section>

        @if ($showCreateForm)
            <form wire:submit.prevent="createLead" class="rounded-2xl border border-emerald-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-950">{{ $pipeline === \App\Models\Lead::TYPE_REFERRAL ? 'New referral source' : 'New family lead' }}</h2>
                        <p class="text-sm text-slate-500">Capture enough information for the next person to continue the conversation.</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Contact name</span>
                        <input type="text" wire:model.blur="leadForm.name" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                        @error('leadForm.name') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">{{ $pipeline === \App\Models\Lead::TYPE_REFERRAL ? 'Role' : 'Relationship / role' }}</span>
                        <input type="text" wire:model.blur="leadForm.contact_role" placeholder="{{ $pipeline === \App\Models\Lead::TYPE_REFERRAL ? 'PCP, case manager, discharge planner' : 'Daughter, spouse, self' }}" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">{{ $pipeline === \App\Models\Lead::TYPE_REFERRAL ? 'Organization' : 'Company / organization' }}</span>
                        <input type="text" wire:model.blur="leadForm.company" placeholder="{{ $pipeline === \App\Models\Lead::TYPE_REFERRAL ? 'Practice, hospital, agency' : 'Optional' }}" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Owner</span>
                        <select wire:model.live="leadForm.assigned_admin_id" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                            <option value="">Unassigned</option>
                            @foreach ($admins as $adminId => $adminName)
                                <option value="{{ $adminId }}">{{ $adminName }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Email</span>
                        <input type="email" wire:model.blur="leadForm.email" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Phone</span>
                        <input type="tel" wire:model.blur="leadForm.phone" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">City / area</span>
                        <input type="text" wire:model.blur="leadForm.location" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">ZIP</span>
                        <input type="text" wire:model.blur="leadForm.zip" inputmode="numeric" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Stage</span>
                        <select wire:model.live="leadForm.status" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                            @foreach ($stageOptions as $stageValue => $stageLabel)
                                <option value="{{ $stageValue }}">{{ $stageLabel }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Priority</span>
                        <select wire:model.live="leadForm.priority" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                            @foreach ($priorityOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Source</span>
                        <select wire:model.live="leadForm.source" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                            <option value="">Unknown</option>
                            @foreach ($sourceOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Source detail</span>
                        <input type="text" wire:model.blur="leadForm.source_detail" placeholder="Specific person, campaign, event, or page" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Follow-up</span>
                        <input type="datetime-local" wire:model.change="leadForm.next_follow_up_at" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                    </label>
                </div>

                <label class="mt-4 block">
                    <span class="text-sm font-medium text-slate-700">First note</span>
                    <textarea wire:model.blur="leadForm.notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="How they found us, care need, referral context, next step..."></textarea>
                </label>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="toggleCreateForm" class="min-h-11 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="min-h-11 rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Create lead</button>
                </div>
            </form>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 lg:grid-cols-12">
                <div class="lg:col-span-4">
                    <input type="search" wire:model.blur="q" placeholder="Search name, phone, source, location..." class="min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-100">
                </div>
                <select wire:model.live="status" class="min-h-11 rounded-xl border border-slate-200 px-3 py-2 text-sm lg:col-span-2">
                    <option value="all">All stages</option>
                    @foreach ($stageOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select wire:model.live="assigned" class="min-h-11 rounded-xl border border-slate-200 px-3 py-2 text-sm lg:col-span-2">
                    <option value="all">All owners</option>
                    <option value="me">Assigned to me</option>
                    <option value="unassigned">Unassigned</option>
                    @foreach ($admins as $adminId => $adminName)
                        <option value="{{ $adminId }}">{{ $adminName }}</option>
                    @endforeach
                </select>
                <select wire:model.live="source" class="min-h-11 rounded-xl border border-slate-200 px-3 py-2 text-sm lg:col-span-2">
                    <option value="all">All sources</option>
                    @foreach ($sourceOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select wire:model.live="priority" class="min-h-11 rounded-xl border border-slate-200 px-3 py-2 text-sm lg:col-span-1">
                    <option value="all">Any priority</option>
                    @foreach ($priorityOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select wire:model.live="perPage" class="min-h-11 rounded-xl border border-slate-200 px-3 py-2 text-sm lg:col-span-1">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </section>

        <section class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-950">Pipeline board</h2>
                    <p class="text-sm text-slate-500">Drag cards across stages, or open a lead to log outreach and details.</p>
                </div>
            </div>

            <div
                class="overflow-x-auto pb-2"
                x-data="{ draggingLeadId: null, overStage: null }"
                @dragend.window="draggingLeadId = null; overStage = null"
            >
                <div class="grid min-w-[1040px] gap-3" style="grid-template-columns: repeat({{ count($stageOptions) }}, minmax(220px, 1fr));">
                    @foreach ($stageOptions as $stageValue => $stageLabel)
                        @php
                            $stageLeads = $boardLeads->get($stageValue, collect());
                            $stageTotal = (int) ($boardStageCounts[$stageValue] ?? 0);
                        @endphp
                        <div
                            class="rounded-2xl border border-slate-200 p-3 shadow-sm transition"
                            :class="overStage === '{{ $stageValue }}' ? 'bg-emerald-50 ring-2 ring-emerald-300' : 'bg-white'"
                            @dragover.prevent="overStage = '{{ $stageValue }}'"
                            @dragenter.prevent="overStage = '{{ $stageValue }}'"
                            @dragleave="if (overStage === '{{ $stageValue }}') overStage = null"
                            @drop.prevent="$wire.moveLeadToStage(draggingLeadId || $event.dataTransfer.getData('text/plain'), '{{ $stageValue }}'); draggingLeadId = null; overStage = null"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-sm font-bold text-slate-900">{{ $stageLabel }}</h3>
                                <div class="flex shrink-0 items-center gap-1.5">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $stageTotal }}</span>
                                    <button
                                        type="button"
                                        wire:click="exportStage('{{ $stageValue }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="exportStage('{{ $stageValue }}')"
                                        class="inline-flex min-h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800 disabled:cursor-wait disabled:opacity-60"
                                        aria-label="Export all {{ $stageLabel }} leads"
                                        title="Export all {{ $stageLabel }} leads with activity history"
                                    >
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                            <path d="M10 2.75v9.5m0 0 3.5-3.5M10 12.25l-3.5-3.5M3.25 13.75v2.5h13.5v-2.5" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        Export
                                    </button>
                                </div>
                            </div>

                            <div class="mt-3 space-y-2">
                                @forelse ($stageLeads as $lead)
                                    <article
                                        wire:key="crm-board-lead-{{ $lead->id }}"
                                        draggable="true"
                                        @dragstart="
                                            draggingLeadId = {{ $lead->id }};
                                            $event.dataTransfer.effectAllowed = 'move';
                                            $event.dataTransfer.setData('text/plain', '{{ $lead->id }}');
                                        "
                                        @dragend="draggingLeadId = null; overStage = null"
                                        :class="draggingLeadId === {{ $lead->id }} ? 'opacity-50 ring-2 ring-emerald-300' : ''"
                                        class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-left shadow-sm transition hover:border-emerald-200 hover:bg-emerald-50/40 cursor-grab active:cursor-grabbing"
                                    >
                                        <div class="flex items-start justify-between gap-2">
                                            <button type="button" wire:click="openLead({{ $lead->id }})" class="min-w-0 flex-1 text-left">
                                                <span class="block truncate text-sm font-semibold text-slate-950">{{ $lead->name ?: 'Unnamed lead' }}</span>
                                                <span class="mt-1 block truncate text-xs text-slate-500">{{ $lead->company ?: $lead->contact_role ?: $lead->phone ?: $lead->email ?: 'No details yet' }}</span>
                                            </button>
                                            <span class="rounded-lg border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">Drag</span>
                                        </div>

                                        <div class="mt-2 flex items-center justify-between gap-2 text-xs">
                                            <span class="rounded-full px-2 py-0.5 font-semibold ring-1 {{ $this->priorityBadgeClass($lead->priority) }}">{{ ucfirst($lead->priority ?: 'normal') }}</span>
                                            <span class="{{ $lead->next_follow_up_at?->isPast() ? 'font-semibold text-rose-700' : 'text-slate-500' }}">
                                                {{ $lead->next_follow_up_at ? $lead->next_follow_up_at->format('M j, g:i A') : 'No follow-up' }}
                                            </span>
                                        </div>
                                    </article>
                                @empty
                                    <div class="rounded-xl border border-dashed border-slate-200 px-3 py-6 text-center text-xs text-slate-400">No leads here</div>
                                @endforelse

                                @if ($stageLeads->count() < $stageTotal)
                                    <button
                                        type="button"
                                        wire:click="loadMoreBoardStage('{{ $stageValue }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="loadMoreBoardStage('{{ $stageValue }}')"
                                        class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 disabled:cursor-wait disabled:opacity-60"
                                    >
                                        Load more · showing {{ $stageLeads->count() }} of {{ $stageTotal }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h2 class="text-lg font-bold text-slate-950">Lead list</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.12em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Lead</th>
                            <th class="px-4 py-3 text-left">Stage</th>
                            <th class="px-4 py-3 text-left">Owner</th>
                            <th class="px-4 py-3 text-left">Source</th>
                            <th class="px-4 py-3 text-left">Last contact</th>
                            <th class="px-4 py-3 text-left">Follow-up</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($leads as $lead)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <button type="button" wire:click="openLead({{ $lead->id }})" class="text-left">
                                        <span class="block font-semibold text-slate-950">{{ $lead->name ?: 'Unnamed lead' }}</span>
                                        <span class="mt-1 block text-xs text-slate-500">{{ $lead->email ?: $lead->phone ?: 'No contact yet' }}</span>
                                        <span class="mt-1 block text-xs text-slate-500">{{ $lead->company ?: $lead->location ?: $lead->zip ?: '' }}</span>
                                    </button>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold ring-1 {{ $this->stageBadgeClass($lead->status) }}">{{ $lead->stageLabel() }}</span>
                                    <span class="ml-1 rounded-full px-2 py-1 text-xs font-semibold ring-1 {{ $this->priorityBadgeClass($lead->priority) }}">{{ ucfirst($lead->priority ?: 'normal') }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $lead->assignedAdmin?->name ?: 'Unassigned' }}</td>
                                <td class="px-4 py-3 text-slate-700">
                                    <span class="block">{{ $lead->sourceLabel() }}</span>
                                    @if ($lead->source_detail)
                                        <span class="mt-1 block max-w-[220px] truncate text-xs text-slate-500">{{ $lead->source_detail }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $lead->last_contacted_at?->format('M j, g:i A') ?: 'Never' }}</td>
                                <td class="px-4 py-3">
                                    <span class="{{ $lead->next_follow_up_at?->isPast() ? 'font-semibold text-rose-700' : 'text-slate-700' }}">
                                        {{ $lead->next_follow_up_at?->format('M j, g:i A') ?: 'None' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        @if (! $lead->assigned_admin_id)
                                            <button type="button" wire:click="assignToMe({{ $lead->id }})" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Take</button>
                                        @endif
                                        <button type="button" wire:click="openLead({{ $lead->id }})" class="rounded-lg bg-slate-950 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">Open</button>
                                        <button
                                            type="button"
                                            onclick="return confirm('Delete this lead? This cannot be undone.')"
                                            wire:click="deleteLead({{ $lead->id }})"
                                            class="rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">No leads match these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 p-4">
                {{ $leads->links() }}
            </div>
        </section>
    </div>

    @if ($selectedLead)
        <div class="fixed inset-0 z-50">
            <button type="button" class="absolute inset-0 bg-slate-950/40" wire:click="closeLead" aria-label="Close lead"></button>

            <div class="absolute right-0 top-0 h-full w-full max-w-3xl overflow-y-auto bg-white shadow-2xl">
                <div class="sticky top-0 z-10 border-b border-slate-200 bg-white/95 px-5 py-4 backdrop-blur">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">{{ $selectedLead->pipelineLabel() }}</p>
                            <h2 class="mt-1 text-2xl font-bold text-slate-950">{{ $selectedLead->name ?: 'Unnamed lead' }}</h2>
                            <p class="mt-1 text-sm text-slate-500">Created {{ $selectedLead->created_at->format('M j, Y g:i A') }}. Updated {{ $selectedLead->updated_at->diffForHumans() }}.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                onclick="return confirm('Delete this lead? This cannot be undone.')"
                                wire:click="deleteLead({{ $selectedLead->id }})"
                                class="rounded-xl border border-rose-200 bg-white px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50"
                            >
                                Delete
                            </button>
                            <button type="button" wire:click="closeLead" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Close</button>
                        </div>
                    </div>
                </div>

                <div class="space-y-6 px-5 py-5">
                    <section class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Stage</p>
                            <p class="mt-1 text-sm font-bold text-slate-950">{{ $selectedLead->stageLabel() }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Owner</p>
                            <p class="mt-1 text-sm font-bold text-slate-950">{{ $selectedLead->assignedAdmin?->name ?: 'Unassigned' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Next follow-up</p>
                            <p class="mt-1 text-sm font-bold {{ $selectedLead->next_follow_up_at?->isPast() ? 'text-rose-700' : 'text-slate-950' }}">{{ $selectedLead->next_follow_up_at?->format('M j, g:i A') ?: 'None' }}</p>
                        </div>
                    </section>

                    <form wire:submit.prevent="saveLead" class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-slate-950">Lead profile</h3>
                            <button type="submit" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Save</button>
                        </div>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Contact name</span>
                                <input type="text" wire:model.blur="leadForm.name" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Email</span>
                                <input type="email" wire:model.blur="leadForm.email" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Phone</span>
                                <input type="tel" wire:model.blur="leadForm.phone" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Role / relationship</span>
                                <input type="text" wire:model.blur="leadForm.contact_role" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Organization</span>
                                <input type="text" wire:model.blur="leadForm.company" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">City / area</span>
                                <input type="text" wire:model.blur="leadForm.location" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">ZIP</span>
                                <input type="text" wire:model.blur="leadForm.zip" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Owner</span>
                                <select wire:model.live="leadForm.assigned_admin_id" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                    <option value="">Unassigned</option>
                                    @foreach ($admins as $adminId => $adminName)
                                        <option value="{{ $adminId }}">{{ $adminName }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Stage</span>
                                <select wire:model.live="leadForm.status" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                    @foreach ($selectedLead->stageOptions() as $stageValue => $stageLabel)
                                        <option value="{{ $stageValue }}">{{ $stageLabel }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Priority</span>
                                <select wire:model.live="leadForm.priority" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                    @foreach ($priorityOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Source</span>
                                <select wire:model.live="leadForm.source" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                    <option value="">Unknown</option>
                                    @foreach ($sourceOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Source detail</span>
                                <input type="text" wire:model.blur="leadForm.source_detail" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Last contacted</span>
                                <input type="datetime-local" wire:model.change="leadForm.last_contacted_at" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Next follow-up</span>
                                <input type="datetime-local" wire:model.change="leadForm.next_follow_up_at" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            </label>
                        </div>
                    </form>

                    <form wire:submit.prevent="logActivity" class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4">
                        <h3 class="text-lg font-bold text-slate-950">Log outreach or note</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
                            <select wire:model.live="activityForm.type" class="min-h-11 rounded-xl border border-emerald-100 bg-white px-3 py-2 text-sm">
                                @foreach ($activityTypeOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <input type="datetime-local" wire:model.change="activityForm.occurred_at" class="min-h-11 rounded-xl border border-emerald-100 bg-white px-3 py-2 text-sm">
                            <input type="datetime-local" wire:model.change="activityForm.next_follow_up_at" class="min-h-11 rounded-xl border border-emerald-100 bg-white px-3 py-2 text-sm" title="Next follow-up">
                        </div>
                        <textarea wire:model.blur="activityForm.body" rows="3" class="mt-3 w-full rounded-xl border border-emerald-100 bg-white px-3 py-2 text-sm" placeholder="What happened? What is the next step?"></textarea>
                        @error('activityForm.body') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                        <div class="mt-3 flex justify-end">
                            <button type="submit" class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Add to timeline</button>
                        </div>
                    </form>

                    <section class="rounded-2xl border border-slate-200 bg-white p-4">
                        <h3 class="text-lg font-bold text-slate-950">Timeline</h3>
                        <div class="mt-4 space-y-3">
                            @forelse ($selectedLead->activities as $activity)
                                <article class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="text-sm font-semibold text-slate-950">{{ $activity->summary ?: ucfirst($activity->type) }}</div>
                                        <div class="text-xs text-slate-500">{{ $activity->occurred_at?->format('M j, Y g:i A') ?: $activity->created_at->format('M j, Y g:i A') }}</div>
                                    </div>
                                    @if ($activity->body)
                                        <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $activity->body }}</p>
                                    @endif
                                    <p class="mt-2 text-xs text-slate-500">{{ $activity->actor?->name ?: 'System' }}</p>
                                </article>
                            @empty
                                <div class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">No activity yet.</div>
                            @endforelse
                        </div>
                    </section>
                </div>
            </div>
        </div>
    @endif
</div>
