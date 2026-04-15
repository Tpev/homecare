<div class="max-w-4xl mx-auto py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    <div class="rounded-xl border border-[#E4DDD3] bg-white px-4 py-3">
        <div class="flex items-center justify-between gap-3">
            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Required progress</p>
            <p class="text-sm font-semibold text-[#17313F]">{{ $onboarding['required_completed'] }}/{{ $onboarding['required_total'] }}</p>
        </div>
        <div class="mt-2 h-2 rounded-full bg-[#F0E9E1]">
            <div class="h-2 rounded-full bg-[#4F6FAF] transition-all duration-300" style="width: {{ $onboarding['progress_percent'] }}%"></div>
        </div>
    </div>

    <x-card>
        <x-slot:header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Task comfort selection</h1>
                    <p class="text-sm text-[#607080] mt-1">Required before review. Select every non-medical task you are comfortable handling.</p>
                </div>
                <a href="{{ route('caregiver.setup.index') }}" wire:navigate>
                    <x-button color="slate" light sm>Back to setup</x-button>
                </a>
            </div>
        </x-slot:header>

        <div class="space-y-4">
            <div class="rounded-md border border-[#BDD4F7] bg-[#EEF5FF] px-3 py-2 text-sm text-[#0F3D3E]">
                Pick all tasks you are comfortable doing. After save, we move you to your next required step automatically.
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

    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-[#E4DDD3] bg-white/95 p-3 backdrop-blur sm:hidden">
        <x-button color="blue" class="w-full" wire:click="save">Save and continue</x-button>
    </div>
</div>

