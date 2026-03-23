<div>
    <div class="hc-page py-8 space-y-6">
        @if ($mode === 'family')
            @php
                $focusRequests = $familyData['focus_requests'] ?? collect();
                $needsApplicants = $focusRequests->filter(function ($request) {
                    return $request->status === \App\Models\CareRequest::STATUS_OPEN
                        && (int) ($request->pending_candidate_count ?? 0) === 0;
                })->values();
                $readyToReview = $focusRequests->filter(function ($request) {
                    return $request->status === \App\Models\CareRequest::STATUS_OPEN
                        && (int) ($request->pending_candidate_count ?? 0) > 0;
                })->values();
                $activeShifts = $familyData['active_shifts'] ?? collect();
                $billingReady = (bool) ($familyData['billing_ready'] ?? false);
                $urgentOpenRequests = (int) ($familyData['urgent_open_requests'] ?? 0);
                $recentApplicants = $familyData['recent_applicants'] ?? collect();
                $hasAnyPipelineData = $focusRequests->count() > 0 || $activeShifts->count() > 0 || $recentApplicants->count() > 0;

                $nextActionTitle = 'Post your first request';
                $nextActionDescription = 'Use Fast Track to post in minutes. You can edit details any time.';
                $nextActionRoute = route('family.requests.create');
                $nextActionLabel = 'Post request';

                if (! $billingReady) {
                    $nextActionTitle = 'Add payment method';
                    $nextActionDescription = 'Payment is secured before shift start so hiring is instant when you choose a caregiver.';
                    $nextActionRoute = route('family.billing.show');
                    $nextActionLabel = 'Set up billing';
                }

                if ($focusRequests->count() > 0 || $activeShifts->count() > 0) {
                    $nextActionTitle = 'Manage your active requests';
                    $nextActionDescription = 'Review candidates, chat, and move to hire quickly.';
                    $nextActionRoute = route('family.requests.index');
                    $nextActionLabel = 'Open my requests';
                }

                if ($needsApplicants->count() > 0) {
                    $nextActionTitle = 'Invite caregivers to get faster replies';
                    $nextActionDescription = $needsApplicants->count().' request(s) are still waiting for applicants.';
                    $nextActionRoute = route('caregivers.search');
                    $nextActionLabel = 'Find caregivers';
                }

                if (($familyData['stats']['unread_messages'] ?? 0) > 0) {
                    $nextActionTitle = 'Reply to caregiver messages';
                    $nextActionDescription = $familyData['stats']['unread_messages'].' unread conversation(s) need your response.';
                    $nextActionRoute = route('messages.index');
                    $nextActionLabel = 'Open messages';
                }

                if ($readyToReview->count() > 0) {
                    $reviewRequest = $readyToReview->first();
                    $nextActionTitle = 'Review applicants and hire';
                    $nextActionDescription = $readyToReview->count().' request(s) have candidates waiting for your decision.';
                    $nextActionRoute = $reviewRequest ? route('family.requests.show', $reviewRequest->id) : route('family.requests.index');
                    $nextActionLabel = 'Review now';
                }

                if ($activeShifts->count() > 0) {
                    $activeRequest = $activeShifts->first();
                    $nextActionTitle = 'Track your active shift';
                    $nextActionDescription = 'Follow status, confirm timesheet, and leave a review when complete.';
                    $nextActionRoute = $activeRequest ? route('family.requests.show', $activeRequest->id) : route('family.requests.index');
                    $nextActionLabel = 'Open shift';
                }

                $journeySteps = [
                    ['label' => 'Post request', 'done' => $focusRequests->count() > 0 || $activeShifts->count() > 0],
                    ['label' => 'Get applicants', 'done' => $recentApplicants->count() > 0 || $readyToReview->count() > 0],
                    ['label' => 'Chat and hire', 'done' => $focusRequests->where('status', \App\Models\CareRequest::STATUS_FILLED)->count() > 0 || $activeShifts->count() > 0],
                    ['label' => 'Complete and review', 'done' => $focusRequests->filter(fn ($request) => in_array((string) ($request->booking?->status ?? ''), [\App\Models\CareBooking::STATUS_COMPLETED, \App\Models\CareBooking::STATUS_REVIEWED], true))->count() > 0],
                ];
            @endphp

            <section class="relative overflow-hidden rounded-3xl border border-slate-900/80 bg-slate-950 p-5 text-white shadow-xl">
                <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-cyan-500/20 blur-2xl"></div>
                <div class="pointer-events-none absolute -left-10 -bottom-14 h-40 w-40 rounded-full bg-emerald-500/20 blur-2xl"></div>

                <div class="relative mt-3 grid grid-cols-1 gap-5 lg:grid-cols-5">
                    <div class="lg:col-span-3">
                        <p class="text-[11px] uppercase tracking-[0.18em] text-slate-300">Family Dashboard</p>
                        <h1 class="mt-1 text-2xl font-display font-semibold leading-tight sm:text-3xl">{{ $nextActionTitle }}</h1>
                        <p class="mt-2 text-sm text-slate-300 max-w-2xl">{{ $nextActionDescription }}</p>
                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <a href="{{ $nextActionRoute }}" wire:navigate><x-button color="white">{{ $nextActionLabel }}</x-button></a>
                            <a href="{{ route('family.requests.create') }}" wire:navigate><x-button color="white" light>Post another request</x-button></a>
                        </div>
                        @unless($billingReady)
                            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                                Add a payment method now so hiring is instant when you find the right caregiver.
                                <a href="{{ route('family.billing.show') }}" wire:navigate class="ml-1 font-semibold underline underline-offset-2">Open billing</a>
                            </div>
                        @endunless
                    </div>
                    <div class="lg:col-span-2 grid grid-cols-2 gap-3">
                        <div class="col-span-2 rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-slate-300">How it works</p>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                @foreach ($journeySteps as $index => $journeyStep)
                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-semibold {{ $journeyStep['done'] ? 'bg-emerald-300 text-emerald-950' : 'bg-white/20 text-white' }}">{{ $index + 1 }}</span>
                                        <span class="{{ $journeyStep['done'] ? 'text-emerald-100' : 'text-slate-200' }}">{{ $journeyStep['label'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-slate-300">Ready to review</p>
                            <p class="mt-1 text-2xl font-semibold">{{ $familyData['stats']['ready_to_review'] }}</p>
                        </div>
                        <div class="rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-slate-300">Waiting applicants</p>
                            <p class="mt-1 text-2xl font-semibold">{{ $familyData['stats']['waiting_for_applicants'] }}</p>
                        </div>
                        <div class="rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-slate-300">Active shifts</p>
                            <p class="mt-1 text-2xl font-semibold">{{ $familyData['stats']['active_shifts'] }}</p>
                        </div>
                        <div class="rounded-xl border border-white/25 bg-white/10 p-3 backdrop-blur-sm">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-slate-300">Unread messages</p>
                            <p class="mt-1 text-2xl font-semibold">{{ $familyData['stats']['unread_messages'] }}</p>
                        </div>
                    </div>
                </div>
            </section>

            @php
                $familyDigest = $familyData['notification_digest'] ?? collect();
                $digestToneStyles = [
                    'success' => 'bg-emerald-100 text-emerald-700',
                    'warning' => 'bg-amber-100 text-amber-800',
                    'danger' => 'bg-rose-100 text-rose-700',
                    'info' => 'bg-sky-100 text-sky-700',
                    'neutral' => 'bg-slate-100 text-slate-700',
                ];
            @endphp

            @if ($familyDigest->count() > 0)
                <x-card>
                    <x-slot:header>
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="font-display font-semibold">Top unread updates</h2>
                            <a href="{{ route('family.notifications.index') }}" wire:navigate class="hc-link">See all</a>
                        </div>
                    </x-slot:header>
                    <div class="space-y-2">
                        @foreach ($familyDigest as $digest)
                            <a href="{{ $digest['url'] ?: route('family.notifications.index') }}" wire:navigate class="block rounded-xl border border-slate-200 bg-white p-3 transition hover:border-slate-300 hover:bg-slate-50">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $digestToneStyles[$digest['tone'] ?? 'neutral'] ?? $digestToneStyles['neutral'] }}">
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

            @if (! $hasAnyPipelineData)
                <x-card>
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                        <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Get started</p>
                        <h2 class="mt-2 font-display text-2xl font-semibold text-slate-900">Post your first care request in minutes</h2>
                        <p class="mx-auto mt-2 max-w-2xl text-sm text-slate-600">
                            Start with Fast Track: schedule, address, services, and recipient name. Then review applicants, chat, and hire.
                        </p>
                        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                            <a href="{{ route('family.requests.create') }}" wire:navigate><x-button color="blue">Start fast request</x-button></a>
                            <a href="{{ route('caregivers.search') }}" wire:navigate><x-button color="slate" light>Browse caregivers</x-button></a>
                        </div>
                    </div>
                </x-card>
            @else
            <section class="grid grid-cols-1 gap-6 xl:grid-cols-12">
                <div class="xl:col-span-8 space-y-6">
                    <x-card>
                        <x-slot:header>
                            <div class="flex items-center justify-between">
                                <h2 class="font-display font-semibold">Request pipeline</h2>
                                <a href="{{ route('family.requests.index') }}" wire:navigate class="hc-link">View all requests</a>
                            </div>
                        </x-slot:header>

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs uppercase tracking-[0.14em] text-amber-700 font-semibold">Needs applicants</p>
                                    <span class="text-sm font-semibold text-amber-900">{{ $needsApplicants->count() }}</span>
                                </div>
                                <div class="mt-3 space-y-2">
                                    @forelse ($needsApplicants->take(3) as $request)
                                        <div class="rounded-lg border border-amber-200 bg-white px-3 py-2">
                                            <p class="font-medium text-slate-900 text-sm">{{ $request->title }}</p>
                                            <p class="text-xs text-slate-500 mt-1">{{ $request->city }}, {{ $request->state }}</p>
                                            <div class="mt-2 flex items-center justify-between">
                                                <a href="{{ route('family.requests.show', $request->id) }}" wire:navigate class="text-xs font-medium text-cyan-700 underline underline-offset-2">Open</a>
                                                <a href="{{ route('caregivers.search') }}" wire:navigate class="text-xs font-medium text-cyan-700 underline underline-offset-2">Find</a>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-xs text-amber-800">No requests currently blocked on applicants.</p>
                                    @endforelse
                                </div>
                            </div>

                            <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs uppercase tracking-[0.14em] text-sky-700 font-semibold">Ready to review</p>
                                    <span class="text-sm font-semibold text-sky-900">{{ $readyToReview->count() }}</span>
                                </div>
                                <div class="mt-3 space-y-2">
                                    @forelse ($readyToReview->take(3) as $request)
                                        <div class="rounded-lg border border-sky-200 bg-white px-3 py-2">
                                            <p class="font-medium text-slate-900 text-sm">{{ $request->title }}</p>
                                            <p class="text-xs text-slate-500 mt-1">{{ (int) ($request->pending_candidate_count ?? 0) }} candidate(s) waiting</p>
                                            <div class="mt-2 flex items-center justify-between">
                                                <p class="text-xs text-slate-500">{{ $request->city }}, {{ $request->state }}</p>
                                                <a href="{{ route('family.requests.show', $request->id) }}" wire:navigate class="text-xs font-medium text-indigo-700 underline underline-offset-2">Review</a>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-xs text-sky-800">No requests waiting for candidate review.</p>
                                    @endforelse
                                </div>
                            </div>

                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs uppercase tracking-[0.14em] text-emerald-700 font-semibold">Active shifts</p>
                                    <span class="text-sm font-semibold text-emerald-900">{{ $activeShifts->count() }}</span>
                                </div>
                                <div class="mt-3 space-y-2">
                                    @forelse ($activeShifts->take(3) as $request)
                                        <div class="rounded-lg border border-emerald-200 bg-white px-3 py-2">
                                            <p class="font-medium text-slate-900 text-sm">{{ $request->title }}</p>
                                            <p class="text-xs text-slate-500 mt-1">Shift {{ strtoupper($request->booking?->status ?? 'n/a') }}</p>
                                            <div class="mt-2 flex items-center justify-between">
                                                <p class="text-xs text-slate-500">{{ optional($request->booking?->scheduled_start_at)->format('M d, H:i') ?: '-' }}</p>
                                                <a href="{{ route('family.requests.show', $request->id) }}" wire:navigate class="text-xs font-medium text-emerald-700 underline underline-offset-2">Manage</a>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-xs text-emerald-800">No active shifts right now.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </x-card>

                    <x-card>
                        <x-slot:header>
                            <div class="flex items-center justify-between">
                                <h2 class="font-display font-semibold">Recent applicant activity</h2>
                                <a href="{{ route('messages.index') }}" wire:navigate class="hc-link">Open messages</a>
                            </div>
                        </x-slot:header>

                        <div class="space-y-3">
                            @forelse ($recentApplicants as $application)
                                <div class="rounded-xl border border-slate-200 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-semibold text-slate-900">{{ $application->caregiver?->name }}</p>
                                            <p class="text-sm text-slate-600">{{ $application->careRequest?->title }}</p>
                                        </div>
                                        <x-badge :text="strtoupper($application->status)" color="blue" />
                                    </div>
                                    <div class="mt-2 flex items-center justify-between">
                                        <p class="text-xs text-slate-500">${{ number_format((float) $application->proposed_rate, 2) }}/hr</p>
                                        <div class="flex items-center gap-2">
                                            @if ($application->conversation)
                                                <a href="{{ route('messages.show', $application->conversation->id) }}" wire:navigate class="text-xs font-medium text-indigo-700 underline underline-offset-2">Chat</a>
                                            @endif
                                            @if ($application->careRequest)
                                                <a href="{{ route('family.requests.show', $application->careRequest->id) }}" wire:navigate class="text-xs font-medium text-cyan-700 underline underline-offset-2">Review</a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-slate-600">No applicant activity yet.</p>
                            @endforelse
                        </div>
                    </x-card>
                </div>

                <div class="xl:col-span-4 space-y-6">
                    <x-card>
                        <x-slot:header><h2 class="font-display font-semibold">What to do now</h2></x-slot:header>
                        <div class="space-y-3">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs uppercase tracking-[0.14em] text-slate-500">Recommended</p>
                                <p class="mt-2 text-sm text-slate-600">{{ $nextActionDescription }}</p>
                                <div class="mt-3">
                                    <a href="{{ $nextActionRoute }}" wire:navigate><x-button color="blue">{{ $nextActionLabel }}</x-button></a>
                                </div>
                            </div>
                            <div class="space-y-2 text-sm">
                                <a href="{{ route('family.requests.create') }}" wire:navigate class="block font-medium text-cyan-700 underline underline-offset-2">Post with Fast Track</a>
                                <a href="{{ route('caregivers.search') }}" wire:navigate class="block font-medium text-cyan-700 underline underline-offset-2">Invite caregivers directly</a>
                                <a href="{{ route('messages.index') }}" wire:navigate class="block font-medium text-cyan-700 underline underline-offset-2">Open messages</a>
                                <a href="{{ route('family.billing.show') }}" wire:navigate class="block font-medium {{ $billingReady ? 'text-emerald-700' : 'text-amber-700' }} underline underline-offset-2">
                                    {{ $billingReady ? 'Billing is ready' : 'Complete billing setup' }}
                                </a>
                            </div>
                        </div>
                    </x-card>

                    <x-card>
                        <details class="group" {{ $urgentOpenRequests > 0 ? 'open' : '' }}>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 [&::-webkit-details-marker]:hidden">
                                <div>
                                    <h2 class="font-display font-semibold">Clarity and timing</h2>
                                    <p class="text-xs text-slate-500 mt-1">Quick guide to keep your request moving fast.</p>
                                </div>
                                <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 group-open:hidden">Expand</span>
                                <span class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 hidden group-open:inline">Collapse</span>
                            </summary>
                            <div class="mt-3 space-y-3 border-t border-slate-200 pt-3 text-sm">
                                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p class="font-medium text-slate-900">Typical flow</p>
                                    <p class="text-slate-600">Post request → applicants arrive → chat → hire → track shift.</p>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p class="font-medium text-slate-900">Payment safety</p>
                                    <p class="text-slate-600">Payment method is authorized before shifts so caregiver payout is secured.</p>
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                    <p class="font-medium text-slate-900">Current status</p>
                                    <p class="text-slate-600">{{ $familyData['stats']['ready_to_review'] }} ready to review, {{ $familyData['stats']['waiting_for_applicants'] }} waiting for applicants.</p>
                                </div>
                                @if ($urgentOpenRequests > 0)
                                    <div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-amber-900">
                                        <p class="font-medium">Urgent follow-up</p>
                                        <p class="text-xs mt-1">{{ $urgentOpenRequests }} request(s) have no applicants after 6+ hours.</p>
                                    </div>
                                @endif
                            </div>
                        </details>
                    </x-card>
                </div>
            </section>
            @endif
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
                $prelaunchMessage = (string) ($caregiverData['prelaunch_message'] ?? 'HomeCare is currently in caregiver pre-launch mode. Complete your setup now and we will notify you as soon as matching opens.');

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
                    $nextActionTitle = 'Continue active shift';
                    $nextActionDescription = 'You have a shift in progress. Continue from shift command center.';
                    $nextActionHref = route('care-requests.apply', $caregiverData['active_shift']->careRequest->id);
                    $nextActionLabel = $caregiverData['active_shift']->status === \App\Models\CareBooking::STATUS_PAUSED ? 'Resume shift' : 'Continue shift';
                } elseif ($needsResponseCount > 0) {
                    $nextActionTitle = 'Answer new opportunities';
                    $nextActionDescription = $needsResponseCount.' item(s) need a response in Work Inbox.';
                } elseif ($hasNextShift && $caregiverData['next_shift']->careRequest) {
                    $nextActionTitle = 'Prepare next shift';
                    $nextActionDescription = 'Review details before your next scheduled shift.';
                    $nextActionHref = route('care-requests.apply', $caregiverData['next_shift']->careRequest->id);
                    $nextActionLabel = 'Open next shift';
                } elseif (count($setupCards) > 0) {
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

            <section class="relative overflow-hidden rounded-3xl border border-slate-900/80 bg-slate-950 p-5 text-white shadow-xl">
                <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-emerald-500/20 blur-2xl"></div>
                <div class="pointer-events-none absolute -left-10 -bottom-14 h-40 w-40 rounded-full bg-cyan-500/20 blur-2xl"></div>

                <div class="relative grid grid-cols-1 gap-5 lg:grid-cols-5">
                    <div class="lg:col-span-3">
                        <p class="text-[11px] uppercase tracking-[0.18em] text-slate-300">Caregiver Dashboard</p>
                        <h1 class="mt-1 text-2xl font-display font-semibold leading-tight sm:text-3xl">You're ready to start getting booked.</h1>
                        <p class="mt-2 text-sm text-slate-300">{{ $nextActionDescription }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ $nextActionHref }}" wire:navigate><x-button color="white">{{ $nextActionLabel }}</x-button></a>
                            <a href="{{ route('caregiver.shifts.index') }}" wire:navigate><x-button color="white" light>My shifts</x-button></a>
                            <a href="{{ route('caregiver.earnings.index') }}" wire:navigate><x-button color="white" light>Earnings</x-button></a>
                        </div>
                    </div>
                    <div class="lg:col-span-2 space-y-2">
                        <div class="rounded-xl border border-white/20 bg-white/10 px-3 py-2">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-slate-300">Best next action</p>
                            <p class="mt-1 text-sm font-semibold text-white">{{ $nextActionTitle }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="rounded-xl border border-white/20 bg-white/10 px-3 py-2">
                                <p class="text-[11px] uppercase tracking-[0.14em] text-slate-300">Needs response</p>
                                <p class="mt-1 text-lg font-semibold">{{ $needsResponseCount }}</p>
                            </div>
                            <div class="rounded-xl border border-white/20 bg-white/10 px-3 py-2">
                                <p class="text-[11px] uppercase tracking-[0.14em] text-slate-300">Profile status</p>
                                <p class="mt-1 text-xs font-semibold uppercase">{{ str_replace('_', ' ', (string) ($profile?->status ?? 'draft')) }}</p>
                            </div>
                            <div class="rounded-xl border border-white/20 bg-white/10 px-3 py-2">
                                <p class="text-[11px] uppercase tracking-[0.14em] text-slate-300">Setup done</p>
                                <p class="mt-1 text-lg font-semibold">{{ $requiredCompleted }}/{{ $requiredTotal }}</p>
                            </div>
                            <div class="rounded-xl border border-white/20 bg-white/10 px-3 py-2">
                                <p class="text-[11px] uppercase tracking-[0.14em] text-slate-300">Unread messages</p>
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
                                    <p class="text-xs text-slate-500 mt-1">{{ $item['location'] }} · {{ $item['schedule'] }}</p>
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
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.14em] text-emerald-700 font-semibold">Live now</p>
                            <p class="font-semibold text-emerald-900">{{ $caregiverData['active_shift']->careRequest?->title ?? 'Current shift' }}</p>
                            <p class="text-sm text-emerald-800 mt-1">
                                {{ $caregiverData['active_shift']->status === \App\Models\CareBooking::STATUS_PAUSED ? 'Paused' : 'Started' }}
                                {{ optional($caregiverData['active_shift']->started_at)->format('M d, H:i') ?: 'just now' }}
                                · {{ $caregiverData['active_shift']->careRequest?->city }}, {{ $caregiverData['active_shift']->careRequest?->state }}
                            </p>
                        </div>
                        @if ($caregiverData['active_shift']->careRequest)
                            <a href="{{ route('care-requests.apply', $caregiverData['active_shift']->careRequest->id) }}" wire:navigate>
                                <x-button color="green">{{ $caregiverData['active_shift']->status === \App\Models\CareBooking::STATUS_PAUSED ? 'Resume shift' : 'Continue shift' }}</x-button>
                            </a>
                        @endif
                    </div>
                @elseif (!empty($caregiverData['next_shift']))
                    <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.14em] text-sky-700 font-semibold">Next shift</p>
                            <p class="font-semibold text-sky-900">{{ $caregiverData['next_shift']->careRequest?->title ?? 'Upcoming shift' }}</p>
                            <p class="text-sm text-sky-800 mt-1">
                                {{ optional($caregiverData['next_shift']->scheduled_start_at)->format('M d, Y H:i') ?: 'Date pending' }}
                                · {{ $caregiverData['next_shift']->careRequest?->city }}, {{ $caregiverData['next_shift']->careRequest?->state }}
                            </p>
                        </div>
                        @if ($caregiverData['next_shift']->careRequest)
                            <a href="{{ route('care-requests.apply', $caregiverData['next_shift']->careRequest->id) }}" wire:navigate>
                                <x-button color="blue">Open shift details</x-button>
                            </a>
                        @endif
                    </div>
                @endif

                @if (!empty($caregiverData['quick_shifts']) && $caregiverData['quick_shifts']->count() > 0)
                    <x-card>
                        <x-slot:header>
                            <div class="flex items-center justify-between">
                                <h2 class="font-display font-semibold">Shift quick access</h2>
                                <a href="{{ route('caregiver.shifts.index') }}" wire:navigate class="hc-link">View all shifts</a>
                            </div>
                        </x-slot:header>
                        <div class="space-y-3">
                            @foreach ($caregiverData['quick_shifts'] as $shift)
                                @php
                                    $shiftStatus = (string) $shift->status;
                                    $shiftCta = match ($shiftStatus) {
                                        \App\Models\CareBooking::STATUS_IN_PROGRESS => 'Continue shift',
                                        \App\Models\CareBooking::STATUS_PAUSED => 'Resume shift',
                                        \App\Models\CareBooking::STATUS_SCHEDULED => 'Start shift',
                                        \App\Models\CareBooking::STATUS_COMPLETED => 'View recap',
                                        default => 'Open shift',
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
                    <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-cyan-900">Ready to submit.</p>
                            <p class="text-sm text-cyan-800">Open the review step, confirm your details, and submit now.</p>
                        </div>
                        <a href="{{ route('caregiver.onboarding', ['step' => 4]) }}" wire:navigate>
                            <x-button color="blue" sm>Submit profile for review</x-button>
                        </a>
                    </div>
                @elseif ($readyForReview)
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-emerald-900">Setup complete.</p>
                            <p class="text-sm text-emerald-800">Your profile is already submitted or active.</p>
                        </div>
                        <x-badge color="green" text="{{ strtoupper(str_replace('_', ' ', (string) ($profile?->status ?? 'draft'))) }}" />
                    </div>
                @endif

                @if (count($setupCards) > 0)
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
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
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
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
