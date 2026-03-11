<div>
    <div class="hc-page py-8 space-y-6">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        <section class="hc-hero">
            <p class="text-xs uppercase tracking-[0.2em] text-emerald-100">My shifts</p>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-4">
                <div class="max-w-2xl">
                    <h1 class="text-3xl font-display font-semibold leading-tight">Start and manage your hired shifts.</h1>
                    <p class="mt-2 text-sm text-emerald-100">
                        Open any shift to check in, run your live timer, and check out with recap.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('care-requests.index') }}" wire:navigate>
                        <x-button color="white">Browse requests</x-button>
                    </a>
                    <a href="{{ route('caregiver.invitations.index') }}" wire:navigate>
                        <x-button color="white" light>Invitations</x-button>
                    </a>
                </div>
            </div>
        </section>

        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-lg font-semibold">Shift list</h2>
                    <x-select.styled
                        wire:model.live="status"
                        :options="[
                            ['label' => 'All statuses', 'value' => 'all'],
                            ['label' => 'In progress', 'value' => \App\Models\CareBooking::STATUS_IN_PROGRESS],
                            ['label' => 'Paused', 'value' => \App\Models\CareBooking::STATUS_PAUSED],
                            ['label' => 'Scheduled', 'value' => \App\Models\CareBooking::STATUS_SCHEDULED],
                            ['label' => 'Completed', 'value' => \App\Models\CareBooking::STATUS_COMPLETED],
                            ['label' => 'Reviewed', 'value' => \App\Models\CareBooking::STATUS_REVIEWED],
                            ['label' => 'Disputed', 'value' => \App\Models\CareBooking::STATUS_DISPUTED],
                            ['label' => 'Cancelled', 'value' => \App\Models\CareBooking::STATUS_CANCELLED],
                        ]"
                    />
                </div>
            </x-slot:header>

            <div class="space-y-3">
                @forelse ($bookings as $booking)
                    @php
                        $request = $booking->careRequest;
                        $status = (string) $booking->status;
                        $ctaLabel = match ($status) {
                            \App\Models\CareBooking::STATUS_SCHEDULED => 'Start shift',
                            \App\Models\CareBooking::STATUS_IN_PROGRESS => 'Continue shift',
                            \App\Models\CareBooking::STATUS_PAUSED => 'Resume shift',
                            \App\Models\CareBooking::STATUS_COMPLETED => 'View recap',
                            \App\Models\CareBooking::STATUS_REVIEWED => 'View shift',
                            \App\Models\CareBooking::STATUS_DISPUTED => 'Open dispute view',
                            \App\Models\CareBooking::STATUS_CANCELLED => 'View details',
                            default => 'Open shift',
                        };
                    @endphp

                    <div class="rounded-xl border border-slate-200 bg-white p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-display text-lg font-semibold text-slate-900">{{ $request?->title ?? 'Care request' }}</p>
                                <p class="text-sm text-slate-600">
                                    Family: {{ $booking->family?->name ?? 'Unknown' }}
                                    · {{ $request?->city ?? '-' }}, {{ $request?->state ?? '-' }}
                                </p>
                            </div>
                            <x-badge :text="strtoupper($status)" color="{{ in_array($status, [\App\Models\CareBooking::STATUS_IN_PROGRESS, \App\Models\CareBooking::STATUS_PAUSED], true) ? 'green' : 'blue' }}" />
                        </div>

                        <div class="mt-3 grid grid-cols-1 gap-2 text-xs text-slate-600 md:grid-cols-3">
                            <p>
                                Scheduled:
                                {{ optional($booking->scheduled_start_at)->format('M d, Y H:i') ?: '-' }}
                                -
                                {{ optional($booking->scheduled_end_at)->format('H:i') ?: '-' }}
                            </p>
                            <p>Started: {{ optional($booking->started_at)->format('M d, H:i') ?: 'Pending' }}</p>
                            <p>Completed: {{ optional($booking->completed_at)->format('M d, H:i') ?: 'Pending' }}</p>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            @if ($request)
                                <a href="{{ route('care-requests.apply', $request->id) }}" wire:navigate>
                                    <x-button color="{{ in_array($status, [\App\Models\CareBooking::STATUS_IN_PROGRESS, \App\Models\CareBooking::STATUS_PAUSED], true) ? 'green' : 'blue' }}" sm>{{ $ctaLabel }}</x-button>
                                </a>
                            @endif

                            @if ($booking->application?->conversation)
                                <a href="{{ route('messages.show', $booking->application->conversation->id) }}" wire:navigate>
                                    <x-button color="slate" light sm>Open chat</x-button>
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-600">
                        No shifts yet. Once a family hires you, your shift actions appear here.
                    </div>
                @endforelse
            </div>

            @if ($bookings->hasPages())
                <div class="mt-4">
                    {{ $bookings->links() }}
                </div>
            @endif
        </x-card>

        @if ($hiredWithoutBooking->count() > 0)
            <x-card>
                <x-slot:header>
                    <h2 class="font-display text-lg font-semibold">Hired requests pending shift setup</h2>
                </x-slot:header>
                <div class="space-y-2">
                    @foreach ($hiredWithoutBooking as $application)
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-sm text-slate-900">{{ $application->careRequest?->title }}</p>
                                <a href="{{ route('care-requests.apply', $application->care_request_id) }}" wire:navigate class="text-xs font-medium text-indigo-700 underline underline-offset-2">
                                    Open request
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endif
    </div>
</div>
