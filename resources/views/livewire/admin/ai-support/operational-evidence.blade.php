<x-card>
    <x-slot:header><h2 class="text-lg font-semibold">Operational health</h2></x-slot:header>
    @php
        $metrics = $health['metrics'];
        $lastSuccess = data_get($health, 'last_run.last_success_at');
        $monitorFresh = $lastSuccess && \Carbon\CarbonImmutable::parse($lastSuccess)->greaterThan(now()->subMinutes(10));
    @endphp
    <p class="text-sm {{ $monitorFresh ? 'text-emerald-800' : 'font-semibold text-amber-900' }}">
        Scheduled monitor: {{ $monitorFresh ? 'recent successful run' : 'no successful run observed in the last 10 minutes' }}.
        @if($lastSuccess) Last success {{ \Carbon\CarbonImmutable::parse($lastSuccess)->diffForHumans() }}.@endif
        @if(data_get($health, 'last_run.status') === 'failed') The last attempt failed; inspect the scheduler logs.@endif
    </p>
    <p class="mt-1 text-sm text-slate-600">This page reads current measurements; it does not run the monitor or change availability. If the heartbeat is missing, check the scheduler and shared cache.</p>
    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 p-3"><dt class="font-semibold">Successful provider calls · last hour</dt><dd class="mt-2">{{ $metrics['conversation_sample'] }} calls · P95 {{ $metrics['conversation_sample'] ? number_format($metrics['conversation_p95_ms']).' ms' : 'unavailable' }}</dd><dd class="mt-1 text-xs text-slate-600">{{ $metrics['conversation_sample_sufficient'] ? 'Minimum warning sample reached.' : 'Below the '.$metrics['minimum_sample'].'-call warning sample; no latency pass implied.' }} Excludes failed calls and end-to-end delivery.</dd></div>
        <div class="rounded-xl border border-slate-200 p-3"><dt class="font-semibold">Failed model turns · last hour</dt><dd class="mt-2">{{ $metrics['failed_model_turns'] }}</dd></div>
        <div class="rounded-xl border border-slate-200 p-3"><dt class="font-semibold">Operations and handoff delivery</dt><dd class="mt-2">{{ $metrics['failed_notifications'] }} failed in the last hour</dd><dd class="mt-1">{{ $metrics['queued_notifications_over_ten_minutes'] }} queued for at least 10 minutes</dd></div>
        <div class="rounded-xl border border-slate-200 p-3"><dt class="font-semibold">Estimated recorded model cost · today</dt><dd class="mt-2">${{ number_format($metrics['daily_cost_microunits'] / 1_000_000, 4) }}</dd><dd class="mt-1 text-xs text-slate-600">{{ $metrics['daily_cost_stopped'] ? 'Daily ceiling reached; review Availability.' : 'Daily ceiling not reached.' }} Provider billing may include attempts without recorded usage.</dd></div>
    </dl>
    <div class="mt-4 rounded-xl border border-slate-200 p-3 text-sm">
        <p class="font-semibold">Retention execution</p>
        <p class="mt-1">Automatic execution is {{ config('ai_support.retention_execution_enabled', false) ? 'enabled' : 'disabled' }}.
            @if($retentionSuccess = data_get($retentionHealth, 'last_success_at')) Last successful execution {{ \Carbon\CarbonImmutable::parse($retentionSuccess)->diffForHumans() }} ({{ $retentionSuccess }}).@else No successful execution has been observed.@endif
            @if(data_get($retentionHealth, 'status') === 'failed') The last attempt failed; inspect scheduler logs.@endif
        </p>
        <p class="mt-1 text-xs text-slate-600">A dry run is not an execution. Open incidents, unexpired active release decisions, and held records remain protected. A successful run with no due records still updates this heartbeat.</p>
    </div>
    @if($openIncidents->isNotEmpty())
        <h3 class="mt-6 font-semibold text-slate-950">Open incidents</h3>
        <div class="mt-3 space-y-3">
            @foreach($openIncidents as $incident)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4" wire:key="incident-{{ $incident->id }}">
                    <p class="font-semibold text-amber-950">{{ str($incident->severity)->headline() }} · {{ $incident->summary }}</p>
                    <p class="mt-1 text-sm text-amber-950">{{ data_get($incident->safe_metadata, 'containment.state') === 'control_stopped' ? 'A stopped control was confirmed at opening: '.$incident->control_key.' (version '.data_get($incident->safe_metadata, 'containment.control_version_id').').' : 'No stopped control was confirmed in this incident record.' }} Opened {{ $incident->opened_at?->diffForHumans() }}.</p>
                    <p class="mt-1 text-xs text-amber-900">This is evidence at opening; check <a href="{{ route('admin.ai-support.settings') }}" wire:navigate class="font-semibold underline">current Availability</a> before taking action.</p>
                    @if(auth()->user()?->canManageAiSupportControls())
                        <button type="button" wire:click="resolveIncident('{{ $incident->id }}')" class="mt-3 min-h-11 rounded-xl border border-amber-400 bg-white px-4 text-sm font-semibold text-amber-950">Resolve with reason below</button>
                    @endif
                </div>
            @endforeach
        </div>
        @if(auth()->user()?->canManageAiSupportControls())
            <label class="mt-4 block text-sm font-semibold text-slate-800">Resolution reason<input wire:model="resolutionReason" type="text" maxlength="500" class="mt-1 min-h-11 w-full rounded-xl border-slate-300" placeholder="What was checked and corrected? No customer details."></label>
            @error('resolutionReason')<p class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-slate-600">Resolving an incident does not restore any control.</p>
        @endif
    @endif
