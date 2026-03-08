<div class="hc-page py-8 space-y-6">
    <x-card>
        <x-slot:header>
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Funnel Analytics</h1>
                <x-select.styled
                    wire:model.live="days"
                    :options="[
                        ['label' => 'Last 7 days', 'value' => 7],
                        ['label' => 'Last 14 days', 'value' => 14],
                        ['label' => 'Last 30 days', 'value' => 30],
                    ]"
                />
            </div>
        </x-slot:header>

        <p class="text-sm text-slate-600 mb-3">Showing events since {{ $start->format('M d, Y') }}</p>

        <div class="space-y-2">
            @forelse($events as $row)
                <div class="rounded-lg border border-slate-200 px-3 py-2 flex items-center justify-between">
                    <p class="font-medium text-slate-900">{{ $row->event }}</p>
                    <x-badge :text="(string) $row->total" color="blue" />
                </div>
            @empty
                <p class="text-sm text-slate-600">No events in selected window.</p>
            @endforelse
        </div>
    </x-card>
</div>
