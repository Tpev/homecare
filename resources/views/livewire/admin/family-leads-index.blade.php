<div class="min-h-screen bg-[#F7F3EC] px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-[1600px] space-y-5">
        <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-[#E9F4EF] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-800">Family acquisition</span>
                    <span class="text-xs font-semibold text-slate-500">CRM workspace</span>
                </div>
                <h1 class="mt-2 text-4xl font-bold tracking-tight text-[#173F35]">Family leads</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">One record from first enquiry through seven call attempts, qualification, assessment, and care start.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.family-acquisition.overview') }}" wire:navigate class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#D9CEC0] bg-white px-4 py-2 text-sm font-bold text-[#23483F] hover:bg-[#FFFBF4]">Management overview</a>
                <a href="{{ route('sdr.family-calling') }}" wire:navigate class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[#23483F] bg-white px-4 py-2 text-sm font-bold text-[#23483F] hover:bg-emerald-50">Open calling console</a>
                <button type="button" wire:click="toggleCreateForm" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#23483F] px-5 py-2 text-sm font-bold text-white shadow-sm hover:bg-[#173F35]">{{ $showCreateForm ? 'Cancel' : '+ Add family lead' }}</button>
            </div>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach([
                ['New', $stats['new'], 'border-sky-200 bg-sky-50 text-sky-900'],
                ['Calls due', $stats['due'], 'border-rose-200 bg-rose-50 text-rose-900'],
                ['Callbacks', $stats['callbacks'], 'border-amber-200 bg-amber-50 text-amber-900'],
                ['Qualified', $stats['qualified'], 'border-emerald-200 bg-emerald-50 text-emerald-900'],
                ['Care started', $stats['converted'], 'border-[#D9CEC0] bg-white text-slate-950'],
            ] as [$label, $value, $style])
                <div class="rounded-2xl border p-4 shadow-sm {{ $style }}">
                    <p class="text-[11px] font-bold uppercase tracking-[0.16em] opacity-70">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-bold">{{ $value }}</p>
                </div>
            @endforeach
        </section>

        @if($showCreateForm)
            <section class="rounded-[1.75rem] border border-[#D9CEC0] bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#A55343]">Manual entry</p>
                        <h2 class="mt-1 text-2xl font-bold text-slate-950">Add a family enquiry</h2>
                    </div>
                    <p class="text-xs text-slate-500">This enters the same queue and reporting funnel as a Meta lead.</p>
                </div>
                <form wire:submit="createLead" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    @foreach([
                        ['name', 'Contact name', 'text', 'Claire Thompson'],
                        ['phone', 'Phone', 'tel', '919-555-0101'],
                        ['email', 'Email', 'email', 'claire@example.com'],
                        ['location', 'Location', 'text', 'Raleigh, NC'],
                        ['relationship', 'Relationship', 'text', 'Daughter'],
                        ['care_for', 'Who needs care?', 'text', 'Mother, 78'],
                        ['urgency', 'How soon?', 'text', 'This week'],
                        ['schedule', 'Likely schedule', 'text', 'Weekday afternoons'],
                    ] as [$field, $label, $type, $placeholder])
                        <label class="block">
                            <span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-600">{{ $label }}</span>
                            <input type="{{ $type }}" wire:model="leadForm.{{ $field }}" placeholder="{{ $placeholder }}" class="mt-2 min-h-11 w-full rounded-xl border-slate-200 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-100">
                            @error('leadForm.'.$field)<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                        </label>
                    @endforeach
                    <label class="block md:col-span-2">
                        <span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-600">Care needs</span>
                        <input type="text" wire:model="leadForm.care_needs" placeholder="Companionship, meals, transportation" class="mt-2 min-h-11 w-full rounded-xl border-slate-200 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-100">
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-600">Priority</span>
                        <select wire:model="leadForm.priority" class="mt-2 min-h-11 w-full rounded-xl border-slate-200 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-100">
                            <option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option>
                        </select>
                    </label>
                    <label class="block md:col-span-2 xl:col-span-3">
                        <span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-600">What did they tell us?</span>
                        <textarea wire:model="leadForm.details" rows="3" placeholder="Context the SDR should see before calling…" class="mt-2 w-full rounded-xl border-slate-200 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-100"></textarea>
                    </label>
                    <div class="flex items-end">
                        <button type="submit" class="min-h-11 w-full rounded-xl bg-[#C96B55] px-5 py-2 text-sm font-bold text-white hover:bg-[#B85C49]">Create lead</button>
                    </div>
                </form>
            </section>
        @endif

        <section class="rounded-[1.5rem] border border-[#D9CEC0] bg-white p-4 shadow-sm">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(260px,1.5fr)_repeat(3,minmax(160px,.55fr))]">
                <label class="relative block">
                    <span class="sr-only">Search leads</span>
                    <input type="search" wire:model.live.debounce.300ms="q" placeholder="Search name, phone, location, campaign…" class="min-h-11 w-full rounded-xl border-slate-200 pl-4 pr-3 text-sm focus:border-emerald-600 focus:ring-emerald-100">
                </label>
                <select wire:model.live="status" aria-label="Filter by status" class="min-h-11 rounded-xl border-slate-200 text-sm focus:border-emerald-600 focus:ring-emerald-100">
                    <option value="active">Active pipeline</option>
                    <option value="all">All stages</option>
                    @foreach($stageOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
                <select wire:model.live="source" aria-label="Filter by source" class="min-h-11 rounded-xl border-slate-200 text-sm focus:border-emerald-600 focus:ring-emerald-100">
                    <option value="all">All sources</option>
                    <option value="meta_lead_ads">Meta lead ads</option>
                    <option value="manual_crm">Manual CRM</option>
                </select>
                <select wire:model.live="assigned" aria-label="Filter by owner" class="min-h-11 rounded-xl border-slate-200 text-sm focus:border-emerald-600 focus:ring-emerald-100">
                    <option value="all">All owners</option>
                    <option value="unassigned">Unassigned</option>
                    @foreach($assigneeOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
            </div>
        </section>

        <section class="grid items-start gap-5 {{ $selectedLead ? '2xl:grid-cols-[minmax(720px,1fr)_480px]' : '' }}">
            <div class="min-w-0 overflow-hidden rounded-[1.6rem] border border-[#D9CEC0] bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left">
                        <thead class="bg-[#FFFBF4]">
                            <tr class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
                                <th class="px-5 py-3">Family</th>
                                <th class="px-4 py-3">Stage</th>
                                <th class="px-4 py-3">Reach attempts</th>
                                <th class="px-4 py-3">Next action</th>
                                <th class="px-4 py-3">Source</th>
                                <th class="px-4 py-3">Owner</th>
                                <th class="px-4 py-3"><span class="sr-only">Lead actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($leads as $lead)
                                @php
                                    $callableStage = in_array($lead->status, \App\Support\FamilyLeadOutreach::CALLABLE_STAGES, true);
                                    $isDue = $callableStage && (!$lead->next_follow_up_at || $lead->next_follow_up_at->isPast());
                                    $terminal = in_array($lead->status, ['converted', 'unreachable', 'not_fit', 'lost', 'closed'], true);
                                @endphp
                                <tr wire:key="family-lead-{{ $lead->id }}" class="group cursor-pointer transition hover:bg-emerald-50/40 {{ $selectedLeadId === $lead->id ? 'bg-emerald-50/70' : '' }}" wire:click="openLead({{ $lead->id }})">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold {{ $lead->priority === 'urgent' ? 'bg-rose-100 text-rose-800' : 'bg-[#F0EAE1] text-[#23483F]' }}">{{ str($lead->name)->substr(0, 1)->upper() }}</span>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-bold text-slate-950">{{ $lead->name }}</p>
                                                <p class="mt-0.5 truncate text-xs text-slate-500">{{ $lead->phone }} · {{ $lead->location ?: 'No location' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{{ $lead->stageLabel() }}</span></td>
                                    <td class="whitespace-nowrap px-4 py-4">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-bold text-slate-800">{{ $lead->unanswered_attempt_count }}/7</span>
                                            <span class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-[#C96B55]" style="width: {{ min(100, ($lead->unanswered_attempt_count / 7) * 100) }}%"></span></span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4">
                                        @if($terminal)
                                            <span class="text-xs font-semibold text-slate-400">Sequence complete</span>
                                        @elseif($callableStage && $lead->next_follow_up_at)
                                            <span class="text-xs font-bold {{ $isDue ? 'text-rose-700' : 'text-slate-700' }}">{{ $isDue ? 'Due ' : '' }}{{ $lead->next_follow_up_at->format('M j, g:i A') }}</span>
                                        @elseif($isDue)
                                            <span class="text-xs font-bold text-rose-700">Call now</span>
                                        @else
                                            <span class="text-xs font-semibold text-slate-400">No family call due</span>
                                        @endif
                                    </td>
                                    <td class="max-w-48 px-4 py-4">
                                        <p class="truncate text-xs font-bold text-slate-700">{{ $lead->source === 'meta_lead_ads' ? 'Meta · '.ucfirst((string) data_get($lead->data, 'meta.platform', 'lead ad')) : 'Manual CRM' }}</p>
                                        <p class="mt-0.5 truncate text-xs text-slate-400">{{ data_get($lead->data, 'meta.campaign_name', $lead->source_detail) }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-xs font-semibold text-slate-600">{{ $lead->assignedAdmin?->name ?: 'Unassigned' }}</td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center justify-end gap-3 text-sm font-bold">
                                            <span class="text-emerald-700">View</span>
                                            <button
                                                type="button"
                                                wire:click.stop="deleteLead({{ $lead->id }})"
                                                wire:confirm="Delete {{ $lead->name }} permanently? The lead and its complete activity timeline will be removed."
                                                class="rounded-lg px-2 py-1 text-rose-700 hover:bg-rose-50 hover:text-rose-800"
                                                aria-label="Delete {{ $lead->name }}"
                                            >Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-6 py-16 text-center text-sm text-slate-500">No family leads match these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($leads->hasPages())<div class="border-t border-slate-200 px-5 py-4">{{ $leads->links() }}</div>@endif
            </div>

            @if($selectedLead)
                @php
                    $form = data_get($selectedLead->data, 'form_answers', []);
                    $meta = data_get($selectedLead->data, 'meta', []);
                    $responseMinutes = $selectedLead->first_call_at && $selectedLead->submitted_at ? (int) $selectedLead->submitted_at->diffInMinutes($selectedLead->first_call_at) : null;
                @endphp
                <aside class="overflow-hidden rounded-[1.6rem] border border-[#D9CEC0] bg-white shadow-lg 2xl:sticky 2xl:top-24 2xl:max-h-[calc(100vh-7rem)] 2xl:overflow-y-auto">
                    <div class="border-b border-[#E7DED2] bg-[#23483F] p-5 text-white">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#F3D4C9]">Family lead detail</p>
                                <h2 class="mt-1 text-2xl font-bold text-white">{{ $selectedLead->name }}</h2>
                                <p class="mt-1 text-sm text-white/70">{{ $selectedLead->phone }} · {{ $selectedLead->email }}</p>
                            </div>
                            <button type="button" wire:click="closeLead" class="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-xl text-white hover:bg-white/20" aria-label="Close lead detail">×</button>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-xl bg-white/10 p-2"><p class="text-[10px] uppercase tracking-wide text-white/60">Reach attempts</p><p class="mt-1 font-bold">{{ $selectedLead->unanswered_attempt_count }}/7</p><p class="text-[9px] text-white/50">{{ $selectedLead->call_attempt_count }} total calls</p></div>
                            <div class="rounded-xl bg-white/10 p-2"><p class="text-[10px] uppercase tracking-wide text-white/60">First call</p><p class="mt-1 font-bold">{{ $responseMinutes !== null ? $responseMinutes.'m' : '—' }}</p></div>
                            <div class="rounded-xl bg-white/10 p-2"><p class="text-[10px] uppercase tracking-wide text-white/60">Owner</p><p class="mt-1 truncate font-bold">{{ $selectedLead->assignedAdmin?->name ?: 'None' }}</p></div>
                        </div>
                    </div>

                    <div class="space-y-5 p-5">
                        <section>
                            <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Lead management</p>
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <label class="block"><span class="text-xs font-semibold text-slate-600">Stage</span><select wire:model="selectedStatus" class="mt-1 min-h-10 w-full rounded-xl border-slate-200 text-xs">@foreach($stageOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                                <label class="block"><span class="text-xs font-semibold text-slate-600">Priority</span><select wire:model="selectedPriority" class="mt-1 min-h-10 w-full rounded-xl border-slate-200 text-xs"><option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select></label>
                                <label class="block"><span class="text-xs font-semibold text-slate-600">Owner</span><select wire:model="selectedAssignee" class="mt-1 min-h-10 w-full rounded-xl border-slate-200 text-xs"><option value="">Unassigned</option>@foreach($assigneeOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
                                <label class="block"><span class="text-xs font-semibold text-slate-600">Next action</span><input type="datetime-local" wire:model="selectedFollowUpAt" class="mt-1 min-h-10 w-full rounded-xl border-slate-200 text-xs"></label>
                            </div>
                            <button type="button" wire:click="saveLead" class="mt-3 min-h-10 w-full rounded-xl bg-[#23483F] px-4 py-2 text-sm font-bold text-white hover:bg-[#173F35]">Save lead</button>
                        </section>

                        <section class="rounded-2xl border border-[#E8C8BE] bg-[#FFF4EF] p-4">
                            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#A55343]">Family context</p>
                            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                <div><dt class="text-xs text-slate-500">Care for</dt><dd class="mt-0.5 font-bold text-slate-900">{{ data_get($form, 'care_for', '—') }}</dd></div>
                                <div><dt class="text-xs text-slate-500">Relationship</dt><dd class="mt-0.5 font-bold text-slate-900">{{ data_get($form, 'relationship', '—') }}</dd></div>
                                <div><dt class="text-xs text-slate-500">Urgency</dt><dd class="mt-0.5 font-bold text-slate-900">{{ data_get($form, 'urgency', '—') }}</dd></div>
                                <div><dt class="text-xs text-slate-500">Schedule</dt><dd class="mt-0.5 font-bold text-slate-900">{{ data_get($form, 'schedule', '—') }}</dd></div>
                            </dl>
                            <div class="mt-3 flex flex-wrap gap-1.5">@foreach((array) data_get($form, 'care_needs', []) as $need)<span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-[#23483F]">{{ $need }}</span>@endforeach</div>
                            <p class="mt-3 text-sm leading-6 text-slate-700">{{ data_get($form, 'additional_details', 'No additional details.') }}</p>
                        </section>

                        <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center justify-between"><p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Attribution</p><span class="text-xs font-bold text-slate-600">{{ $selectedLead->source === 'meta_lead_ads' ? 'Meta' : 'Manual' }}</span></div>
                            <p class="mt-2 text-sm font-bold text-slate-900">{{ data_get($meta, 'campaign_name', $selectedLead->source_detail) }}</p>
                            @if($meta)
                                <p class="mt-1 text-xs leading-5 text-slate-500">{{ data_get($meta, 'ad_set_name') }}<br>{{ data_get($meta, 'ad_name') }}<br>Form: {{ data_get($meta, 'form_name') }}</p>
                            @endif
                        </section>

                        <section>
                            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Add internal note</p>
                            <textarea wire:model="note" rows="3" placeholder="Add context for the SDR or management team…" class="mt-2 w-full rounded-xl border-slate-200 text-sm"></textarea>
                            @error('note')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                            <button type="button" wire:click="addNote" class="mt-2 min-h-10 w-full rounded-xl border border-[#23483F] px-4 py-2 text-sm font-bold text-[#23483F] hover:bg-emerald-50">Add note</button>
                        </section>

                        <section>
                            <div class="flex items-center justify-between"><p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Timeline</p><span class="text-xs text-slate-400">Newest first</span></div>
                            <div class="mt-3 space-y-3">
                                @foreach($selectedLead->activities as $activity)
                                    <article class="rounded-xl border border-slate-200 p-3">
                                        <div class="flex items-start justify-between gap-3"><p class="text-sm font-bold text-slate-900">{{ $activity->summary }}</p><p class="shrink-0 text-[10px] text-slate-400">{{ $activity->occurred_at?->format('M j, g:i A') }}</p></div>
                                        @if($activity->body)<p class="mt-1 text-xs leading-5 text-slate-600">{{ $activity->body }}</p>@endif
                                        <p class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ $activity->actor?->name ?: 'System / Meta' }}</p>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    </div>
                </aside>
            @endif
        </section>
    </div>
</div>
