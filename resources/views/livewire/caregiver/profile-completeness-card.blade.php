<x-card>
    <x-slot:header>
        <div class="flex items-center justify-between">
            <h3 class="font-semibold">Profile completeness</h3>
            <x-badge color="blue" :text="$percent.'%'" />
        </div>
    </x-slot:header>

    <div class="space-y-2 text-sm">
        @foreach($checks as $label => $ok)
            <div class="flex items-center justify-between">
                <span>{{ $label }}</span>
                <span>{{ $ok ? '✅' : '⬜' }}</span>
            </div>
        @endforeach
    </div>
</x-card>
