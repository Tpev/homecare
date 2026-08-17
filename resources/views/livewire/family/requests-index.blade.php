<div>
    <div class="hc-page space-y-5 py-5 sm:space-y-6 sm:py-8">
        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        @php
            $carePlans = $carePlans ?? collect();
            $familyActions = $familyActions ?? collect();
            $rebookableRequests = $rebookableRequests ?? collect();
            $upcomingRequests = $requests->getCollection()->filter(function ($request) {
                return (string) ($request->booking?->status ?? '') === \App\Models\CareBooking::STATUS_SCHEDULED
                    && ! ($request->booking?->payment?->requiresFamilyAction() ?? false);
            })->sortBy(fn ($request) => optional($request->booking?->scheduled_start_at)->timestamp ?? PHP_INT_MAX)->values();
        @endphp

        <section data-ai-target="family.care_requests" tabindex="-1" class="rounded-3xl border border-[#E4DDD3] bg-[#FFFCF8] p-4 shadow-sm outline-none sm:p-6">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-center">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#C96B55]">Care</p>
                    <h1 class="mt-2 max-w-3xl text-3xl font-display font-semibold leading-tight text-[#17313F] sm:text-4xl">
                        Your care, visits, and caregivers in one place.
                    </h1>
                    <p class="mt-3 max-w-2xl text-base leading-7 text-[#4B5B6B]">
                        Open requests, upcoming visits, weekly care, and rebooking all live here. Start with the item that needs attention.
                    </p>
                    <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                        <a href="{{ route('family.requests.create') }}" wire:navigate class="hc-primary-button w-full sm:w-auto">Get care</a>
                        <a href="{{ route('family.care.history') }}" wire:navigate class="hc-secondary-button w-full sm:w-auto">Care history</a>
                        <a href="{{ route('caregivers.search') }}" wire:navigate class="hc-secondary-button w-full sm:w-auto">Find caregivers</a>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-2xl border border-[#E4DDD3] bg-white p-4 text-center">
                        <p class="text-3xl font-semibold text-[#17313F]">{{ (int) ($attentionCount ?? 0) }}</p>
                        <p class="mt-1 text-xs text-[#607080]">need action</p>
                    </div>
                    <div class="rounded-2xl border border-[#E4DDD3] bg-white p-4 text-center">
                        <p class="text-3xl font-semibold text-[#17313F]">{{ $carePlans->count() }}</p>
                        <p class="mt-1 text-xs text-[#607080]">weekly</p>
                    </div>
                    <div class="rounded-2xl border border-[#E4DDD3] bg-white p-4 text-center">
                        <p class="text-3xl font-semibold text-[#17313F]">{{ $avgFirstResponseLabel ?? '-' }}</p>
                        <p class="mt-1 text-xs text-[#607080]">avg reply</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="space-y-5">
                <x-card>
                    <x-slot:header>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="font-display text-xl font-semibold">Needs your action</h2>
                                <p class="text-sm text-[#607080]">Care items where you need to decide, approve, or reply.</p>
                            </div>
                        </div>
                    </x-slot:header>

                    <div class="space-y-3">
                        @foreach ($familyActions as $action)
                            <x-family-action-card :item="$action" />
                        @endforeach

                        @if ($familyActions->isEmpty())
                            <div class="rounded-2xl border border-[#D8E1D7] bg-[#F2F8F4] p-5">
                                <p class="font-display text-lg font-semibold text-[#17313F]">Nothing urgent right now.</p>
                                <p class="mt-1 text-sm text-[#607080]">You can start new care, rebook a trusted caregiver, or browse all care below.</p>
                            </div>
                        @endif
                    </div>
                </x-card>

                @if ($upcomingRequests->isNotEmpty())
                    <x-card>
                        <x-slot:header>
                            <div>
                                <h2 class="font-display text-xl font-semibold">Upcoming visits</h2>
                                <p class="text-sm text-[#607080]">Scheduled care that is already set. No decision needed unless something changed.</p>
                            </div>
                        </x-slot:header>

                        <div class="space-y-3">
                            @foreach ($upcomingRequests->take(4) as $request)
                                <a href="{{ route('family.requests.show', $request->id) }}" wire:navigate class="block rounded-2xl border border-[#D8E1D7] bg-[#F2F8F4] p-4 transition hover:shadow-sm">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="font-display text-lg font-semibold text-[#17313F]">{{ $request->title }}</p>
                                            <p class="mt-1 text-sm text-[#4B5B6B]">
                                                {{ optional($request->booking?->scheduled_start_at)->format('M d, g:i A') ?: 'Time pending' }}
                                                @if ($request->booking?->scheduled_end_at)
                                                    to {{ $request->booking->scheduled_end_at->format('g:i A') }}
                                                @endif
                                            </p>
                                            @if ($request->booking?->caregiver)
                                                <x-caregiver-identity :caregiver="$request->booking->caregiver" class="mt-3" />
                                            @endif
                                        </div>
                                        <span class="inline-flex min-h-11 items-center justify-center rounded-xl bg-white px-4 text-sm font-semibold text-[#23483F]">Open visit</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </x-card>
                @endif

                <x-card>
                    <x-slot:header>
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <h2 class="font-display text-xl font-semibold">All care</h2>
                                <p class="text-sm text-[#607080]">Requests and visits, from newest to oldest.</p>
                            </div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:min-w-[620px]">
                                <x-native-select-field label="Status" wire:model.live="status" :options="$statusOptions" />
                                <x-native-select-field label="Type" wire:model.live="requestType" :options="$requestTypeOptions" />
                                <x-native-select-field label="Sort" wire:model.live="sort" :options="$sortOptions" />
                            </div>
                        </div>
                    </x-slot:header>

                    <div class="space-y-3">
                        @forelse ($requests as $request)
                            @php
                                $nextAction = \App\Support\CareRequestProgress::bestNextAction($request);
                                $bookingStatus = (string) ($request->booking?->status ?? '');
                                $paymentNeedsAction = $request->booking?->payment?->requiresFamilyAction() ?? false;
                                if ($paymentNeedsAction) {
                                    $nextAction = [
                                        'title' => 'Payment needs attention',
                                        'action' => 'Confirm or replace your card for this visit.',
                                    ];
                                }
                                $statusLabel = $request->booking
                                    ? 'Visit '.str_replace('_', ' ', $bookingStatus)
                                    : match ((string) $request->status) {
                                        \App\Models\CareRequest::STATUS_FILLED => 'Visit scheduled',
                                        \App\Models\CareRequest::STATUS_CANCELLED => 'Withdrawn',
                                        default => ucfirst((string) $request->status),
                                    };
                                $scheduleLabel = $request->request_type === \App\Models\CareRequest::TYPE_ONE_TIME
                                    ? (optional($request->requested_start_at)->format('M d, g:i A') ?: 'Time not set')
                                    : 'Repeats every week';
                                $cardActionLabel = match (true) {
                                    $paymentNeedsAction => 'Fix payment',
                                    $bookingStatus === \App\Models\CareBooking::STATUS_COMPLETED => 'Review hours',
                                    in_array($bookingStatus, [\App\Models\CareBooking::STATUS_SCHEDULED, \App\Models\CareBooking::STATUS_IN_PROGRESS, \App\Models\CareBooking::STATUS_PAUSED], true) => 'Open visit',
                                    $request->status === \App\Models\CareRequest::STATUS_OPEN && (int) $request->applications_count > 0 => 'Review caregivers',
                                    $request->status === \App\Models\CareRequest::STATUS_OPEN => 'View request',
                                    default => 'View details',
                                };
                                $canBookAgain = $request->booking
                                    && in_array($bookingStatus, [
                                        \App\Models\CareBooking::STATUS_COMPLETED,
                                        \App\Models\CareBooking::STATUS_REVIEWED,
                                    ], true)
                                    && $request->booking?->family_confirmed_at;
                            @endphp

                            <article class="rounded-2xl border border-[#E4DDD3] bg-white p-4 shadow-sm">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="font-display text-xl font-semibold text-[#17313F]">{{ $request->title }}</h3>
                                            <span class="rounded-full bg-[#F5F1EB] px-3 py-1 text-xs font-semibold text-[#4B5B6B]">{{ $statusLabel }}</span>
                                            @if ($paymentNeedsAction)
                                                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Payment needs attention</span>
                                            @endif
                                        </div>
                                        <p class="mt-2 text-sm text-[#607080]">{{ $scheduleLabel }} - {{ $request->city }}, {{ $request->state }}</p>
                                        <p class="mt-1 text-sm text-[#607080]">For {{ $request->recipient?->full_name ?? 'care recipient' }}</p>
                                        @if ($request->booking?->caregiver)
                                            <x-caregiver-identity :caregiver="$request->booking->caregiver" class="mt-3" />
                                        @endif
                                        <p class="mt-3 text-sm text-[#324457]">
                                            <span class="font-semibold">{{ $nextAction['title'] }}</span>
                                            <span class="text-[#607080]"> - {{ $nextAction['action'] }}</span>
                                        </p>
                                    </div>

                                    <div class="flex flex-col gap-2 sm:flex-row lg:flex-col lg:items-stretch">
                                        <a href="{{ route('family.requests.show', $request->id) }}" wire:navigate class="hc-primary-button w-full lg:w-44">{{ $cardActionLabel }}</a>
                                        @if ($canBookAgain)
                                            <a href="{{ route('family.requests.book_again', $request->id) }}" wire:navigate class="hc-secondary-button w-full lg:w-44">Book again</a>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="rounded-2xl border border-dashed border-[#D6CCBE] bg-[#F7F2EA] px-4 py-8 text-center">
                                <p class="font-display text-xl font-semibold text-[#17313F]">No care yet.</p>
                                <p class="mx-auto mt-2 max-w-xl text-sm text-[#607080]">Start with a simple request. You can choose the caregiver after replies arrive.</p>
                                <div class="mt-5">
                                    <a href="{{ route('family.requests.create') }}" wire:navigate class="hc-primary-button">Get care</a>
                                </div>
                            </div>
                        @endforelse
                    </div>

                    <div class="mt-5">{{ $requests->links() }}</div>
                </x-card>
            </div>

            <aside class="space-y-5">
                <x-card>
                    <x-slot:header>
                        <h2 class="font-display text-xl font-semibold">Book the same caregiver again</h2>
                    </x-slot:header>

                    <div class="space-y-3">
                        @forelse ($rebookableRequests as $request)
                            @php
                                $hired = $request->applications->first();
                                $caregiverName = trim((string) ($hired?->caregiver?->name ?? ''));
                                $caregiverFirstName = $caregiverName !== ''
                                    ? \Illuminate\Support\Str::of($caregiverName)->before(' ')
                                    : 'caregiver';
                            @endphp
                            <div class="rounded-2xl border border-[#E4DDD3] bg-white p-4">
                                <p class="font-display text-lg font-semibold text-[#17313F]">{{ $hired?->caregiver?->name ?: 'Hired caregiver' }}</p>
                                <p class="mt-1 text-sm text-[#607080]">{{ $request->title }}</p>
                                <p class="mt-1 text-xs text-[#7B8794]">{{ optional($request->booking?->scheduled_start_at)->format('M d, Y') ?: 'Recent care' }}</p>
                                <div class="mt-3">
                                    <a href="{{ route('family.requests.book_again', $request->id) }}" wire:navigate class="hc-secondary-button w-full">Book {{ $caregiverFirstName }} again</a>
                                </div>
                            </div>
                        @empty
                            <p class="rounded-2xl border border-dashed border-[#D6CCBE] p-4 text-sm text-[#607080]">Trusted caregivers appear here after you schedule or complete a visit.</p>
                        @endforelse
                    </div>
                </x-card>

                <x-card>
                    <x-slot:header>
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="font-display text-xl font-semibold">Weekly care</h2>
                            <a href="{{ route('family.care.index') }}" wire:navigate class="hc-link">Manage</a>
                        </div>
                    </x-slot:header>

                    <div class="space-y-3">
                        @forelse ($carePlans as $plan)
                            @php
                                $planStatusLabel = $plan->status === \App\Models\CarePlan::STATUS_PAYMENT_ATTENTION
                                    ? 'Payment needs attention'
                                    : ucfirst(str_replace('_', ' ', (string) $plan->status));
                                $planNeedsPayment = $plan->status === \App\Models\CarePlan::STATUS_PAYMENT_ATTENTION;
                                $planHref = $planNeedsPayment && $plan->nextBooking
                                    ? route('family.requests.show', $plan->nextBooking->care_request_id)
                                    : route('family.care.show', $plan->id);
                            @endphp
                            <a href="{{ $planHref }}" wire:navigate class="block rounded-2xl border p-4 transition {{ $planNeedsPayment ? 'border-amber-300 bg-amber-50 hover:bg-amber-100/70' : 'border-[#E4DDD3] bg-white hover:bg-[#F7F2EA]' }}">
                                <p class="font-display text-lg font-semibold text-[#17313F]">{{ $plan->title }}</p>
                                <p class="mt-1 text-sm text-[#607080]">{{ $plan->caregiver?->name ?: 'Caregiver' }}</p>
                                <p class="mt-2 text-sm font-semibold {{ $planNeedsPayment ? 'text-amber-900' : 'text-[#4B5B6B]' }}">{{ $planStatusLabel }}</p>
                                @if ($planNeedsPayment)
                                    <p class="mt-2 text-sm font-semibold text-amber-900 underline underline-offset-2">Fix payment</p>
                                @endif
                            </a>
                        @empty
                            <div class="rounded-2xl border border-dashed border-[#D6CCBE] p-4 text-sm text-[#607080]">
                                No weekly care plan yet. Use "Book again" after an approved visit, then choose weekly care.
                            </div>
                        @endforelse
                    </div>
                </x-card>
            </aside>
        </section>
    </div>
</div>
