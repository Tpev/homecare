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
                    <h1 class="text-xl font-semibold">Insurance setup</h1>
                    <p class="text-sm text-[#607080] mt-1">Optional step. Tell us if you carry caregiver liability insurance.</p>
                </div>
                <a href="{{ route('caregiver.setup.index') }}" wire:navigate>
                    <x-button color="slate" light sm>Back to setup</x-button>
                </a>
            </div>
        </x-slot:header>

        <div class="space-y-4">
            <div class="rounded-md border border-[#E4DDD3] bg-[#F7F2EA] px-3 py-2 text-sm text-[#4B5B6B]">
                Insurance is optional for submission. You can submit without it and update this later anytime.
            </div>

            <x-native-select-field
                label="Do you currently have insurance?"
                wire:model="insurance_status"
                :options="[
                    ['label' => 'Select an answer', 'value' => \App\Models\CaregiverProfile::INSURANCE_NOT_PROVIDED],
                    ['label' => 'No, I do not have insurance', 'value' => \App\Models\CaregiverProfile::INSURANCE_NO],
                    ['label' => 'Yes, I have insurance', 'value' => \App\Models\CaregiverProfile::INSURANCE_YES],
                ]"
            />

            @if ($insurance_status === \App\Models\CaregiverProfile::INSURANCE_YES)
                <x-upload label="Upload proof of insurance (PDF or image)" wire:model="insurance_document" />
                @error('insurance_document') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                @if ($insurance_document_path)
                    <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                        Proof uploaded. You can upload a new file to replace it.
                    </div>
                @endif
            @else
                <div class="rounded-md border border-[#E4DDD3] bg-[#F7F2EA] px-3 py-2 text-sm text-[#4B5B6B]">
                    You can continue without insurance. If that changes later, you can upload proof here anytime.
                </div>
            @endif
        </div>

        <x-slot:footer>
            <x-button color="blue" wire:click="save">Save insurance setup</x-button>
        </x-slot:footer>
    </x-card>

    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-[#E4DDD3] bg-white/95 p-3 backdrop-blur sm:hidden">
        <x-button color="blue" class="w-full" wire:click="save">Save</x-button>
    </div>
</div>

