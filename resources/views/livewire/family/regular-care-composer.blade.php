<div class="hc-page space-y-5 py-5 sm:space-y-6 sm:py-8">
    @if (session('status'))
        <x-alert color="amber">{{ session('status') }}</x-alert>
    @endif

    @php
        $caregiver = $hiredApplication?->caregiver;
        $card = $billingSummary['card'] ?? null;
        $dayOptions = [
            0 => 'Sun',
            1 => 'Mon',
            2 => 'Tue',
            3 => 'Wed',
            4 => 'Thu',
            5 => 'Fri',
            6 => 'Sat',
        ];
    @endphp

    <section class="hc-brand-panel">
        <div class="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="hc-brand-kicker text-[#E8E0FF]">Regular care setup</p>
                <h1 class="mt-1 text-2xl font-display font-semibold leading-tight sm:text-3xl">Send a direct schedule to {{ $caregiver?->name ?: 'your caregiver' }}.</h1>
                <p class="mt-2 max-w-2xl text-sm text-[#F7F1E8]/82">
                    This creates a real regular-care offer. When the caregiver accepts, LoLo generates the next request, books the shift, and authorizes payment.
                </p>
            </div>
            <a href="{{ route('family.requests.show', $requestItem->id) }}" wire:navigate>
                <x-button color="white" light class="w-full sm:w-auto">Back to request</x-button>
            </a>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-5 xl:grid-cols-12">
        <form wire:submit="sendOffer" class="space-y-5 xl:col-span-8">
            <x-card>
                <x-slot:header>
                    <div>
                        <h2 class="font-display text-lg font-semibold">Schedule</h2>
                        <p class="text-sm text-[#607080]">Pick the weekly rhythm that should be offered to the caregiver.</p>
                    </div>
                </x-slot:header>

                <div class="space-y-5">
                    <x-input label="Plan name" wire:model="title" />
                    @error('title') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <div>
                        <p class="text-sm font-medium text-[#324457]">Care days</p>
                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
                            @foreach ($dayOptions as $value => $label)
                                <label class="flex h-12 cursor-pointer items-center justify-center rounded-xl border text-sm font-semibold transition {{ in_array((string) $value, $scheduleDays, true) ? 'border-[#0F3D3E] bg-[#0F3D3E] text-white' : 'border-[#DED6CA] bg-white text-[#0F3D3E] hover:bg-[#F5F1EB]' }}">
                                    <input type="checkbox" class="sr-only" value="{{ $value }}" wire:model.live="scheduleDays">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('scheduleDays') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-input type="time" label="Start time" wire:model="scheduleStartTime" />
                        <x-input type="time" label="End time" wire:model="scheduleEndTime" />
                    </div>
                    @error('scheduleStartTime') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @error('scheduleEndTime') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-input type="date" label="First eligible date" wire:model="startsOn" />
                        <x-input type="date" label="Optional end date" wire:model="endsOn" />
                    </div>
                    @error('startsOn') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @error('endsOn') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </x-card>

            <x-card>
                <x-slot:header>
                    <div>
                        <h2 class="font-display text-lg font-semibold">Care details</h2>
                        <p class="text-sm text-[#607080]">These details are copied into generated requests so the caregiver sees consistent expectations.</p>
                    </div>
                </x-slot:header>

                <div class="space-y-4">
                    <div class="rounded-2xl border border-[#CFE1D8] bg-[#F2F8F4] p-4">
                        <p class="text-xs uppercase tracking-[0.12em] text-emerald-700">Platform rate</p>
                        <p class="mt-1 font-display text-2xl font-semibold text-emerald-950">${{ number_format((float) $platformRate, 2) }}/hr</p>
                        <p class="mt-1 text-sm text-emerald-800">LoLo sets the care rate. Families and caregivers do not edit it in regular-care setup.</p>
                    </div>

                    <x-textarea label="Care notes" wire:model="careNotes" />
                    @error('careNotes') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                    <x-textarea label="Message to caregiver" wire:model="familyMessage" />
                    @error('familyMessage') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <x-slot:footer>
                    @if ($billingSummary['ready'] ?? false)
                        <x-button color="blue" type="submit">Send regular-care offer</x-button>
                    @else
                        <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-amber-700">Add a payment method before sending this offer.</p>
                            <a href="{{ route('family.billing.show') }}" wire:navigate>
                                <x-button color="amber" type="button">Open billing</x-button>
                            </a>
                        </div>
                    @endif
                </x-slot:footer>
            </x-card>
        </form>

        <aside class="space-y-5 xl:col-span-4">
            <x-card>
                <x-slot:header>
                    <h2 class="font-display text-lg font-semibold">Source request</h2>
                </x-slot:header>
                <div class="space-y-3 text-sm">
                    <div>
                        <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Request</p>
                        <p class="mt-1 font-semibold text-[#17313F]">{{ $requestItem->title }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Caregiver</p>
                        <p class="mt-1 font-semibold text-[#17313F]">{{ $caregiver?->name ?: '-' }}</p>
                        <p class="text-[#607080]">Platform rate: ${{ number_format((float) $platformRate, 2) }}/hr</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Recipient</p>
                        <p class="mt-1 font-semibold text-[#17313F]">{{ $requestItem->recipient?->full_name ?: '-' }}</p>
                    </div>
                </div>
            </x-card>

            <x-card>
                <x-slot:header>
                    <h2 class="font-display text-lg font-semibold">Payment readiness</h2>
                </x-slot:header>
                @if ($billingSummary['ready'] ?? false)
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                        <p class="font-semibold">Ready for authorization</p>
                        <p class="mt-1">
                            {{ strtoupper((string) ($card['brand'] ?? 'Card')) }} ending {{ $card['last4'] ?? '----' }}
                        </p>
                    </div>
                @else
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        <p class="font-semibold">Payment method required</p>
                        <p class="mt-1">LoLo authorizes the next generated visit after the caregiver accepts.</p>
                    </div>
                @endif
            </x-card>
        </aside>
    </section>
</div>
