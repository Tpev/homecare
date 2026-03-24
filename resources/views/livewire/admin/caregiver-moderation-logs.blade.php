<div class="hc-page py-8">
    <x-card>
        <x-slot:header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Caregiver Moderation Logs</h1>
                    <p class="mt-1 text-sm text-slate-600">Recent moderation actions, reviewer decisions, and caregiver history.</p>
                </div>
                <div class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                    {{ $logs->total() }} records
                </div>
            </div>
        </x-slot:header>

        <div class="space-y-3">
            @foreach($logs as $log)
                <div class="rounded-2xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <span class="inline-flex w-fit rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-white">
                            {{ strtoupper($log->action) }}
                        </span>
                        <span class="text-xs text-slate-500">{{ $log->created_at }}</span>
                    </div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-slate-500">Caregiver</p>
                            <p class="mt-1 font-medium text-slate-900">{{ $log->caregiverProfile->user->name ?? 'N/A' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-slate-500">Actor</p>
                            <p class="mt-1 font-medium text-slate-900">{{ $log->actor->name ?? 'System' }}</p>
                        </div>
                    </div>
                    @if($log->note)
                        <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-slate-500">Note</p>
                            <p class="mt-1 text-slate-700">{{ $log->note }}</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <x-slot:footer>
            {{ $logs->links() }}
        </x-slot:footer>
    </x-card>
</div>
