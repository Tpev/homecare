<div class="hc-page space-y-5 py-5 sm:space-y-6 sm:py-8">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @if (!empty($prelaunchMode))
        <x-alert color="yellow">
            Caregiver pre-launch mode is active. Keep building your profile and we will notify you when applications open.
        </x-alert>
    @endif

    <section class="rounded-3xl border border-[#E4DDD3] bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="hc-brand-kicker">Open care requests</p>
                <h1 class="mt-1 text-2xl font-display font-semibold leading-tight text-[#17313F] sm:text-3xl">New work you can act on</h1>
                <p class="mt-1 max-w-2xl text-sm text-[#607080]">Browse open requests, direct invitations, and applications without digging through shift history.</p>
            </div>
            <a href="{{ route('caregiver.work-inbox.index') }}" wire:navigate>
                <x-button color="white" light class="w-full lg:w-auto">Back to work inbox</x-button>
            </a>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-2 lg:grid-cols-4">
            @foreach ($scopeOptions as $option)
                @php
                    $count = (int) ($scopeCounts[$option['value']] ?? 0);
                @endphp
                <button
                    type="button"
                    wire:click="$set('scope', '{{ $option['value'] }}')"
                    class="rounded-2xl border px-3 py-3 text-left transition {{ $scope === $option['value'] ? 'border-[#0F3D3E] bg-[#0F3D3E] text-[#FAF9F7] shadow-sm' : 'border-[#DED6CA] bg-[#FFFCF8] text-[#0F3D3E] hover:border-[#B7ADA0]' }}"
                >
                    <span class="block text-sm font-semibold">{{ $option['label'] }}</span>
                    <span class="mt-1 block text-2xl font-display font-semibold">{{ $count }}</span>
                </button>
            @endforeach
        </div>
    </section>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[19rem_minmax(0,1fr)]">
        <x-card>
            <div class="space-y-4">
                <div>
                    <h2 class="font-display text-lg font-semibold text-[#17313F]">Filters</h2>
                    <p class="mt-1 text-sm text-[#607080]">Keep the list focused on requests you can realistically cover.</p>
                </div>

                <x-input label="City" wire:model.blur="city" />
                <x-input label="State (2 letters)" wire:model.blur="state" maxlength="2" />

                <div>
                    <p class="text-sm font-medium text-[#324457]">Tasks</p>
                    <div class="mt-2 max-h-52 space-y-2 overflow-y-auto rounded-xl border border-[#DED6CA] bg-[#FFFCF8] p-2">
                        @foreach ($taskOptions as $task)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-2 text-sm text-[#324457] hover:bg-white" wire:key="request-task-filter-{{ $task['id'] }}">
                                <input type="checkbox" value="{{ $task['id'] }}" wire:model.live="taskIds" class="rounded border-[#B7ADA0] text-[#0F3D3E] focus:ring-[#4F6FAF]">
                                <span>{{ $task['name'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <x-native-select-field
                    label="Job type"
                    wire:model.live="requestType"
                    :options="[
                        ['label' => 'All types', 'value' => 'all'],
                        ['label' => 'One-time', 'value' => \App\Models\CareRequest::TYPE_ONE_TIME],
                        ['label' => 'Recurring', 'value' => \App\Models\CareRequest::TYPE_RECURRING],
                    ]"
                />

                <x-native-select-field
                    label="When"
                    wire:model.live="when"
                    :options="[
                        ['label' => 'Any date', 'value' => 'any'],
                        ['label' => 'Today', 'value' => 'today'],
                        ['label' => 'This week', 'value' => 'this_week'],
                    ]"
                />

                <x-native-select-field
                    label="Sort"
                    wire:model.live="sort"
                    :options="[
                        ['label' => 'Newest', 'value' => 'newest'],
                        ['label' => 'Soonest start', 'value' => 'start_soon'],
                    ]"
                />

                <button type="button" wire:click="clearFilters" class="h-11 w-full rounded-xl border border-[#DED6CA] bg-white px-3 text-sm font-semibold text-[#0F3D3E] transition hover:bg-[#F5F1EB]">
                    Clear filters
                </button>
            </div>
        </x-card>

        <section class="space-y-4">
            @forelse ($requests as $request)
                @php
                    $existingApplication = $request->applications->first();
                    $pendingInvitation = $request->invitations->firstWhere('status', \App\Models\CareRequestInvitation::STATUS_PENDING);
                    $preview = $this->requestPreview($request);
                    $payLine = $this->estimatedPayLine($request);
                    $isFresh = $request->created_at?->gte(now()->subDays(2)) ?? false;
                    $taskNotes = $request->tasks->filter(fn ($task) => filled($task->pivot?->task_note));
                @endphp

                <article class="rounded-3xl border border-[#DED6CA] bg-[rgba(255,253,250,0.98)] p-4 shadow-sm transition hover:shadow-md sm:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-display text-xl font-semibold leading-snug text-[#17313F]">{{ $request->title }}</h2>
                                @if ($isFresh)
                                    <x-badge text="NEW" color="blue" />
                                @endif
                                @if ($pendingInvitation)
                                    <x-badge text="INVITED" color="green" />
                                @endif
                                @if ($request->is_private)
                                    <x-badge text="PRIVATE" color="indigo" />
                                @endif
                                @if ($request->request_type === \App\Models\CareRequest::TYPE_RECURRING)
                                    <x-badge text="RECURRING" color="green" />
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-[#607080]">{{ $request->city }}, {{ $request->state }} - posted {{ $this->postedLabel($request) }}</p>
                        </div>

                        <div class="shrink-0 text-left sm:text-right">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Applicants</p>
                            <p class="mt-1 font-semibold text-[#17313F]">{{ $request->applications_count }}</p>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-2 md:grid-cols-3">
                        <div class="rounded-2xl border border-[#E4DDD3] bg-white px-3 py-3">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Schedule</p>
                            <p class="mt-1 text-sm font-medium text-[#17313F]">{{ $this->scheduleLabel($request) }}</p>
                        </div>
                        <div class="rounded-2xl border border-[#E4DDD3] bg-white px-3 py-3">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Response</p>
                            <p class="mt-1 text-sm font-medium text-[#17313F]">{{ $this->responseTargetLabel($request) }}</p>
                        </div>
                        <div class="rounded-2xl border border-[#E4DDD3] bg-white px-3 py-3">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Estimated pay</p>
                            <p class="mt-1 text-sm font-medium text-[#17313F]">{{ $payLine ?: '$27.00/hr* after schedule is confirmed' }}</p>
                            <p class="mt-1 text-xs text-[#607080]">*Gross earnings before Stripe processing fees.</p>
                        </div>
                    </div>

                    <div class="mt-4">
                        <x-care-recipient-context :recipient="$request->recipient" :show-name="true" class="mt-2" />
                    </div>

                    @if ($preview)
                        <div class="mt-4 rounded-2xl border border-[#E4DDD3] bg-[#F7F2EA] px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.12em] text-[#7B8794]">Care details</p>
                            <p class="mt-1 text-sm text-[#324457]">{{ $preview }}</p>
                            @if ($request->time_expectations && $request->time_expectations !== $preview)
                                <p class="mt-2 text-sm text-[#607080]">{{ \Illuminate\Support\Str::limit($request->time_expectations, 180) }}</p>
                            @endif
                        </div>
                    @endif

                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($request->tasks as $task)
                            <span class="inline-flex rounded-full bg-[#F0E9E1] px-3 py-1 text-xs font-medium text-[#4B5B6B]">{{ $task->name }}</span>
                        @endforeach
                    </div>

                    @if ($taskNotes->isNotEmpty())
                        <div class="mt-3 space-y-2">
                            @foreach ($taskNotes->take(3) as $task)
                                <p class="rounded-xl border border-[#E4DDD3] bg-white px-3 py-2 text-xs text-[#607080]">
                                    <span class="font-semibold text-[#17313F]">{{ $task->name }}:</span>
                                    {{ \Illuminate\Support\Str::limit((string) $task->pivot?->task_note, 170) }}
                                </p>
                            @endforeach
                        </div>
                    @endif

                    @if ($pendingInvitation)
                        <div class="mt-4 rounded-xl border border-[#BDD4F7] bg-[#EEF5FF] px-3 py-2 text-sm text-[#0F3D3E]">
                            This family invited you directly. Open the request to review it and respond.
                        </div>
                    @endif

                    <div class="mt-5 flex flex-col gap-3 border-t border-[#E4DDD3] pt-4 sm:flex-row sm:items-center sm:justify-between">
                        @if ($existingApplication)
                            <p class="text-sm text-[#607080]">You already applied: <span class="font-semibold text-[#17313F]">{{ strtoupper($existingApplication->status) }}</span></p>
                        @else
                            <p class="text-sm text-[#607080]">No application sent yet.</p>
                        @endif

                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            @if ($existingApplication && in_array($existingApplication->status, ['shortlisted', 'hired'], true) && $existingApplication->conversation)
                                <a href="{{ route('messages.show', $existingApplication->conversation->id) }}" wire:navigate>
                                    <x-button color="indigo" light class="w-full sm:w-auto">Open chat</x-button>
                                </a>
                            @endif

                            <a href="{{ route('care-requests.apply', $request->id) }}" wire:navigate>
                                <x-button color="blue" class="w-full sm:w-auto">{{ $existingApplication ? 'Open application' : 'Review and apply' }}</x-button>
                            </a>
                        </div>
                    </div>
                </article>
            @empty
                <x-card>
                    <p class="text-sm text-[#607080]">
                        {{ !empty($prelaunchMode) ? 'Applications are paused during pre-launch. You will be notified when Raleigh goes live.' : 'No open requests match this view yet.' }}
                    </p>
                </x-card>
            @endforelse

            <div>{{ $requests->links() }}</div>
        </section>
    </div>
</div>
