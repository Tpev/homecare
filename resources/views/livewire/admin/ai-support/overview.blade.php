<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Restricted administration</p>
            <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-950">AI Support</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Pilot access, knowledge governance, and safety controls. Human support remains the canonical conversation.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.ai-support.pilots.index') }}" wire:navigate class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-800">Pilot users</a>
            <a href="{{ route('admin.ai-support.knowledge.index') }}" wire:navigate class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-800">Knowledge base</a>
            <a href="{{ route('admin.ai-support.activity.index') }}" wire:navigate class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-800">Activity</a>
            <a href="{{ route('admin.ai-support.settings') }}" wire:navigate class="inline-flex min-h-11 items-center rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white">Settings</a>
        </div>
    </div>

    @if(! $runtimeAvailable)
        <x-alert color="blue">The deployment runtime guard is off. No customer-facing AI can become eligible, even when a grant is stored.</x-alert>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        <x-card>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Customer AI state</p>
            <p class="mt-2 text-2xl font-extrabold text-slate-950">{{ $runtimeAvailable && $masterState['enabled'] && $visibleState['enabled'] && ! $humanOnlyState['enabled'] ? 'Pilot controls open' : 'Failing closed' }}</p>
            <p class="mt-2 text-sm text-slate-600">Master {{ $masterState['enabled'] ? 'on' : 'off' }} · user-visible {{ $visibleState['enabled'] ? 'on' : 'off' }} · human-only {{ $humanOnlyState['enabled'] ? 'on' : 'off' }}</p>
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
            <li class="rounded-xl border border-slate-200 p-4"><strong>Exact users only.</strong> Grants never extend to another family or account member.</li>
            <li class="rounded-xl border border-slate-200 p-4"><strong>No model runtime.</strong> This phase contains no customer model-call path.</li>
            <li class="rounded-xl border border-slate-200 p-4"><strong>Human support preserved.</strong> Existing chat, claim, reply, resolve, and close behavior remains primary.</li>
            <li class="rounded-xl border border-slate-200 p-4"><strong>Shadow locked.</strong> Production-data shadowing cannot be enabled before DEC-014 is approved.</li>
        </ul>
    </x-card>
</div>
