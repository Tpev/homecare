<div class="hc-page space-y-5 py-5 sm:space-y-6 sm:py-8">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @php
        $booking = $sourceRequest->booking;
        $lastVisitLabel = $booking?->scheduled_start_at
            ? $booking->scheduled_start_at->format('M d, Y')
            : 'your last visit';
        $recipientName = $sourceRequest->recipient?->full_name ?: 'the care recipient';
        $address = trim(collect([
            $sourceRequest->address_line1,
            $sourceRequest->address_line2,
            trim($sourceRequest->city.', '.$sourceRequest->state.' '.$sourceRequest->zip),
        ])->filter()->implode(', '));
    @endphp

    <section class="rounded-3xl border border-[#E4DDD3] bg-[#23483F] p-5 text-white shadow-sm sm:p-7">
        <div class="max-w-3xl">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#D8E8D4]">Request care with {{ $caregiverFirstName }}</p>
            <h1 class="mt-2 font-display text-3xl font-semibold leading-tight sm:text-4xl">
                One visit or regular care—without starting over.
            </h1>
            <p class="mt-3 text-base leading-7 text-[#F7F1E8]">
                We copied the care details from {{ $lastVisitLabel }}. Choose how often you need care; the existing invitation, approval, and payment flow stays the same.
            </p>
        </div>
    </section>

    <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_420px]">
        <x-card>
            <x-slot:header>
                <div>
                    <p class="hc-brand-kicker">One visit</p>
                    <h2 class="mt-1 font-display text-2xl font-semibold text-[#17313F]">Invite {{ $caregiverFirstName }} for a specific date</h2>
                    <p class="mt-1 text-sm text-[#607080]">They will see it in their work inbox. If they accept, you can hire them from the request page.</p>
                </div>
            </x-slot:header>

            <div class="space-y-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <x-input type="date" label="Starting day" wire:model.change="visitDate" />
                        @error('visitDate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-input type="time" label="Starting time" wire:model.change="startTime" />
                        @error('startTime') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-native-select-field label="Duration" wire:model.live="durationMinutes" :options="$durationOptions" />
                        @error('durationMinutes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <x-textarea label="Message to caregiver" wire:model="message" />
                @error('message') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                @if ($careProfileUpdateAvailable)
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-[#CFE1D8] bg-[#F2F8F4] p-4">
                        <input type="checkbox" wire:model="useLatestCareProfile" class="mt-1 rounded border-[#AFA394] text-[#0F3D3E] focus:ring-[#0F3D3E]">
                        <span><span class="block font-semibold text-[#17313F]">Use the latest care profile for {{ $careProfileName }}</span><span class="mt-1 block text-sm text-[#526474]">Leave this off to reuse exactly what was shared for the last visit.</span></span>
                    </label>
                @endif

                <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-4">
                    <p class="font-display text-lg font-semibold text-[#17313F]">Copied from last visit</p>
                    <div class="mt-3 grid gap-3 text-sm text-[#4B5B6B] sm:grid-cols-2">
                        <div class="rounded-xl border border-[#E4DDD3] bg-white px-3 py-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#7B8794]">Caregiver</p>
                            <p class="mt-1 font-semibold text-[#17313F]">{{ $caregiverName }}</p>
                        </div>
                        <div class="rounded-xl border border-[#E4DDD3] bg-white px-3 py-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#7B8794]">For</p>
                            <p class="mt-1 font-semibold text-[#17313F]">{{ $recipientName }}</p>
                        </div>
                        <div class="rounded-xl border border-[#E4DDD3] bg-white px-3 py-2 sm:col-span-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#7B8794]">Location</p>
                            <p class="mt-1 font-semibold text-[#17313F]">{{ $address !== '' ? $address : 'Same care location' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <x-slot:footer>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('family.requests.show', $sourceRequest->id) }}" wire:navigate class="hc-secondary-button w-full sm:w-auto">Back to visit</a>
                    <x-button color="green" wire:click="sendOneTimeInvite" class="w-full sm:w-auto">Send one-time invite</x-button>
                </div>
            </x-slot:footer>
        </x-card>

        <div class="space-y-5">
            <x-card>
                <x-slot:header>
                    <div>
                        <p class="hc-brand-kicker">Regular care</p>
                        <h2 class="mt-1 font-display text-xl font-semibold text-[#17313F]">Set up a repeating schedule</h2>
                    </div>
                </x-slot:header>

                <div class="space-y-4 text-sm leading-6 text-[#4B5B6B]">
                    <p>
                        Choose this when {{ $caregiverFirstName }} should come every week. You can set the days, time, start date, and end date before sending the offer.
                    </p>
                        <a href="{{ route('family.care.compose', $sourceRequest->id) }}" wire:navigate class="hc-primary-button w-full">Set up regular care</a>
                </div>
            </x-card>

            <x-card>
                <x-slot:header>
                    <h2 class="font-display text-xl font-semibold">What happens next</h2>
                </x-slot:header>

                <div class="space-y-3 text-sm text-[#4B5B6B]">
                    <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-3">
                        <p class="font-semibold text-[#17313F]">1. {{ $caregiverFirstName }} accepts or declines</p>
                        <p class="mt-1">They get a direct invite with the copied care details.</p>
                    </div>
                    <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-3">
                        <p class="font-semibold text-[#17313F]">2. You hire when ready</p>
                        <p class="mt-1">The normal request page handles chat, profile review, hiring, and payment authorization.</p>
                    </div>
                    <div class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-3">
                        <p class="font-semibold text-[#17313F]">3. The visit follows the usual flow</p>
                        <p class="mt-1">Check-in, timesheet, approval, and payout stay in the same system.</p>
                    </div>
                </div>
            </x-card>
        </div>
    </section>
</div>
