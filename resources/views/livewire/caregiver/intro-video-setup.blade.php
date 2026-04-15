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
                    <h1 class="text-xl font-semibold">Intro video (optional)</h1>
                    <p class="text-sm text-[#607080] mt-1">A short video presentation helps families trust faster and usually improves profile conversion.</p>
                </div>
                <a href="{{ route('caregiver.setup.index') }}" wire:navigate>
                    <x-button color="slate" light sm>Back to setup</x-button>
                </a>
            </div>
        </x-slot:header>

        <div class="space-y-4">
            <div class="rounded-md border border-[#BDD4F7] bg-[#EEF5FF] px-3 py-2 text-sm text-[#0F3D3E]">
                Suggested content: who you are, caregiving experience, preferred tasks, and your communication style (30-90 seconds).
            </div>
            <div class="rounded-md border border-[#E4DDD3] bg-[#F7F2EA] px-3 py-2 text-sm text-[#4B5B6B]">
                Optional step. Once required setup is complete, submit from
                <a href="{{ route('caregiver.onboarding', ['step' => 4]) }}" wire:navigate class="font-semibold underline underline-offset-2">onboarding review</a>.
            </div>

            <x-upload label="Upload intro video" wire:model="intro_video" />
            @error('intro_video') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            @if ($intro_video_path)
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                    Video uploaded and linked to your profile.
                </div>
            @endif
        </div>

        <x-slot:footer>
            <div class="flex items-center justify-between">
                <x-button color="red" light wire:click="remove" :disabled="! $intro_video_path">Remove video</x-button>
                <x-button color="blue" wire:click="save">Save intro video</x-button>
            </div>
        </x-slot:footer>
    </x-card>

    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-[#E4DDD3] bg-white/95 p-3 backdrop-blur sm:hidden">
        <x-button color="blue" class="w-full" wire:click="save">Save</x-button>
    </div>
</div>

