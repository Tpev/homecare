<div class="min-h-screen bg-[#F7F3EC] px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-[1500px] space-y-6">
        <header class="overflow-hidden rounded-[2rem] border border-[#1A3D35] bg-[#23483F] shadow-xl">
            <div class="grid gap-6 px-6 py-6 text-white lg:grid-cols-[1fr_auto] lg:items-end lg:px-8">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-white/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-[#F3D4C9]">Management</span>
                        <span class="text-xs font-semibold text-white/60">Family acquisition intelligence</span>
                    </div>
                    <h1 class="mt-3 text-4xl font-bold tracking-tight text-white">From ad spend to care started.</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-white/70">A cohort view of response speed, call performance, conversion, CPL, and CAC—not just a snapshot of today’s pipeline.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="toggleAlertSettings" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/20 px-4 py-2 text-sm font-bold text-white hover:bg-white/10">
                        {{ $alertsEnabled ? 'Alerts on' : 'Alerts off' }} · Settings
                    </button>
                    <a href="{{ route('admin.family-acquisition.leads') }}" wire:navigate class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/20 px-4 py-2 text-sm font-bold text-white hover:bg-white/10">Open family CRM</a>
                    <a href="{{ route('sdr.family-calling') }}" wire:navigate class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#C96B55] px-4 py-2 text-sm font-bold text-white hover:bg-[#B85C49]">Review calling console</a>
                </div>
            </div>
            <div class="grid gap-3 border-t border-white/10 bg-black/10 px-6 py-4 sm:grid-cols-[190px_minmax(220px,360px)_1fr] lg:px-8">
                <select wire:model.live="range" aria-label="Cohort date range" class="min-h-11 rounded-xl border-white/10 bg-white/10 text-sm font-bold text-white focus:border-white/30 focus:ring-white/20">
                    <option class="text-slate-900" value="all">All time</option>
                    <option class="text-slate-900" value="30">Last 30 days</option>
                    <option class="text-slate-900" value="60">Last 60 days</option>
                    <option class="text-slate-900" value="90">Last 90 days</option>
                </select>
                <select wire:model.live="campaign" aria-label="Campaign filter" class="min-h-11 rounded-xl border-white/10 bg-white/10 text-sm font-bold text-white focus:border-white/30 focus:ring-white/20">
                    <option class="text-slate-900" value="all">All acquisition sources</option>
                    @foreach($campaignOptions as $id => $name)<option class="text-slate-900" value="{{ $id }}">{{ $name }}</option>@endforeach
                    <option class="text-slate-900" value="manual">Manual CRM / community</option>
                </select>
                <p class="self-center text-xs leading-5 text-white/60 sm:text-right">
                    @if($start)
                        Lead cohort: {{ $start->format('M j') }}–{{ $end->format('M j, Y') }}.
                    @else
                        All-time lead cohort through {{ $end->format('M j, Y') }}.
                    @endif
                    Later outcomes remain attributed to the original lead.
                </p>
            </div>
        </header>

        <section class="rounded-[1.75rem] border border-[#D9CEC0] bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-700">Live CRM pipeline</p>
                    <h2 class="mt-1 text-2xl font-bold text-slate-950">Current family stages</h2>
                    <p class="mt-1 text-sm text-slate-500">Updates as soon as a stage is saved, regardless of when the lead first entered the funnel.</p>
                </div>
                <p class="text-sm font-bold text-slate-700">{{ number_format($livePipeline['total']) }} total family leads</p>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                @foreach([
                    ['New', $livePipeline['new'], 'bg-sky-50 text-sky-900'],
                    ['Calling / follow-up', $livePipeline['calling'], 'bg-amber-50 text-amber-900'],
                    ['Qualified', $livePipeline['qualified'], 'bg-emerald-50 text-emerald-900'],
                    ['Assessment scheduled', $livePipeline['assessment'], 'bg-violet-50 text-violet-900'],
                    ['Care started', $livePipeline['care_started'], 'bg-[#FFF4EF] text-[#8C493B]'],
                ] as [$label, $value, $style])
                    <article class="rounded-2xl px-4 py-4 {{ $style }}">
                        <p class="text-xs font-bold uppercase tracking-[0.12em] opacity-70">{{ $label }}</p>
                        <p class="mt-2 text-3xl font-bold">{{ number_format($value) }}</p>
                    </article>
                @endforeach
            </div>

            @if($recentStageChanges->isNotEmpty())
                <div class="mt-5 border-t border-slate-100 pt-5">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-bold text-slate-950">Recent stage changes</h3>
                        <a href="{{ route('admin.family-acquisition.leads', ['status' => 'all']) }}" wire:navigate class="text-xs font-bold text-emerald-700 hover:text-emerald-900">Open family CRM</a>
                    </div>
                    <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach($recentStageChanges as $change)
                            @if($change->lead)
                                <a href="{{ route('admin.family-acquisition.leads', ['q' => $change->lead->name, 'status' => 'all']) }}" wire:navigate class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 transition hover:border-emerald-300 hover:bg-emerald-50/50">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-slate-950">{{ $change->lead->name }}</p>
                                            <p class="mt-1 text-xs font-semibold text-emerald-700">{{ $change->lead->stageLabel() }}</p>
                                        </div>
                                        <span class="shrink-0 text-xs text-slate-400">{{ $change->occurred_at?->diffForHumans() }}</span>
                                    </div>
                                    <p class="mt-2 text-xs text-slate-500">Updated by {{ $change->actor?->name ?: 'System' }}</p>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        @if($showAlertSettings)
            <section class="rounded-[1.75rem] border border-[#D9CEC0] bg-white p-5 shadow-lg sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#A55343]">Immediate response</p>
                        <h2 class="mt-1 text-2xl font-bold text-slate-950">New-lead email alerts and escalation</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">The SDR list is emailed as soon as any new family lead is created—Meta API or manual CRM. If no first call is recorded within the SLA, the escalation list is emailed automatically.</p>
                    </div>
                    <button type="button" wire:click="toggleAlertSettings" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-200 text-xl text-slate-500 hover:bg-slate-50" aria-label="Close alert settings">×</button>
                </div>

                <form wire:submit="saveAlertSettings" class="mt-6 grid gap-5 xl:grid-cols-[1fr_1fr_220px]">
                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-600">New lead — SDR emails</span>
                        <textarea wire:model="newLeadAlertEmails" rows="4" placeholder="jordan@lolo.care&#10;backup-sdr@lolo.care" class="mt-2 w-full rounded-2xl border-slate-200 px-4 py-3 text-sm focus:border-emerald-600 focus:ring-emerald-100"></textarea>
                        <span class="mt-2 block text-xs leading-5 text-slate-500">One per line, or separated by commas. Every recipient receives the lead’s phone, urgency, care needs, source, and preferred call time.</span>
                        @error('newLeadAlertEmails')<span class="mt-1 block text-xs font-semibold text-rose-600">{{ $message }}</span>@enderror
                    </label>

                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-600">SLA missed — escalation emails</span>
                        <textarea wire:model="escalationAlertEmails" rows="4" placeholder="manager@lolo.care" class="mt-2 w-full rounded-2xl border-slate-200 px-4 py-3 text-sm focus:border-emerald-600 focus:ring-emerald-100"></textarea>
                        <span class="mt-2 block text-xs leading-5 text-slate-500">Usually management or a backup SDR. Leave blank to reuse the new-lead SDR list.</span>
                        @error('escalationAlertEmails')<span class="mt-1 block text-xs font-semibold text-rose-600">{{ $message }}</span>@enderror
                    </label>

                    <div class="space-y-4 rounded-2xl border border-[#E8C8BE] bg-[#FFF4EF] p-4">
                        <label class="flex items-start gap-3">
                            <input type="checkbox" wire:model="alertsEnabled" class="mt-0.5 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600">
                            <span><span class="block text-sm font-bold text-slate-900">Email alerts enabled</span><span class="mt-1 block text-xs leading-5 text-slate-500">Pause without deleting the recipient lists.</span></span>
                        </label>

                        <label class="block">
                            <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-600">First-call SLA</span>
                            <div class="mt-2 flex items-center gap-2"><input type="number" min="5" max="240" wire:model="firstCallSlaMinutes" class="min-h-11 w-24 rounded-xl border-slate-200 text-sm focus:border-emerald-600 focus:ring-emerald-100"><span class="text-sm font-semibold text-slate-600">minutes</span></div>
                            @error('firstCallSlaMinutes')<span class="mt-1 block text-xs font-semibold text-rose-600">{{ $message }}</span>@enderror
                        </label>

                        <button type="submit" class="min-h-11 w-full rounded-xl bg-[#23483F] px-4 py-2 text-sm font-bold text-white hover:bg-[#173F35]">Save alert settings</button>
                    </div>
                </form>
            </section>
        @endif

        @if($metrics['sla_breaches'] > 0)
            <section class="flex flex-col gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="text-sm font-bold text-rose-900">{{ $metrics['sla_breaches'] }} active family lead{{ $metrics['sla_breaches'] === 1 ? '' : 's' }} missed the {{ $firstCallSlaMinutes }}-minute first-call SLA.</p><p class="mt-1 text-xs text-rose-700">The configured escalation recipients are notified once per lead.</p></div>
                <a href="{{ route('admin.family-acquisition.leads', ['status' => 'active']) }}" wire:navigate class="inline-flex min-h-10 items-center justify-center rounded-xl bg-rose-700 px-4 py-2 text-sm font-bold text-white hover:bg-rose-800">Review waiting leads</a>
            </section>
        @endif

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            @foreach([
                ['Leads', number_format($metrics['leads']), 'Received in cohort', 'text-slate-950'],
                ['Meta spend', '$'.number_format($metrics['spend'], 0), 'Synced campaign spend', 'text-slate-950'],
                ['CPL', $metrics['cpl'] !== null ? '$'.number_format($metrics['cpl'], 0) : '—', 'Spend ÷ valid leads', 'text-[#A55343]'],
                ['Contact rate', number_format($metrics['contact_rate'], 1).'%', 'Reached a person', 'text-emerald-800'],
                ['Care conversion', number_format($metrics['conversion_rate'], 1).'%', 'Lead → care started', 'text-emerald-800'],
                ['Paid CAC', $metrics['cac'] !== null ? '$'.number_format($metrics['cac'], 0) : '—', 'Spend ÷ customers', 'text-[#A55343]'],
            ] as [$label, $value, $help, $color])
                <article class="rounded-[1.35rem] border border-[#D9CEC0] bg-white p-4 shadow-sm">
                    <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-bold {{ $color }}">{{ $value }}</p>
                    <p class="mt-2 text-xs text-slate-400">{{ $help }}</p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.15fr)_minmax(420px,.85fr)]">
            <article class="rounded-[1.75rem] border border-[#D9CEC0] bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-700">Conversion funnel</p>
                        <h2 class="mt-1 text-2xl font-bold text-slate-950">Where families progress—or drop off</h2>
                    </div>
                    <p class="text-xs text-slate-500">Based on lead cohort, not event date</p>
                </div>
                <div class="mt-6 space-y-4">
                    @php($funnelMax = max(1, $funnel[0]['value']))
                    @foreach($funnel as $index => $stage)
                        <div class="grid grid-cols-[140px_minmax(0,1fr)_70px] items-center gap-3 sm:grid-cols-[190px_minmax(0,1fr)_90px]">
                            <div>
                                <p class="text-sm font-bold text-slate-800">{{ $stage['label'] }}</p>
                                @if($index > 0)<p class="mt-0.5 text-[10px] text-slate-400">{{ $funnel[$index - 1]['value'] > 0 ? number_format(($stage['value'] / $funnel[$index - 1]['value']) * 100, 0).'%' : '—' }} from previous</p>@endif
                            </div>
                            <div class="h-9 overflow-hidden rounded-xl bg-slate-100">
                                <div class="flex h-full min-w-8 items-center rounded-xl px-3 text-xs font-bold text-white {{ $index === count($funnel) - 1 ? 'bg-[#C96B55]' : 'bg-[#23483F]' }}" style="width: {{ max(5, ($stage['value'] / $funnelMax) * 100) }}%"></div>
                            </div>
                            <div class="text-right"><span class="text-xl font-bold text-slate-950">{{ $stage['value'] }}</span><span class="ml-1 text-xs text-slate-400">{{ number_format(($stage['value'] / $funnelMax) * 100, 0) }}%</span></div>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="rounded-[1.75rem] border border-[#D9CEC0] bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#A55343]">Speed to lead</p>
                        <h2 class="mt-1 text-2xl font-bold text-slate-950">Time to first phone call</h2>
                    </div>
                    <div class="rounded-2xl bg-emerald-50 px-4 py-3 text-right">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Within 15m</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-900">{{ number_format($speed['within_15'], 0) }}%</p>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-3 gap-3">
                    @foreach([['Median', $speed['median']], ['P75', $speed['p75']], ['P90', $speed['p90']]] as [$label, $minutes])
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 text-center">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                            <p class="mt-1 text-xl font-bold text-slate-950">{{ $minutes !== null ? $minutes.'m' : '—' }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 space-y-3">
                    @php($bucketMax = max(1, collect($speed['buckets'])->max('count')))
                    @foreach($speed['buckets'] as $bucket)
                        <div class="grid grid-cols-[90px_1fr_28px] items-center gap-3">
                            <p class="text-xs font-semibold text-slate-600">{{ $bucket['label'] }}</p>
                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-[#C96B55]" style="width: {{ ($bucket['count'] / $bucketMax) * 100 }}%"></div></div>
                            <p class="text-right text-xs font-bold text-slate-800">{{ $bucket['count'] }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-900"><strong>{{ $speed['uncalled'] }} lead{{ $speed['uncalled'] === 1 ? '' : 's' }}</strong> in this cohort have no first-call timestamp yet. This lowers the 15-minute SLA rate.</div>
            </article>
        </section>

        <section class="rounded-[1.75rem] border border-[#D9CEC0] bg-white shadow-sm">
            <div class="flex flex-col gap-2 border-b border-slate-200 p-5 sm:flex-row sm:items-end sm:justify-between sm:p-6">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-700">Acquisition efficiency</p>
                    <h2 class="mt-1 text-2xl font-bold text-slate-950">Campaign performance</h2>
                </div>
                <p class="text-xs text-slate-500">Spend is campaign-level fake Meta data for this review version</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left">
                    <thead class="bg-[#FFFBF4] text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500"><tr><th class="px-6 py-3">Campaign</th><th class="px-4 py-3">Spend</th><th class="px-4 py-3">Leads</th><th class="px-4 py-3">CPL</th><th class="px-4 py-3">Contact</th><th class="px-4 py-3">Customers</th><th class="px-4 py-3">CAC</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($campaignRows as $row)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4"><p class="text-sm font-bold text-slate-950">{{ $row['name'] }}</p><p class="mt-0.5 text-xs text-slate-400">{{ $row['platform'] }}</p></td>
                                <td class="px-4 py-4 text-sm font-semibold text-slate-700">${{ number_format($row['spend'], 0) }}</td>
                                <td class="px-4 py-4 text-sm font-bold text-slate-950">{{ $row['leads'] }}</td>
                                <td class="px-4 py-4 text-sm font-bold text-[#A55343]">{{ $row['cpl'] !== null ? '$'.number_format($row['cpl'], 0) : '—' }}</td>
                                <td class="px-4 py-4 text-sm font-semibold text-slate-700">{{ number_format($row['contact_rate'], 0) }}%</td>
                                <td class="px-4 py-4 text-sm font-bold text-emerald-800">{{ $row['customers'] }}</td>
                                <td class="px-4 py-4 text-sm font-bold text-[#A55343]">{{ $row['cac'] !== null ? '$'.number_format($row['cac'], 0) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-12 text-center text-sm text-slate-500">No campaign data in this cohort.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-3">
            <article class="rounded-[1.75rem] border border-[#D9CEC0] bg-white p-5 shadow-sm sm:p-6 xl:col-span-2">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div><p class="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-700">Cadence intelligence</p><h2 class="mt-1 text-2xl font-bold text-slate-950">Does each attempt earn its place?</h2></div>
                    <p class="text-xs text-slate-500">Connections created on that attempt</p>
                </div>
                <div class="mt-6 grid grid-cols-7 gap-2 sm:gap-3">
                    @php($attemptMax = max(1, collect($attemptPerformance)->max('calls')))
                    @foreach($attemptPerformance as $attempt)
                        <div class="flex min-w-0 flex-col items-center">
                            <div class="flex h-44 w-full items-end justify-center rounded-xl bg-slate-50 px-1 pt-3">
                                <div class="relative w-full max-w-12 rounded-t-lg bg-[#23483F]" style="height: {{ max(8, ($attempt['calls'] / $attemptMax) * 100) }}%">
                                    @if($attempt['connected'] > 0)<span class="absolute inset-x-1 bottom-1 rounded-md bg-[#C96B55] py-1 text-center text-[9px] font-bold text-white">{{ $attempt['connected'] }} reached</span>@endif
                                </div>
                            </div>
                            <p class="mt-2 text-xs font-bold text-slate-800">#{{ $attempt['attempt'] }}</p>
                            <p class="mt-0.5 text-[10px] text-slate-400">{{ $attempt['calls'] }} calls</p>
                            <p class="mt-0.5 text-[10px] font-bold text-emerald-700">{{ number_format($attempt['rate'], 0) }}%</p>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="rounded-[1.75rem] border border-[#D9CEC0] bg-white p-5 shadow-sm sm:p-6">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#A55343]">Call outcomes</p>
                <h2 class="mt-1 text-2xl font-bold text-slate-950">What is happening</h2>
                <div class="mt-5 space-y-3">
                    @php($outcomeMax = max(1, collect($outcomes)->max('count')))
                    @forelse($outcomes as $outcome)
                        <div>
                            <div class="flex items-center justify-between gap-3"><p class="truncate text-xs font-semibold text-slate-700">{{ $outcome['label'] }}</p><p class="text-xs font-bold text-slate-950">{{ $outcome['count'] }}</p></div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-[#C96B55]" style="width: {{ ($outcome['count'] / $outcomeMax) * 100 }}%"></div></div>
                        </div>
                    @empty
                        <p class="rounded-xl bg-slate-50 p-5 text-sm text-slate-500">No call outcomes yet.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <article class="rounded-[1.75rem] border border-[#D9CEC0] bg-white p-5 shadow-sm sm:p-6">
                <div><p class="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-700">SDR operations</p><h2 class="mt-1 text-2xl font-bold text-slate-950">Calling performance</h2></div>
                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left"><thead class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500"><tr><th class="py-3 pr-4">SDR</th><th class="px-4 py-3">Calls</th><th class="px-4 py-3">Connected</th><th class="px-4 py-3">Connect rate</th><th class="px-4 py-3">Qualified / booked</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($sdrRows as $row)<tr><td class="py-4 pr-4 text-sm font-bold text-slate-950">{{ $row['name'] }}</td><td class="px-4 py-4 text-sm text-slate-700">{{ $row['calls'] }}</td><td class="px-4 py-4 text-sm text-slate-700">{{ $row['connected'] }}</td><td class="px-4 py-4 text-sm font-bold text-emerald-800">{{ number_format($row['connect_rate'], 0) }}%</td><td class="px-4 py-4 text-sm font-bold text-slate-950">{{ $row['qualified'] }}</td></tr>@endforeach</tbody></table>
                </div>
            </article>

            <aside class="rounded-[1.75rem] border border-[#E8C8BE] bg-[#FFF4EF] p-5 shadow-sm sm:p-6">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#A55343]">Metric definitions</p>
                <h2 class="mt-1 text-xl font-bold text-slate-950">Numbers everyone can trust</h2>
                <dl class="mt-5 space-y-4 text-sm leading-6 text-slate-700">
                    <div><dt class="font-bold text-slate-950">CPL</dt><dd>Meta spend divided by valid leads received in the cohort.</dd></div>
                    <div><dt class="font-bold text-slate-950">Paid CAC</dt><dd>Meta spend divided by leads that reached “care started.”</dd></div>
                    <div><dt class="font-bold text-slate-950">First call</dt><dd>Original form submission to the SDR pressing “Start phone call.”</dd></div>
                    <div><dt class="font-bold text-slate-950">Contact</dt><dd>A real conversation, including a requested callback—not a voicemail.</dd></div>
                </dl>
            </aside>
        </section>
    </div>
</div>
