<div class="max-w-4xl mx-auto py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    <x-card>
        <x-slot:header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Intro video (optional)</h1>
                    <p class="text-sm text-slate-600 mt-1">A short video presentation helps families trust faster and usually improves profile conversion.</p>
                </div>
                <a href="{{ route('dashboard') }}" wire:navigate>
                    <x-button color="slate" light sm>Back to dashboard</x-button>
                </a>
            </div>
        </x-slot:header>

        <div class="space-y-4">
            <div class="rounded-md border border-cyan-200 bg-cyan-50 px-3 py-2 text-sm text-cyan-900">
                Suggested content: who you are, caregiving experience, preferred tasks, and your communication style (30-90 seconds).
            </div>
            <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
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
</div>
