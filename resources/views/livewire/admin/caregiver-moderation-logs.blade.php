<div class="max-w-7xl mx-auto py-8">
    <x-card>
        <x-slot:header>
            <h1 class="text-xl font-semibold">Caregiver Moderation Logs</h1>
        </x-slot:header>

        <div class="space-y-3">
            @foreach($logs as $log)
                <div class="border rounded p-3 text-sm">
                    <div class="flex justify-between">
                        <span class="font-medium">{{ strtoupper($log->action) }}</span>
                        <span class="text-slate-500">{{ $log->created_at }}</span>
                    </div>
                    <p>Caregiver: {{ $log->caregiverProfile->user->name ?? 'N/A' }}</p>
                    <p>Actor: {{ $log->actor->name ?? 'System' }}</p>
                    @if($log->note)<p>Note: {{ $log->note }}</p>@endif
                </div>
            @endforeach
        </div>

        <x-slot:footer>
            {{ $logs->links() }}
        </x-slot:footer>
    </x-card>
</div>
