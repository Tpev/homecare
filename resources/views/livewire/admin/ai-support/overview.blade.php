<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Restricted administration</p>
            <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-950">AI Support</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Manage who can use AI Support, the knowledge base, and human conversations.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.ai-support.pilots.index') }}" wire:navigate class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-800">Pilot users</a>
            <a href="{{ route('admin.ai-support.knowledge.index') }}" wire:navigate class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-800">Knowledge base</a>
            <a href="{{ route('admin.ai-support.activity.index') }}" wire:navigate class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-800">Activity</a>
            <a href="{{ route('admin.ai-support.settings') }}" wire:navigate class="inline-flex min-h-11 items-center rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white">Availability</a>
        </div>
    </div>

    @if(! $runtimeAvailable)
        <x-alert color="blue">The deployment runtime guard is off. No customer-facing AI can become eligible, even when a grant is stored.</x-alert>
    @endif
    @if(! $providerEnabled)
        <x-alert color="blue">The provider deployment guard is off. No customer model call can run.</x-alert>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        <x-card>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Customer AI state</p>
            <p class="mt-2 text-2xl font-extrabold text-slate-950">
                @if($humanOnlyState['enabled'])
                    Emergency stop
                @elseif(! $runtimeAvailable || ! $providerEnabled || ! $masterState['enabled'] || ! $visibleState['enabled'])
                    Unavailable
                @elseif($generalReleaseState['enabled'])
                    Live for everyone
                @else
                    Pilot only
                @endif
            </p>
            <p class="mt-2 text-sm text-slate-600">{{ $generalReleaseState['enabled'] ? 'All supported Family and Caregiver users' : 'Only the active pilot users' }}</p>
        </x-card>
        <x-card>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Active exact-user grants</p>
            <p class="mt-2 text-3xl font-extrabold text-slate-950">{{ $activeGrants }}</p>
            <p class="mt-2 text-sm text-slate-600">{{ $scheduledGrants }} scheduled · {{ $expiringSoon }} expire within 7 days</p>
        </x-card>
        <x-card>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Knowledge base</p>
            <p class="mt-2 text-2xl font-extrabold text-slate-950">{{ $knowledgeWorkingCount }} working</p>
            <p class="mt-2 text-sm text-slate-600">{{ $knowledgeDraftCount }} Draft · {{ $knowledgePublishedCount }} published · {{ $knowledgePausedCount }} paused · {{ $knowledgeOverdueCount }} overdue</p>
        </x-card>
    </div>

    <x-card>
        <x-slot:header><h2 class="text-lg font-semibold">Safety posture</h2></x-slot:header>
        <ul class="grid gap-3 text-sm text-slate-700 md:grid-cols-2">
            <li class="rounded-xl border border-slate-200 p-4"><strong>Simple availability.</strong> Use two exact pilot users or one switch for everyone.</li>
            <li class="rounded-xl border border-slate-200 p-4"><strong>Two deployment guards.</strong> Runtime and provider access must both be configured before any customer model call.</li>
            <li class="rounded-xl border border-slate-200 p-4"><strong>Human support preserved.</strong> Existing chat, claim, reply, resolve, and close behavior remains primary.</li>
            <li class="rounded-xl border border-slate-200 p-4"><strong>Role appropriate.</strong> Caregivers receive Caregiver help; Family request creation remains Family-only.</li>
        </ul>
    </x-card>

    <x-card>
        <x-slot:header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div><h2 class="text-lg font-semibold">Family intent coverage</h2><p class="mt-1 text-sm text-slate-600">Executable coverage and compact outcomes. No chat text is stored here.</p></div>
                <label class="block text-sm font-semibold text-slate-700">Find an intent<input type="search" wire:model.live.debounce.250ms="intentSearch" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 sm:w-72" placeholder="ID, domain, task, or KB"></label>
            </div>
        </x-slot:header>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs font-bold uppercase text-slate-500">Catalog</p><p class="mt-1 text-2xl font-extrabold">{{ $intentCoverage['total'] }}</p></div>
            <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs font-bold uppercase text-slate-500">KB mapped</p><p class="mt-1 text-2xl font-extrabold">{{ $intentCoverage['kb_mapped'] }}</p></div>
            <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs font-bold uppercase text-slate-500">Pilot behavior</p><p class="mt-1 text-2xl font-extrabold">{{ $intentCoverage['pilot'] }}</p></div>
            <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs font-bold uppercase text-slate-500">Prepare</p><p class="mt-1 text-2xl font-extrabold">{{ $intentCoverage['prepare'] }}</p></div>
            <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs font-bold uppercase text-slate-500">Backlog</p><p class="mt-1 text-2xl font-extrabold">{{ $intentCoverage['backlog'] }}</p></div>
        </div>
        <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-3">Intent</th><th class="px-3 py-3">Current stages</th><th class="px-3 py-3">KB</th><th class="px-3 py-3">State</th></tr></thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($intentRecords as $record)
                        <tr>
                            <td class="max-w-md px-3 py-3 align-top"><p class="font-mono text-xs font-bold text-emerald-800">{{ $record['intent_id'] }}</p><p class="mt-1 font-semibold text-slate-900">{{ $record['intent'] }}</p><p class="mt-1 text-xs text-slate-500">{{ str($record['domain'])->headline() }}</p></td>
                            <td class="px-3 py-3 align-top"><div class="flex max-w-sm flex-wrap gap-1">@foreach((array) data_get($record, 'capability_stages.current', []) as $stage)<span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $stage }}</span>@endforeach</div></td>
                            <td class="max-w-sm px-3 py-3 align-top text-xs text-slate-600">{{ implode(', ', (array) $record['kb_stable_ids']) ?: 'Not mapped' }}</td>
                            <td class="px-3 py-3 align-top"><span class="rounded-full px-2 py-1 text-xs font-bold {{ $record['rollout_state'] === 'pilot' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">{{ str($record['rollout_state'])->headline() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-8 text-center text-slate-500">No matching intent.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card>
        <x-slot:header><h2 class="text-lg font-semibold">Recent Family intent outcomes</h2></x-slot:header>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach(['intent_unmatched' => 'Unmatched', 'intent_looped' => 'Repeated loops', 'intent_failed' => 'Failures', 'intent_transferred' => 'Transfers'] as $type => $label)
                <div class="rounded-xl border border-slate-200 p-3"><p class="text-xs font-bold uppercase text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-extrabold">{{ (int) ($intentOutcomeCounts[$type] ?? 0) }}</p></div>
            @endforeach
        </div>
        <div class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200">
            @forelse($recentIntentOutcomes as $event)
                <div class="flex flex-col gap-1 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                    <div><span class="font-semibold text-slate-900">{{ str($event->event_type)->replace('_', ' ')->headline() }}</span><span class="ml-2 font-mono text-xs text-slate-500">{{ data_get($event->safe_metadata, 'intent_id', 'unmatched') }}</span></div>
                    <div class="text-xs text-slate-500">{{ $event->result_code }} · {{ $event->occurred_at?->diffForHumans() }}</div>
                </div>
            @empty
                <p class="px-4 py-6 text-sm text-slate-500">No unmatched, looped, failed, recovered, or transferred intent outcomes in the last 30 days.</p>
            @endforelse
        </div>
    </x-card>
</div>
