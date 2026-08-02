<div>
    <div class="hc-page py-8 space-y-6">
        @if ($mode === 'family')
            @include('livewire.dashboard.partials.family-home')

        @elseif ($mode === 'caregiver')
            @php
                $profile = $caregiverData['profile'] ?? null;
                $setupCards = $caregiverData['setup_cards'] ?? [];
                $requiredCompleted = (int) ($caregiverData['required_setup_completed'] ?? 0);
                $requiredTotal = (int) ($caregiverData['required_setup_total'] ?? 0);
                $readyForReview = (bool) ($caregiverData['ready_for_review'] ?? false);
                $canSubmitForReview = (bool) ($caregiverData['can_submit_for_review'] ?? false);
                $hasActiveShift = !empty($caregiverData['active_shift']);
                $hasNextShift = !empty($caregiverData['next_shift']);
                $needsResponseCount = (int) ($caregiverData['stats']['needs_response'] ?? 0);
                $prelaunchMode = (bool) ($caregiverData['prelaunch_mode'] ?? false);
                $prelaunchMessage = (string) ($caregiverData['prelaunch_message'] ?? 'LoLo Care is currently in caregiver pre-launch mode. Complete your setup now and we will notify you as soon as matching opens.');
                $profileIsActive = (string) ($profile?->status ?? '') === 'active';
                $setupStatusLabel = $profileIsActive ? 'Ready' : $requiredCompleted.'/'.$requiredTotal;

                $nextActionTitle = 'Respond to your inbox';
                $nextActionDescription = 'Open Work Inbox and answer pending invites first.';
                $nextActionHref = route('caregiver.work-inbox.index');
                $nextActionLabel = 'Open Work Inbox';

                if ($prelaunchMode) {
                    $nextActionTitle = 'Finish setup and get launch-ready';
                    $nextActionDescription = $prelaunchMessage;
                    $nextActionHref = route('caregiver.profile.edit');
                    $nextActionLabel = 'Complete setup';
                } elseif ($hasActiveShift && $caregiverData['active_shift']->careRequest) {
                    $nextActionTitle = 'Continue active visit';
                    $nextActionDescription = 'You have a visit in progress. Continue from the visit command center.';
                    $nextActionHref = route('care-requests.apply', $caregiverData['active_shift']->careRequest->id);
                    $nextActionLabel = $caregiverData['active_shift']->status === \App\Models\CareBooking::STATUS_PAUSED ? 'Resume visit' : 'Continue visit';
                } elseif ($needsResponseCount > 0) {
                    $nextActionTitle = 'Answer new opportunities';
                    $nextActionDescription = $needsResponseCount.' item(s) need a response in Work Inbox.';
                } elseif ($hasNextShift && $caregiverData['next_shift']->careRequest) {
                    $nextActionTitle = 'Prepare next visit';
                    $nextActionDescription = 'Review details before your next scheduled visit.';
                    $nextActionHref = route('care-requests.apply', $caregiverData['next_shift']->careRequest->id);
                    $nextActionLabel = 'Open next visit';
                } elseif (! $profileIsActive && count($setupCards) > 0) {
                    $nextActionTitle = 'Complete profile setup';
                    $nextActionDescription = 'Finish setup cards to stay visible and trustworthy to families.';
                    $nextActionHref = route('caregiver.profile.edit');
                    $nextActionLabel = 'Continue setup';
                }
            @endphp

            @if ($prelaunchMode)
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-sm font-semibold text-amber-900">Matching opens soon in your area.</p>
                    <p class="mt-1 text-sm text-amber-800">{{ $prelaunchMessage }}</p>
                </div>
            @endif

            <section class="hc-brand-panel">
                <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-[#7C5DDC]/20 blur-2xl"></div>
                <div class="pointer-events-none absolute -left-10 -bottom-14 h-40 w-40 rounded-full bg-[#4F6FAF]/20 blur-2xl"></div>

                <div class="relative grid grid-cols-1 gap-5 lg:grid-cols-5">
                    <div class="lg:col-span-3">
                        <p class="text-[11px] uppercase tracking-[0.18em] text-[#E8E0FF]">Caregiver Dashboard</p>
                        <h1 class="mt-1 text-2xl font-display font-semibold leading-tight sm:text-3xl">You're ready to start getting booked.</h1>
                        <p class="mt-2 text-sm text-[#F7F1E8]/82">{{ $nextActionDescription }}</p>
                        <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                            <a href="{{ $nextActionHref }}" wire:navigate class="block"><x-button color="white" class="w-full sm:w-auto">{{ $nextActionLabel }}</x-button></a>
                            <a href="{{ route('caregiver.shifts.index') }}" wire:navigate class="block"><x-button color="white" light class="w-full sm:w-auto">My visits</x-button></a>
                            <a href="{{ route('caregiver.earnings.index') }}" wire:navigate class="block"><x-button color="white" light class="w-full sm:w-auto">Earnings</x-button></a>
                        </div>
                    </div>
                    <div class="lg:col-span-2 space-y-2">
                        <div class="hc-brand-stat">
                            <p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Best next action</p>
                            <p class="mt-1 text-sm font-semibold text-white">{{ $nextActionTitle }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="hc-brand-stat">
                                <p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Needs response</p>
                                <p class="mt-1 text-lg font-semibold">{{ $needsResponseCount }}</p>
                            </div>
                            <div class="hc-brand-stat">
                                <p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Profile status</p>
                                <p class="mt-1 text-xs font-semibold uppercase">{{ str_replace('_', ' ', (string) ($profile?->status ?? 'draft')) }}</p>
                            </div>
                            <div class="hc-brand-stat">
                                <p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Setup</p>
                                <p class="mt-1 text-lg font-semibold">{{ $setupStatusLabel }}</p>
                            </div>
                            <div class="hc-brand-stat">
                                <p class="text-[11px] uppercase tracking-[0.16em] text-[#F0E9E1]/70">Unread messages</p>
                                <p class="mt-1 text-lg font-semibold">{{ $caregiverData['stats']['unread_messages'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            @php
                $caregiverDigest = $caregiverData['notification_digest'] ?? collect();
                $caregiverDigestToneStyles = [
                    'success' => 'bg-emerald-100 text-emerald-700',
                    'warning' => 'bg-amber-100 text-amber-800',
                    'danger' => 'bg-rose-100 text-rose-700',
                    'info' => 'bg-sky-100 text-sky-700',
                    'neutral' => 'bg-slate-100 text-slate-700',
                ];
            @endphp

            @if ($caregiverDigest->count() > 0)
                <x-card>
                    <x-slot:header>
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="font-display font-semibold">Top unread updates</h2>
                            <a href="{{ route('caregiver.notifications.index') }}" wire:navigate class="hc-link">See all</a>
                        </div>
                    </x-slot:header>
                    <div class="space-y-2">
                        @foreach ($caregiverDigest as $digest)
                            <a href="{{ $digest['url'] ?: route('caregiver.notifications.index') }}" wire:navigate class="block rounded-xl border border-slate-200 bg-white p-3 transition hover:border-slate-300 hover:bg-slate-50">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $caregiverDigestToneStyles[$digest['tone'] ?? 'neutral'] ?? $caregiverDigestToneStyles['neutral'] }}">
                                                {{ strtoupper($digest['event_label']) }}
                                            </span>
                                            <span class="text-xs text-slate-500">{{ optional($digest['created_at'])->diffForHumans() }}</span>
                                        </div>
                                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $digest['title'] }}</p>
                                        <p class="mt-1 text-sm text-slate-600">{{ $digest['body'] }}</p>
                                    </div>
                                    <span class="inline-flex h-11 items-center rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700">Open</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </x-card>
            @endif

            <x-card>
                <x-slot:header>
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-display font-semibold">Work inbox</h2>
                        <a href="{{ route('caregiver.work-inbox.index') }}" wire:navigate class="hc-link">Open full inbox</a>
                    </div>
                </x-slot:header>
                <div class="space-y-3">
                    @php
                        $previewItems = $caregiverData['work_inbox_preview'] ?? collect();
                        $inboxStatusStyles = [
                            'success' => 'bg-emerald-100 text-emerald-700',
                            'warning' => 'bg-amber-100 text-amber-800',
                            'danger' => 'bg-rose-100 text-rose-700',
                            'info' => 'bg-sky-100 text-sky-700',
                            'neutral' => 'bg-slate-100 text-slate-700',
                        ];
                    @endphp

                    @forelse ($previewItems as $item)
                        <div class="rounded-xl border border-slate-200 bg-white p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $item['title'] }}</p>
                                    <p class="text-xs text-slate-500 mt-1">{{ $item['location'] }} - {{ $item['schedule'] }}</p>
                                    @if (!empty($item['recipient_context']))
                                        <x-care-recipient-context :context="$item['recipient_context']" :show-name="true" class="mt-2" />
                                    @endif
                                    <p class="text-xs text-slate-600 mt-1">{{ $item['fit_reason'] }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $inboxStatusStyles[$item['status_tone'] ?? 'neutral'] ?? $inboxStatusStyles['neutral'] }}">
                                    {{ strtoupper((string) $item['status_label']) }}
                                </span>
                            </div>

                            <div class="mt-3 flex items-center gap-2">
                                @if (($item['primary_action']['kind'] ?? null) === 'link')
                                    <a href="{{ $item['primary_action']['href'] }}" wire:navigate>
                                        <x-button color="blue" light sm>{{ $item['primary_action']['label'] }}</x-button>
                                    </a>
                                @else
                                    <a href="{{ route('caregiver.work-inbox.index') }}" wire:navigate>
                                        <x-button color="blue" light sm>Respond now</x-button>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-600">
                            No active inbox items right now.
                        </div>
                    @endforelse
                </div>
            </x-card>

            <section class="space-y-4">
                @if (!empty($caregiverData['active_shift']))
                    <div class="rounded-[1.4rem] border border-[#CFE1D8] bg-[#F2F8F4] p-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.14em] text-emerald-700 font-semibold">Live now</p>
                            <p class="font-semibold text-emerald-900">{{ $caregiverData['active_shift']->careRequest?->title ?? 'Current visit' }}</p>
                            <p class="text-sm text-emerald-800 mt-1">
                                {{ $caregiverData['active_shift']->status === \App\Models\CareBooking::STATUS_PAUSED ? 'Paused' : 'Started' }}
                                {{ optional($caregiverData['active_shift']->started_at)->format('M d, H:i') ?: 'just now' }}
                                · {{ $caregiverData['active_shift']->careRequest?->city }}, {{ $caregiverData['active_shift']->careRequest?->state }}
                            </p>
                        </div>
                        @if ($caregiverData['active_shift']->careRequest)
                            <a href="{{ route('care-requests.apply', $caregiverData['active_shift']->careRequest->id) }}" wire:navigate>
                                <x-button color="green">{{ $caregiverData['active_shift']->status === \App\Models\CareBooking::STATUS_PAUSED ? 'Resume visit' : 'Continue visit' }}</x-button>
                            </a>
                        @endif
                    </div>
                @elseif (!empty($caregiverData['next_shift']))
                    <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.14em] text-sky-700 font-semibold">Next visit</p>
                            <p class="font-semibold text-sky-900">{{ $caregiverData['next_shift']->careRequest?->title ?? 'Upcoming visit' }}</p>
                            <p class="text-sm text-sky-800 mt-1">
                                {{ optional($caregiverData['next_shift']->scheduled_start_at)->format('M d, Y H:i') ?: 'Date pending' }}
                                · {{ $caregiverData['next_shift']->careRequest?->city }}, {{ $caregiverData['next_shift']->careRequest?->state }}
                            </p>
                        </div>
                        @if ($caregiverData['next_shift']->careRequest)
                            <a href="{{ route('care-requests.apply', $caregiverData['next_shift']->careRequest->id) }}" wire:navigate>
                                <x-button color="blue">Open visit details</x-button>
                            </a>
                        @endif
                    </div>
                @endif

                @if (!empty($caregiverData['quick_shifts']) && $caregiverData['quick_shifts']->count() > 0)
                    <x-card>
                        <x-slot:header>
                            <div class="flex items-center justify-between">
                                <h2 class="font-display font-semibold">Visit quick access</h2>
                                <a href="{{ route('caregiver.shifts.index') }}" wire:navigate class="hc-link">View all visits</a>
                            </div>
                        </x-slot:header>
                        <div class="space-y-3">
                            @foreach ($caregiverData['quick_shifts'] as $shift)
                                @php
                                    $shiftStatus = (string) $shift->status;
                                    $shiftCta = match ($shiftStatus) {
                                        \App\Models\CareBooking::STATUS_IN_PROGRESS => 'Continue visit',
                                        \App\Models\CareBooking::STATUS_PAUSED => 'Resume visit',
                                        \App\Models\CareBooking::STATUS_SCHEDULED => 'Start visit',
                                        \App\Models\CareBooking::STATUS_COMPLETED => 'View recap',
                                        default => 'Open visit',
                                    };
                                @endphp
                                <div class="rounded-xl border border-slate-200 bg-white p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-medium text-slate-900">{{ $shift->careRequest?->title ?? 'Care request' }}</p>
                                            <p class="text-xs text-slate-500 mt-1">
                                                {{ optional($shift->scheduled_start_at)->format('M d, H:i') ?: 'Date pending' }}
                                                · {{ $shift->careRequest?->city }}, {{ $shift->careRequest?->state }}
                                            </p>
                                        </div>
                                        <x-badge :text="strtoupper($shiftStatus)" color="{{ in_array($shiftStatus, [\App\Models\CareBooking::STATUS_IN_PROGRESS, \App\Models\CareBooking::STATUS_PAUSED], true) ? 'green' : 'blue' }}" />
                                    </div>
                                    @if ($shift->careRequest)
                                        <div class="mt-3">
                                            <a href="{{ route('care-requests.apply', $shift->careRequest->id) }}" wire:navigate>
                                                <x-button color="{{ in_array($shiftStatus, [\App\Models\CareBooking::STATUS_IN_PROGRESS, \App\Models\CareBooking::STATUS_PAUSED], true) ? 'green' : 'blue' }}" light sm>{{ $shiftCta }}</x-button>
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </x-card>
                @endif
            </section>

            <section class="space-y-4">
                @if ($canSubmitForReview)
                    <div class="rounded-[1.4rem] border border-[#D8D1F1] bg-[#F5F1FB] p-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-cyan-900">Ready to submit.</p>
                            <p class="text-sm text-cyan-800">Open the review step, confirm your details, and submit now.</p>
                        </div>
                        <a href="{{ route('caregiver.onboarding', ['step' => 4]) }}" wire:navigate>
                            <x-button color="blue" sm>Submit profile for review</x-button>
                        </a>
                    </div>
                @elseif ($readyForReview)
                    <div class="rounded-[1.4rem] border border-[#CFE1D8] bg-[#F2F8F4] p-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-emerald-900">Setup complete.</p>
                            <p class="text-sm text-emerald-800">Your profile is already submitted or active.</p>
                        </div>
                        <x-badge color="green" text="{{ strtoupper(str_replace('_', ' ', (string) ($profile?->status ?? 'draft'))) }}" />
                    </div>
                @endif

                @if (count($setupCards) > 0)
                    <details class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:hidden">
                        <summary class="cursor-pointer list-none">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-display text-lg font-semibold text-slate-900">Optional profile boosters</p>
                                    <p class="mt-1 text-sm text-slate-600">Nice to have, but not required to start getting booked.</p>
                                </div>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700">{{ count($setupCards) }} left</span>
                            </div>
                        </summary>
                        <div class="mt-4 grid grid-cols-1 gap-4">
                            @foreach ($setupCards as $card)
                                <div class="rounded-2xl border {{ $card['required'] ? 'border-amber-200 bg-amber-50/60' : 'border-slate-200 bg-white' }} p-4 shadow-sm">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs uppercase tracking-[0.12em] {{ $card['required'] ? 'text-slate-600' : 'text-slate-500' }}">
                                                {{ $card['required'] ? 'Required' : 'Optional' }}
                                            </p>
                                            <h3 class="mt-1 font-display text-lg font-semibold text-slate-900">{{ $card['title'] }}</h3>
                                            <p class="mt-1 text-sm text-slate-600">{{ $card['description'] }}</p>
                                        </div>
                                        <x-badge :color="$card['required'] ? 'amber' : 'slate'" text="PENDING" />
                                    </div>
                                    <div class="mt-4">
                                        <a href="{{ $card['route'] }}" wire:navigate>
                                            <x-button color="blue" light sm class="w-full">{{ $card['cta'] }}</x-button>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </details>

                    <div class="hidden lg:grid grid-cols-1 gap-4 lg:grid-cols-2">
                        @foreach ($setupCards as $card)
                            <div class="rounded-2xl border {{ $card['required'] ? 'border-amber-200 bg-amber-50/60' : 'border-slate-200 bg-white' }} p-4 shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs uppercase tracking-[0.12em] {{ $card['required'] ? 'text-slate-600' : 'text-slate-500' }}">
                                            {{ $card['required'] ? 'Required' : 'Optional' }}
                                        </p>
                                        <h3 class="mt-1 font-display text-lg font-semibold text-slate-900">{{ $card['title'] }}</h3>
                                        <p class="mt-1 text-sm text-slate-600">{{ $card['description'] }}</p>
                                    </div>
                                    <x-badge :color="$card['required'] ? 'amber' : 'slate'" text="PENDING" />
                                </div>
                                <div class="mt-4">
                                    <a href="{{ $card['route'] }}" wire:navigate>
                                        <x-button color="blue" light sm>{{ $card['cta'] }}</x-button>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-[1.4rem] border border-[#CFE1D8] bg-[#F2F8F4] p-4">
                        <p class="font-semibold text-emerald-900">All setup cards are complete.</p>
                        <p class="text-sm text-emerald-800 mt-1">If your profile is still draft, open the review step and submit.</p>
                    </div>
                @endif
            </section>

            @if (!empty($caregiverData['profile']))
                <section class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-xs text-slate-500">Reliability score</p>
                        <p class="text-xl font-semibold text-slate-900">{{ number_format((float) $caregiverData['profile']->reliability_score, 0) }}%</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-xs text-slate-500">Completed shifts</p>
                        <p class="text-xl font-semibold text-slate-900">{{ (int) $caregiverData['profile']->completed_bookings_count }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-xs text-slate-500">Hired</p>
                        <p class="text-xl font-semibold text-slate-900">{{ $caregiverData['stats']['hired'] }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-xs text-slate-500">Unread messages</p>
                        <p class="text-xl font-semibold text-slate-900">{{ $caregiverData['stats']['unread_messages'] }}</p>
                    </div>
                </section>
            @endif
        @else
            <x-card>
                <h1 class="text-xl font-semibold">Dashboard</h1>
                <p class="text-sm text-slate-600 mt-2">Your account is active. Use the navigation to continue.</p>
            </x-card>
        @endif
    </div>
</div>

