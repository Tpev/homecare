<div class="flex flex-wrap gap-2" role="group" aria-label="History section">
    @foreach (['visits' => 'Visits & payments', 'expired' => 'Expired requests'] as $section => $label)
        <button type="button" wire:click="$set('historyView', '{{ $section }}')" aria-pressed="{{ $historyView === $section ? 'true' : 'false' }}"
            @class(['min-h-11 rounded-xl border px-4 py-2 text-sm font-semibold', 'border-[#0F3D3E] bg-[#0F3D3E] text-white' => $historyView === $section, 'border-[#D6CCBE] bg-white text-[#0F3D3E]' => $historyView !== $section])>{{ $label }}</button>
    @endforeach
</div>
