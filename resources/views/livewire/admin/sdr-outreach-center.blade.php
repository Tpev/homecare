<div class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">SDR Outreach</p>
                <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-950">Provider call management</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">
                    Import practice lists, feed the SDR call queue, and track call volume and outcomes by person.
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <a
                    href="{{ route('sdr.calling') }}"
                    wire:navigate
                    class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800"
                >
                    Open calling console
                </a>
                <a
                    href="{{ route('admin.crm.index', ['pipeline' => \App\Models\Lead::TYPE_REFERRAL, 'source' => \App\Support\SdrOutreach::SOURCE]) }}"
                    wire:navigate
                    class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                >
                    View in referral CRM
                </a>
            </div>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Imported</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $poolStats['total'] }}</p>
            </div>
            <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700">Unclaimed</p>
                <p class="mt-2 text-3xl font-bold text-amber-800">{{ $poolStats['unassigned'] }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Claimed</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $poolStats['claimed'] }}</p>
            </div>
            <div class="rounded-2xl border border-rose-100 bg-rose-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-rose-600">Follow-up due</p>
                <p class="mt-2 text-3xl font-bold text-rose-700">{{ $poolStats['due'] }}</p>
            </div>
            <div class="rounded-2xl border border-sky-100 bg-sky-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-700">Calls in window</p>
                <p class="mt-2 text-3xl font-bold text-sky-800">{{ $poolStats['calls'] }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">Resources requested</p>
                <p class="mt-2 text-3xl font-bold text-emerald-800">{{ $poolStats['resource_requested'] }}</p>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
            <form wire:submit.prevent="importLeads" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-950">Paste a call list</h2>
                        <p class="text-sm text-slate-500">Copy rows from a spreadsheet. Headers are optional.</p>
                    </div>
                    <button type="submit" class="mt-3 min-h-11 rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 sm:mt-0">
                        Import to queue
                    </button>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-4">
                    <label class="block md:col-span-2">
                        <span class="text-sm font-medium text-slate-700">Campaign tags</span>
                        <input
                            type="text"
                            wire:model.blur="tags"
                            placeholder="raleigh, pcp, july list"
                            class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                        >
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Stage</span>
                        <select wire:model.live="defaultStatus" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            @foreach ($stageOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700">Priority</span>
                        <select wire:model.live="defaultPriority" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            @foreach ($priorityOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block md:col-span-2">
                        <span class="text-sm font-medium text-slate-700">Owner</span>
                        <select wire:model.live="defaultOwnerId" class="mt-1 min-h-11 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <option value="">Leave unclaimed for SDR queue</option>
                            @foreach ($ownerOptions as $ownerId => $ownerName)
                                <option value="{{ $ownerId }}">{{ $ownerName }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs leading-5 text-slate-600 md:col-span-2">
                        Accepted columns: practice or company, contact name, role, phone, email, location, zip, notes.
                        Without headers, use this order: practice, contact, role, phone, email, location, notes.
                    </div>
                </div>

                <label class="mt-4 block">
                    <span class="text-sm font-medium text-slate-700">Spreadsheet rows</span>
                    <textarea
                        wire:model.blur="pasteRows"
                        rows="12"
                        spellcheck="false"
                        placeholder="Practice&#9;Contact&#9;Role&#9;Phone&#9;Email&#9;Location&#10;Triangle Primary Care&#9;Steve Miller&#9;Office manager&#9;+19195551212&#9;steve@example.com&#9;Raleigh, NC"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 font-mono text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                    ></textarea>
                    @error('pasteRows') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>

                @if ($importResult)
                    <div class="mt-4 rounded-xl border border-emerald-100 bg-emerald-50 p-4 text-sm text-emerald-950">
                        <p class="font-bold">Import complete</p>
                        <p class="mt-1">
                            {{ $importResult['created'] }} created,
                            {{ $importResult['updated'] }} updated,
                            {{ $importResult['skipped'] }} skipped.
                        </p>
                        @if (! empty($importResult['examples']))
                            <p class="mt-2 text-xs">Examples: {{ implode(', ', $importResult['examples']) }}</p>
                        @endif
                    </div>
                @endif
            </form>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-slate-950">Daily calling scoreboard</h2>
                        <p class="text-sm text-slate-500">Volume and outcomes by SDR.</p>
                    </div>
                    <select wire:model.live="metricsWindow" class="min-h-11 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <option value="1">Today</option>
                        <option value="7">7 days</option>
                        <option value="14">14 days</option>
                        <option value="30">30 days</option>
                    </select>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.12em] text-slate-500">
                            <tr>
                                <th class="py-3 text-left">Day</th>
                                <th class="py-3 text-left">SDR</th>
                                <th class="py-3 text-right">Calls</th>
                                <th class="py-3 text-right">Resource</th>
                                <th class="py-3 text-right">Meeting</th>
                                <th class="py-3 text-right">No answer</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($dailyStats as $row)
                                <tr>
                                    <td class="py-3 text-slate-700">{{ $row['date'] }}</td>
                                    <td class="py-3 font-semibold text-slate-950">{{ $row['sdr'] }}</td>
                                    <td class="py-3 text-right font-semibold text-slate-950">{{ $row['total'] }}</td>
                                    <td class="py-3 text-right text-emerald-700">{{ $row['resource_requested'] }}</td>
                                    <td class="py-3 text-right text-sky-700">{{ $row['meeting_requested'] }}</td>
                                    <td class="py-3 text-right text-slate-600">{{ $row['no_answer'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-10 text-center text-sm text-slate-500">No SDR calls logged in this window.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-950">Recent SDR call outcomes</h2>
                    <p class="text-sm text-slate-500">These notes are also visible on each referral lead timeline.</p>
                </div>
            </div>

            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                @forelse ($recentCalls as $activity)
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-950">{{ $activity->lead?->company ?: $activity->lead?->name ?: 'Unknown source' }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $activity->actor?->name ?: 'Unknown SDR' }} · {{ $activity->occurred_at?->format('M j, g:i A') }}</p>
                            </div>
                            <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                {{ data_get($activity->metadata, 'sdr_outcome_label', 'Call') }}
                            </span>
                        </div>
                        @if ($activity->body)
                            <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $activity->body }}</p>
                        @endif
                    </article>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500 lg:col-span-2">
                        Calls logged from the SDR console will appear here.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
