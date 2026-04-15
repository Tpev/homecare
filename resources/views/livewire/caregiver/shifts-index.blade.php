<div>
    <div class="hc-page py-6 space-y-5">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        @php
            $statusTone = fn (string $value) => match ($value) {
                \App\Models\CareBooking::STATUS_IN_PROGRESS => 'bg-emerald-100 text-emerald-700',
                \App\Models\CareBooking::STATUS_PAUSED => 'bg-amber-100 text-amber-800',
                \App\Models\CareBooking::STATUS_SCHEDULED => 'bg-sky-100 text-sky-700',
                \App\Models\CareBooking::STATUS_COMPLETED => 'bg-indigo-100 text-indigo-700',
                \App\Models\CareBooking::STATUS_REVIEWED => 'bg-emerald-100 text-emerald-700',
                \App\Models\CareBooking::STATUS_DISPUTED => 'bg-rose-100 text-rose-700',
                \App\Models\CareBooking::STATUS_CANCELLED => 'bg-slate-200 text-slate-700',
                default => 'bg-slate-100 text-slate-700',
            };

            $filterOptions = [
                ['label' => 'All', 'value' => 'all'],
                ['label' => 'Scheduled', 'value' => \App\Models\CareBooking::STATUS_SCHEDULED],
                ['label' => 'In progress', 'value' => \App\Models\CareBooking::STATUS_IN_PROGRESS],
                ['label' => 'Paused', 'value' => \App\Models\CareBooking::STATUS_PAUSED],
                ['label' => 'Completed', 'value' => \App\Models\CareBooking::STATUS_COMPLETED],
                ['label' => 'Reviewed', 'value' => \App\Models\CareBooking::STATUS_REVIEWED],
                ['label' => 'Issues', 'value' => \App\Models\CareBooking::STATUS_DISPUTED],
            ];
        @endphp

        <section class="hc-brand-panel">
            <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-[#7C5DDC]/20 blur-2xl"></div>
            <div class="pointer-events-none absolute -left-10 -bottom-12 h-40 w-40 rounded-full bg-[#4F6FAF]/20 blur-2xl"></div>

            <div class="relative space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="hc-brand-kicker text-[#E8E0FF]">My shifts</p>
                        <h1 class="mt-1 text-2xl font-display font-semibold leading-tight sm:text-3xl">Get ready for your first shift.</h1>
                        <p class="mt-1 text-sm text-[#F7F1E8]/82">Start, pause, resume, and close shifts from one command view.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('caregiver.work-inbox.index') }}" wire:navigate>
                            <x-button color="white" light sm>Work Inbox</x-button>
                        </a>
                        <a href="{{ route('caregiver.earnings.index') }}" wire:navigate>
                            <x-button color="white" light sm>Earnings</x-button>
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <div class="hc-brand-stat">
                        <p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Active</p>
                        <p class="mt-1 text-lg font-semibold">{{ (int) ($counts['active'] ?? 0) }}</p>
                    </div>
                    <div class="hc-brand-stat">
                        <p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Scheduled</p>
                        <p class="mt-1 text-lg font-semibold">{{ (int) ($counts['scheduled'] ?? 0) }}</p>
                    </div>
                    <div class="hc-brand-stat">
                        <p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Completed</p>
                        <p class="mt-1 text-lg font-semibold">{{ (int) ($counts['completed'] ?? 0) }}</p>
                    </div>
                    <div class="hc-brand-stat">
                        <p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Issues</p>
                        <p class="mt-1 text-lg font-semibold">{{ (int) ($counts['issues'] ?? 0) }}</p>
                    </div>
                </div>

                @if (!empty($nextShift))
                    <div class="rounded-[1.4rem] border border-[#D3CBF0] bg-[rgba(255,255,255,0.08)] px-3 py-3">
                        <p class="text-xs uppercase tracking-[0.14em] text-[#E8E0FF]">Next shift</p>
                        <p class="mt-1 text-sm font-semibold text-[#FAF9F7]">{{ $nextShift->careRequest?->title ?? 'Upcoming shift' }}</p>
                        <p class="text-xs text-[#F0E9E1]/82">
                            {{ optional($nextShift->scheduled_start_at)->format('D, M d \\a\\t H:i') }}
                            · {{ $nextShift->careRequest?->city }}, {{ $nextShift->careRequest?->state }}
                        </p>
                    </div>
                @endif
            </div>
        </section>

        <div class="sticky top-16 z-20 -mx-1 px-1">
            <div class="rounded-2xl border border-[#DED6CA] bg-[rgba(255,253,250,0.95)] p-2 shadow-sm backdrop-blur">
                <div class="overflow-x-auto">
                    <div class="grid min-w-full grid-cols-2 gap-1 sm:flex sm:min-w-max">
                        @foreach ($filterOptions as $option)
                            <button
                                type="button"
                                wire:click="$set('status', '{{ $option['value'] }}')"
                                class="h-11 rounded-xl px-3 text-sm font-medium transition {{ $status === $option['value'] ? 'bg-[#0F3D3E] text-[#FAF9F7]' : 'text-[#6E746F] hover:bg-[#F5F1EB] hover:text-[#0F3D3E]' }}"
                            >
                                {{ $option['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <section class="space-y-3">
            @forelse ($bookings as $booking)
                @php
                    $request = $booking->careRequest;
                    $bookingStatus = (string) $booking->status;
                    $ctaLabel = match ($bookingStatus) {
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

                <article class="rounded-2xl border border-[#DED6CA] bg-[rgba(255,253,250,0.98)] p-4 shadow-sm sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-display text-lg font-semibold text-slate-900">{{ $request?->title ?? 'Care request' }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $request?->city ?? '-' }}, {{ $request?->state ?? '-' }} · Family: {{ $booking->family?->name ?? 'Unknown' }}</p>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusTone($bookingStatus) }}">
                            {{ strtoupper($bookingStatus) }}
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-2 text-xs text-slate-600 sm:grid-cols-3">
                        <div class="rounded-lg border border-[#DED6CA] bg-[#F5F1EB] px-3 py-2">
                            Scheduled:
                            {{ optional($booking->scheduled_start_at)->format('M d, H:i') ?: '-' }}
                            -
                            {{ optional($booking->scheduled_end_at)->format('H:i') ?: '-' }}
                        </div>
                        <div class="rounded-lg border border-[#DED6CA] bg-[#F5F1EB] px-3 py-2">
                            Started: {{ optional($booking->started_at)->format('M d, H:i') ?: 'Pending' }}
                        </div>
                        <div class="rounded-lg border border-[#DED6CA] bg-[#F5F1EB] px-3 py-2">
                            Completed: {{ optional($booking->completed_at)->format('M d, H:i') ?: 'Pending' }}
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-2 sm:flex sm:flex-wrap">
                        @if ($request)
                            <a
                                href="{{ route('care-requests.apply', $request->id) }}"
                                wire:navigate
                                class="hc-primary-button w-full sm:w-auto"
                            >
                                {{ $ctaLabel }}
                            </a>
                        @endif

                        @if ($booking->application?->conversation)
                            <a
                                href="{{ route('messages.show', $booking->application->conversation->id) }}"
                                wire:navigate
                                class="hc-secondary-button w-full sm:w-auto"
                            >
                                Open chat
                            </a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-[rgba(255,253,250,0.98)] px-4 py-8 text-center text-sm text-slate-600">
                    No shifts yet. Once a family hires you, your shift actions appear here.
                </div>
            @endforelse
        </section>

        @if ($bookings->hasPages())
            <div>
                {{ $bookings->links() }}
            </div>
        @endif

        @if ($hiredWithoutBooking->count() > 0)
            <x-card>
                <x-slot:header>
                    <h2 class="font-display text-lg font-semibold">Pending shift setup</h2>
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