</x-card>

<x-card>
    <x-slot:header><h2 class="text-lg font-semibold">Current configuration and verification evidence</h2></x-slot:header>
    <p class="text-sm text-slate-600">Historical scores describe their tested version. They do not establish current model quality or enable access.</p>
    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
        <div><dt class="font-semibold">Configured model</dt><dd class="mt-1 break-all font-mono text-xs">{{ $operationalEvidence['current']['model'] }} · {{ $operationalEvidence['current']['model_configuration_version'] }}</dd></div>
        <div><dt class="font-semibold">Current prompt</dt><dd class="mt-1 break-all font-mono text-xs">{{ $operationalEvidence['current']['prompt_version'] }}</dd></div>
        <div><dt class="font-semibold">Release commit</dt><dd class="mt-1 break-all font-mono text-xs">{{ $operationalEvidence['current']['release_commit'] ?: 'Not recorded' }}</dd></div>
        <div><dt class="font-semibold">Current support source fingerprint</dt><dd class="mt-1 break-all font-mono text-xs">{{ $operationalEvidence['current']['source_hash'] }}</dd></div>
        <div><dt class="font-semibold">Published knowledge fingerprint</dt><dd class="mt-1 break-all font-mono text-xs">{{ $operationalEvidence['current']['knowledge_hash'] }}</dd><dd class="mt-1 text-xs">{{ count($operationalEvidence['current']['knowledge_versions']) }} retrievable versions. Includes version IDs and content hashes; timestamps are excluded.</dd></div>
    </dl>
    @if($validation = $operationalEvidence['deterministic_validation'])
        <div class="mt-4 rounded-xl border border-slate-200 p-4 text-sm">
            <h3 class="font-semibold">Deterministic regression · {{ $validation['result'] }}</h3>
            <p class="mt-1">{{ $validation['scope'] }}. {{ $validation['tests'] }} tests · {{ $validation['assertions'] }} assertions · {{ $validation['age_days'] }} days old ({{ $validation['observed_at'] }}).</p>
            <p class="mt-1">This checks application behavior; it is not a new model-quality evaluation.</p>
            <p class="mt-2 break-all text-xs">Tested model/configuration: {{ $validation['model'] }} · prompt {{ $validation['prompt_version'] }}<br>Source {{ $validation['source_hash'] }}<br>Published knowledge {{ $validation['knowledge_hash'] }}<br>Evidence SHA-256 {{ $validation['evidence_hash'] }}</p>
        </div>
    @else
        <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-950">No current deterministic regression report is attached to this installation. No current pass is implied.</p>
    @endif
    <div class="mt-4 space-y-3">
        @forelse($operationalEvidence['historical_records'] as $record)
            <div class="rounded-xl border border-slate-200 p-4 text-sm">
                <h3 class="font-semibold">{{ str($record['kind'])->headline() }} · recorded {{ $record['result'] }}</h3>
                <p class="mt-1">{{ $record['scope'] }}. {{ $record['age_days'] ?? 'Unknown' }} days old · {{ $record['observed_at'] ?? 'Date not recorded' }}.</p>
                <p class="mt-2 break-all text-xs">Tested model {{ $record['model'] ?? 'not recorded' }} · prompt {{ $record['prompt_version'] ?? 'not recorded' }}<br>Commit {{ $record['release_commit'] ?? 'not recorded' }}<br>Evidence SHA-256 {{ $record['evidence_hash'] ?? 'not recorded' }}</p>
            </div>
        @empty
            <p class="text-sm text-slate-600">No versioned model rehearsal or human-verification evidence is recorded in this installation.</p>
        @endforelse
    </div>
</x-card>
