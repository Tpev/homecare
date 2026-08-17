<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a href="{{ route('admin.ai-support.index') }}" wire:navigate class="text-sm font-semibold text-emerald-700 hover:underline">&larr; AI Support overview</a>
            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-950">Release readiness</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Evidence and incident visibility only. This page cannot enable AI, change a safety control, or create a pilot grant.</p>
            <p class="mt-1 text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Initial two-user pilot · {{ $snapshot['policy_version'] }}</p>
        </div>
        <x-badge :text="$snapshot['state']" :color="$snapshot['ready'] ? 'green' : 'red'" />
    </div>

    @if(session('status'))<x-alert color="green">{{ session('status') }}</x-alert>@endif
    @if(! $snapshot['ready'])
        <x-alert color="blue">Initial pilot remains blocked. {{ collect($snapshot['checks'])->where('satisfied', false)->count() }} required check(s) still need evidence or correction.</x-alert>
    @else
        <x-alert color="green">Initial-pilot evidence is ready for a separate explicit release decision. This state does not enable AI, create a grant, or satisfy expansion readiness.</x-alert>
    @endif

    <x-alert color="yellow">Expansion readiness: <strong>{{ $expansionSnapshot['state'] }}</strong>. {{ collect($expansionSnapshot['checks'])->where('satisfied', false)->count() }} expansion check(s) remain blocking; deferred evidence is never treated as Passed.</x-alert>

    @if($snapshot['release_decision'])
        <x-alert :color="$snapshot['release_decision']['effective'] ? 'green' : 'yellow'">
            Explicit initial-pilot release decision:
            <strong>{{ str($snapshot['release_decision']['status'])->upper() }} · {{ $snapshot['release_decision']['effective'] ? 'EFFECTIVE' : 'INEFFECTIVE' }}</strong>
            · commit {{ $snapshot['release_decision']['release_commit'] }}
            · expires {{ $snapshot['release_decision']['expires_at']->format('M j, Y g:i A') }}.
            This record does not itself enable a control or create a grant.
            @if(! $snapshot['release_decision']['effective']) Activation remains denied until the web process verifies this exact deployed commit. @endif
        </x-alert>
    @else
        <x-alert color="blue">No explicit initial-pilot release decision is recorded. Even a ready preflight cannot authorize activation by itself.</x-alert>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <x-card>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Release decision</p>
            <p class="mt-2 text-xl font-extrabold {{ $snapshot['ready'] ? 'text-emerald-800' : 'text-rose-800' }}">{{ $snapshot['state'] }}</p>
        </x-card>
        <x-card>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Passing checks</p>
            <p class="mt-2 text-3xl font-extrabold text-slate-950">{{ collect($snapshot['checks'])->where('passed', true)->count() }} / {{ count($snapshot['checks']) }}</p>
        </x-card>
        <x-card>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Deferred before expansion</p>
            <p class="mt-2 text-3xl font-extrabold text-amber-800">{{ $snapshot['deferred_count'] }}</p>
        </x-card>
        <x-card>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Open incidents</p>
            <p class="mt-2 text-3xl font-extrabold {{ $snapshot['open_incidents'] ? 'text-rose-800' : 'text-slate-950' }}">{{ $snapshot['open_incidents'] }}</p>
        </x-card>
        <x-card>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Open warnings</p>
            <p class="mt-2 text-3xl font-extrabold {{ $snapshot['open_warnings'] ? 'text-amber-800' : 'text-slate-950' }}">{{ $snapshot['open_warnings'] }}</p>
        </x-card>
    </div>

    <x-card>
        <x-slot:header><h2 class="text-lg font-semibold">Computed release checks</h2></x-slot:header>
        <div class="grid gap-3 md:grid-cols-2">
            @foreach($snapshot['checks'] as $check)
                <div class="rounded-xl border p-4 {{ $check['state'] === 'pass' ? 'border-emerald-200 bg-emerald-50/60' : ($check['state'] === 'deferred' ? 'border-amber-200 bg-amber-50/60' : 'border-rose-200 bg-rose-50/60') }}">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-semibold text-slate-950">{{ $check['label'] }}</p>
                        <x-badge
                            :text="$check['state'] === 'pass' ? 'PASS' : ($check['state'] === 'deferred' ? 'DEFERRED BEFORE EXPANSION' : 'BLOCKED')"
                            :color="$check['state'] === 'pass' ? 'green' : ($check['state'] === 'deferred' ? 'yellow' : 'red')"
                        />
                    </div>
                    <p class="mt-2 text-sm text-slate-700">{{ $check['detail'] }}</p>
                </div>
            @endforeach
        </div>
    </x-card>

    @if($incidents->isNotEmpty())
        <x-card>
            <x-slot:header><h2 class="text-lg font-semibold text-rose-900">Unresolved incidents and warnings</h2></x-slot:header>
            <label class="block text-sm font-semibold text-slate-800">Content-free resolution reason
                <input wire:model="resolutionReason" type="text" maxlength="500" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base" placeholder="What was verified and corrected?">
            </label>
            @error('resolutionReason')<p class="mt-1 text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
            <div class="mt-4 divide-y divide-slate-200">
                @foreach($incidents as $incident)
                    <div class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold text-slate-950">{{ str($incident->reason_code)->replace(['.', '_'], ' ')->headline() }}</p>
                                <x-badge :text="str($incident->severity)->upper()" :color="$incident->severity === 'critical' ? 'red' : 'yellow'" />
                            </div>
                            <p class="mt-1 text-sm text-slate-700">{{ $incident->summary }}</p>
                            <p class="mt-1 text-xs text-slate-500">Opened {{ $incident->opened_at->format('M j, Y g:i A') }}{{ $incident->control_key ? ' · '.$incident->control_key : '' }}</p>
                        </div>
                        <x-button type="button" color="red" wire:click="resolveIncident('{{ $incident->id }}')" class="min-h-11 justify-center">Mark resolved</x-button>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    <x-card>
        <x-slot:header><h2 class="text-lg font-semibold">Evidence register</h2></x-slot:header>
        <div class="grid gap-3 md:grid-cols-2">
            @foreach($snapshot['evidence'] as $key => $row)
                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-slate-950">{{ $row['label'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $row['deferable_for_initial_pilot'] ? 'Required before expansion; DEC-070 deferral permitted for the exact initial pilot' : ($row['required'] ? 'Required' : 'Tracked, not release-blocking') }}</p>
                        </div>
                        <x-badge :text="$row['status'] === 'deferred' ? 'DEFERRED BEFORE EXPANSION' : str($row['status'])->replace('_', ' ')->upper()" :color="$row['effective_pass'] ? 'green' : ($row['status'] === 'failed' ? 'red' : ($row['effective_deferred'] ? 'yellow' : 'slate'))" />
                    </div>
                    <p class="mt-3 text-sm text-slate-700">{{ $row['summary'] ?: $row['guidance'] }}</p>
                    @if($row['recorded_by'])
                        <p class="mt-2 text-xs text-slate-500">{{ $row['recorded_by'] }} · {{ $row['observed_at']?->format('M j, Y') }}</p>
                    @endif
                    @if($row['source_reference'])<p class="mt-1 break-all text-xs text-slate-500">Source: {{ $row['source_reference'] }}</p>@endif
                </div>
            @endforeach
        </div>
    </x-card>

    <x-card>
        <x-slot:header><h2 class="text-lg font-semibold">Record a new evidence version</h2></x-slot:header>
        <form wire:submit="recordEvidence" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="text-sm font-semibold text-slate-800">Evidence item
                    <select wire:model="evidenceKey" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base">
                        @foreach($definitions as $key => $definition)<option value="{{ $key }}">{{ $definition['label'] }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold text-slate-800">Result
                    <select wire:model="evidenceStatus" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base">
                        <option value="pending">Pending</option><option value="passed">Passed</option><option value="failed">Failed</option><option value="deferred">Deferred before expansion (DEC-070 only)</option>
                    </select>
                </label>
            </div>
            @error('evidenceStatus')<p class="text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
            <label class="block text-sm font-semibold text-slate-800">Content-free summary
                <textarea wire:model="evidenceSummary" maxlength="500" rows="3" class="mt-1 w-full rounded-xl border-slate-300 text-base" placeholder="Result, scope, and safe evidence identifiers only"></textarea>
            </label>
            @error('evidenceSummary')<p class="text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
            <label class="block text-sm font-semibold text-slate-800">Source reference
                <input wire:model="sourceReference" type="text" maxlength="500" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base" placeholder="Document, provider page, report ID, or commit">
            </label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-semibold text-slate-800">Observed on
                    <input wire:model="evidenceObservedAt" type="date" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base">
                </label>
                <label class="text-sm font-semibold text-slate-800">Expires on (optional)
                    <input wire:model="evidenceExpiresAt" type="date" class="mt-1 min-h-11 w-full rounded-xl border-slate-300 text-base">
                </label>
            </div>
            <label class="flex items-start gap-3 text-sm text-slate-800"><input wire:model="contentFreeConfirmed" type="checkbox" class="mt-1 rounded border-slate-300"> <span>I confirm this evidence contains no transcript, prompt, credential, payment data, medical record, or unnecessary personal information.</span></label>
            @error('contentFreeConfirmed')<p class="text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
            <x-button type="submit" class="min-h-11 justify-center">Record evidence version</x-button>
        </form>
    </x-card>
</div>
