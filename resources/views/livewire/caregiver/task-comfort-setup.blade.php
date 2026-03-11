<div class="max-w-4xl mx-auto py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    <x-card>
        <x-slot:header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Task comfort selection</h1>
                    <p class="text-sm text-slate-600 mt-1">Required before review. Select every non-medical task you are comfortable handling.</p>
                </div>
                <a href="{{ route('dashboard') }}" wire:navigate>
                    <x-button color="slate" light sm>Back to dashboard</x-button>
                </a>
            </div>
        </x-slot:header>

        <div class="space-y-4">
            <div class="rounded-md border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm text-cyan-900">
                Setup flow: complete required cards, then open
                <a href="{{ route('caregiver.onboarding', ['step' => 4]) }}" wire:navigate class="font-semibold underline underline-offset-2">Submit for review</a>.
                Reviews usually finish within 1 business day.
            </div>

            <x-select.styled
                wire:model="selectedSkills"
                multiple
                label="Tasks you are comfortable with"
                :options="collect($skillOptions)->map(fn($item)=>['label'=>$item['name'],'value'=>$item['id']])->values()->all()"
            />
            @error('selectedSkills') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('selectedSkills.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <x-slot:footer>
            <x-button color="blue" wire:click="save">Save task preferences</x-button>
        </x-slot:footer>
    </x-card>
</div>
