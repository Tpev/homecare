<div>
    <div class="hc-page py-8 space-y-6">
        <section class="hc-brand-panel relative overflow-hidden">
            <div class="pointer-events-none absolute -right-10 top-0 h-28 w-28 rounded-full bg-[#7C5DDC]/20 blur-2xl"></div>
            <div class="pointer-events-none absolute -left-8 bottom-0 h-24 w-24 rounded-full bg-[#4F6FAF]/20 blur-2xl"></div>

            <div class="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <p class="hc-brand-kicker inline-flex rounded-full px-3 py-1">Family workspace</p>
                    <h1 class="mt-3 text-3xl font-display font-semibold sm:text-4xl">Your care requests, all in one place.</h1>
                    <p class="mt-3 max-w-xl text-sm text-[#E5E7EB] sm:text-base">
                        Review applicants, keep track of response timing, and move each request from posted to booked with a calmer, clearer workflow.
                    </p>
                    <p class="mt-3 text-xs font-medium uppercase tracking-[0.18em] text-[#CFC6F7]">Average first response: {{ $avgFirstResponseLabel ?? '-' }}</p>
                </div>

                <div class="w-full lg:w-auto">
                    <a href="{{ route('family.requests.create') }}" wire:navigate class="hc-secondary-button w-full sm:w-auto">
                        Create request
                    </a>
                </div>
            </div>
        </section>

        @if (session('status'))
            <x-alert color="green">{{ session('status') }}</x-alert>
        @endif

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="hc-brand-card">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <x-native-select-field label="Status" wire:model.live="status" :options="$statusOptions" />
                    <x-native-select-field label="Type" wire:model.live="requestType" :options="$requestTypeOptions" />
                    <x-native-select-field label="Sort" wire:model.live="sort" :options="$sortOptions" />
                </div>
            </div>

            <div class="hc-brand-card">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#7C5DDC]">Request guidance</p>
                <div class="mt-3 space-y-3 text-sm text-[#3C4A5B]">
                    <div class="rounded-[1rem] border border-[#DED6CA] bg-[#FFFCF8] px-4 py-3">
                        <p class="font-semibold text-[#0F3D3E]">Best practice</p>
                        <p class="mt-1">Keep each request focused on one household need so caregivers can answer faster.</p>
                    </div>
                    <div class="rounded-[1rem] border border-[#DED6CA] bg-[#FFFCF8] px-4 py-3">
                        <p class="font-semibold text-[#0F3D3E]">What to review first</p>
                        <p class="mt-1">Look at trust badges, response speed, and schedule fit before making your shortlist.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4">
            @forelse ($requests as $request)
                @php
                    $nextAction = \App\Support\CareRequestProgress::bestNextAction($request);
                @endphp

                <article class="hc-brand-card space-y-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-2xl font-display font-semibold text-[#0F172A]">{{ $request->title }}</h2>
                                <span class="hc-muted-chip">{{ strtoupper((string) $request->status) }}</span>
                            </div>

                            <p class="text-sm text-[#5B6472]">
                                {{ $request->city }}, {{ $request->state }}
                                @if ($request->request_type === \App\Models\CareRequest::TYPE_ONE_TIME)
                                    - {{ optional($request->requested_start_at)->format('M d, Y H:i') }}
                                @else
                                    - Recurring
                                @endif
                            </p>

                            <p class="text-sm text-[#5B6472]">Recipient: {{ $request->recipient?->full_name ?? 'Not set' }}</p>

                            <p class="text-xs uppercase tracking-[0.14em] text-[#7C5DDC]">
                                Posted {{ \App\Support\CareRequestProgress::postedAgoLabel($request) }}
                                - First response {{ \App\Support\CareRequestProgress::firstResponseLabel($request) }}
                                - First hire {{ \App\Support\CareRequestProgress::firstHireLabel($request) }}
                            </p>
                        </div>

                        <div class="grid w-full gap-3 sm:grid-cols-3 lg:w-[420px]">
                            <div class="hc-metric-card">
                                <p class="text-[11px] uppercase tracking-[0.14em] text-[#7C5DDC]">Applicants</p>
                                <p class="mt-2 text-2xl font-display font-semibold text-[#0F3D3E]">{{ $request->applications_count }}</p>
                            </div>
                            <div class="hc-metric-card">
                                <p class="text-[11px] uppercase tracking-[0.14em] text-[#7C5DDC]">Request type</p>
                                <p class="mt-2 text-sm font-semibold text-[#0F3D3E]">{{ $request->request_type === \App\Models\CareRequest::TYPE_ONE_TIME ? 'One-time' : 'Recurring' }}</p>
                            </div>
                            <div class="hc-metric-card">
                                <p class="text-[11px] uppercase tracking-[0.14em] text-[#7C5DDC]">Status</p>
                                <p class="mt-2 text-sm font-semibold text-[#0F3D3E]">{{ ucfirst((string) $request->status) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[1.4rem] border border-[#DED6CA] bg-[#FFFCF8] px-4 py-4">
                        <p class="text-xs uppercase tracking-[0.14em] text-[#7C5DDC]">Best next action</p>
                        <p class="mt-2 text-lg font-display font-semibold text-[#0F172A]">{{ $nextAction['title'] }}</p>
                        <p class="mt-1 text-sm text-[#5B6472]">{{ $nextAction['action'] }}</p>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-[#E6DED3] pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-[#5B6472]">{{ $request->applications_count }} applicant(s) ready for review</p>
                        <a href="{{ route('family.requests.show', $request->id) }}" wire:navigate class="hc-primary-button w-full sm:w-auto">
                            Open request
                        </a>
                    </div>
                </article>
            @empty
                <div class="hc-brand-card">
                    <p class="text-sm text-[#5B6472]">No requests yet. Create your first one to start receiving applications.</p>
                </div>
            @endforelse
        </div>

        <div>{{ $requests->links() }}</div>
    </div>
</div>
