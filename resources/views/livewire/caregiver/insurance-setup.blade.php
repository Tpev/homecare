<div class="max-w-4xl mx-auto py-8 space-y-6">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    <x-card>
        <x-slot:header>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Insurance setup</h1>
                    <p class="text-sm text-slate-600 mt-1">Required before profile submission. Tell us if you carry caregiver liability insurance.</p>
                </div>
                <a href="{{ route('dashboard') }}" wire:navigate>
                    <x-button color="slate" light sm>Back to dashboard</x-button>
                </a>
            </div>
        </x-slot:header>

        <div class="space-y-4">
            <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                Insurance is optional for submission. You can submit without it and update this later anytime.
            </div>

            <x-select.styled
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
                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    You can continue without insurance. If that changes later, you can upload proof here anytime.
                </div>
            @endif
        </div>

        <x-slot:footer>
            <x-button color="blue" wire:click="save">Save insurance setup</x-button>
        </x-slot:footer>
    </x-card>
</div>
