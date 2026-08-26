<div class="hc-page space-y-5 py-5 sm:space-y-6 sm:py-8">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    @if (!empty($prelaunchMode))
        <x-alert color="yellow">
            Matching opens soon in your area. Complete your profile now and we will notify you when matching opens.
        </x-alert>
    @endif

    @php
        $statusStyles = [
            'success' => 'bg-emerald-100 text-emerald-700',
            'warning' => 'bg-amber-100 text-amber-800',
            'danger' => 'bg-rose-100 text-rose-700',
            'info' => 'bg-[#E8F0FF] text-[#4F6FAF]',
            'neutral' => 'bg-[#F0E9E1] text-[#4B5B6B]',
        ];
        $needsResponseCount = (int) ($counts['needs_response'] ?? 0);
        $newRequestCount = (int) ($counts['new_requests'] ?? $counts['recommended'] ?? 0);
        $appliedCount = (int) ($counts['applied'] ?? 0);
        $hiredCount = (int) ($counts['hired'] ?? 0);
        $responsiveValue = collect($items)->where('scope', 'needs_response')->sum(fn ($item) => (float) data_get($item, 'compensation.total', 0));
        $workInboxTitle = 'No action needed right now.';
        $workInboxBody = 'You can browse new requests or check your upcoming visits when you are ready.';
        $workInboxMeta = 'New requests and visits will appear below.';

        if ($needsResponseCount > 0) {
            $workInboxTitle = $needsResponseCount === 1
                ? 'You have 1 request to answer.'
                : 'You have '.$needsResponseCount.' requests to answer.';
            $workInboxBody = 'Start with invitations and time-sensitive requests that need a yes or no.';
            $workInboxMeta = $responsiveValue > 0
                ? '$'.number_format($responsiveValue, 2).' in requests waiting for your response.'
                : 'Requests waiting for your response are shown first.';
        } elseif ($hiredCount > 0) {
            $workInboxTitle = $hiredCount === 1
                ? 'You have 1 hired visit.'
                : 'You have '.$hiredCount.' hired visits.';
            $workInboxBody = 'Open your visits to check schedule, address, check-in, and visit tools.';
            $workInboxMeta = 'Nothing needs an answer before you work.';
        } elseif ($appliedCount > 0) {
            $workInboxTitle = 'Waiting for families to choose.';
            $workInboxBody = 'Your sent applications are below. Open one if you need to review or chat.';
            $workInboxMeta = $appliedCount === 1 ? '1 application is waiting.' : $appliedCount.' applications are waiting.';
        } elseif ($newRequestCount > 0) {
            $workInboxTitle = 'New requests are available.';
            $workInboxBody = 'Review the requests below and apply only to the visits that fit your schedule.';
            $workInboxMeta = $newRequestCount === 1 ? '1 new request to review.' : $newRequestCount.' new requests to review.';
        }

        $primaryActionClasses = 'inline-flex h-11 w-full items-center justify-center rounded-[1rem] bg-[#0F3D3E] px-4 text-sm font-semibold text-[#FAF9F7] shadow-sm transition hover:bg-[#123f40] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]/30 sm:w-auto';
        $successActionClasses = 'inline-flex h-11 w-full items-center justify-center rounded-[1rem] bg-[#4F6FAF] px-4 text-sm font-semibold text-[#FAF9F7] shadow-sm transition hover:bg-[#44639f] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]/30 sm:w-auto';
        $dangerActionClasses = 'inline-flex h-11 w-full items-center justify-center rounded-[1rem] border border-rose-200 bg-[rgba(255,253,250,0.98)] px-4 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500/30 sm:w-auto';
        $secondaryActionClasses = 'inline-flex h-11 w-full items-center justify-center rounded-[1rem] border border-[#DED6CA] bg-[rgba(255,253,250,0.98)] px-4 text-sm font-semibold text-[#0F3D3E] transition hover:bg-[#F5F1EB] focus:outline-none focus:ring-2 focus:ring-[#4F6FAF]/20 sm:w-auto';
    @endphp

    <section class="hc-brand-panel p-4 sm:p-5">
        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="hc-brand-kicker text-[#E8E0FF]">Caregiver Work Inbox</p>
                <h1 class="mt-1 text-2xl font-display font-semibold leading-tight sm:text-3xl">{{ $workInboxTitle }}</h1>
                <p class="mt-2 text-sm leading-6 text-[#F7F1E8]/82">{{ $workInboxBody }}</p>
                <div class="mt-4 rounded-2xl border border-white/15 bg-white/10 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-[#D7DEE6]">Right now</p>
                    <p class="mt-1 text-sm font-medium text-white">{{ $workInboxMeta }}</p>
                </div>
            </div>
            <div class="hidden shrink-0 flex-col gap-2 sm:flex sm:flex-row lg:justify-end">
                <a href="{{ route('caregiver.shifts.index') }}" wire:navigate>
                    <x-button color="white" light class="w-full sm:w-auto" sm>My visits</x-button>
                </a>
                <a href="{{ route('caregiver.earnings.index') }}" wire:navigate>
                    <x-button color="white" light class="w-full sm:w-auto" sm>Earnings</x-button>
                </a>
            </div>
        </div>
    </section>

    <div class="sticky top-16 z-20 -mx-1 px-1">
        <div class="grid grid-cols-1 gap-2 rounded-2xl border border-[#E4DDD3] bg-white/95 p-2 shadow-sm backdrop-blur lg:grid-cols-4">
            <div class="overflow-x-auto lg:col-span-3">
                <div class="flex min-w-max gap-1 lg:min-w-0 lg:grid lg:grid-cols-6 lg:gap-1">
                @foreach ($scopeOptions as $option)
                    @php
                        $scopeCount = (int) ($counts[$option['value']] ?? 0);
                    @endphp
                    <button
                        type="button"
                        wire:click="$set('scope', '{{ $option['value'] }}')"
                            class="flex h-11 min-w-[8.5rem] items-center justify-center gap-2 rounded-xl px-3 text-sm font-medium transition lg:min-w-0 {{ $scope === $option['value'] ? 'bg-[#0F3D3E] text-[#FAF9F7] shadow-sm' : 'text-[#6E746F] hover:bg-[#F5F1EB] hover:text-[#0F3D3E]' }}"
                    >
                        <span class="whitespace-nowrap">{{ $option['label'] }}</span>
                        @if ($scopeCount > 0)
                            <span class="inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-semibold {{ $scope === $option['value'] ? 'bg-white/18 text-white' : 'bg-[#F0E9E1] text-[#4B5B6B]' }}">
                                {{ $scopeCount }}
                            </span>
                        @endif
                    </button>
                @endforeach
                </div>
            </div>
            <div class="hidden rounded-[1rem] border border-[#DED6CA] bg-[rgba(255,253,250,0.96)] p-2 sm:block">
                <label for="work-inbox-sort" class="sr-only">Sort inbox</label>
                <select
                    id="work-inbox-sort"
                    wire:model.live="sort"
                    class="h-10 w-full rounded-xl border-0 bg-white px-3 text-sm font-medium text-[#0F3D3E] shadow-sm outline-none ring-1 ring-[#DED6CA] transition focus:ring-2 focus:ring-[#4F6FAF]/40"
                >
                    @foreach ($sortOptions as $option)
                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <section class="space-y-3 pt-1">
        @forelse ($items as $item)
            <article class="group overflow-hidden rounded-[1.8rem] border border-[#DED6CA] bg-[rgba(255,253,250,0.98)] p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-lg sm:p-5">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_17rem]">
                    <div class="min-w-0">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-display text-lg font-semibold leading-snug text-[#17313F]">{{ $item['title'] }}</p>
                                <p class="mt-1 text-sm text-[#607080]">{{ $item['location'] }} - {{ $item['schedule'] }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusStyles[$item['status_tone'] ?? 'neutral'] ?? $statusStyles['neutral'] }}">
                                {{ strtoupper((string) $item['status_label']) }}
                            </span>
                        </div>

                        <p class="mt-2 text-xs font-medium text-[#7B8794]">{{ $item['meta'] }}</p>
                        @if (!empty($item['recipient_context']))
                            <x-care-recipient-context :context="$item['recipient_context']" :show-name="true" class="mt-3" />
                        @endif
                        <p class="mt-2 text-sm text-[#4B5B6B]">{{ $item['fit_reason'] }}</p>

                        @if (!empty($item['request_details']))
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($item['request_details'] as $detail)
                                    <span class="inline-flex rounded-full border border-[#DED6CA] bg-[#FFFCF8] px-3 py-1 text-xs font-medium text-[#4B5B6B]">
                                        {{ $detail }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        @if (!empty($item['note']))
                            <div class="mt-3 rounded-lg border border-[#E4DDD3] bg-[#F7F2EA] px-3 py-2 text-sm text-[#4B5B6B]">
                                {{ $item['note'] }}
                            </div>
                        @endif

                        @if (!empty($item['compensation_line']))
                            <p class="mt-3 text-xs font-semibold uppercase tracking-[0.12em] text-[#7B8794]">{{ $item['compensation_line'] }}</p>
                        @endif

                        <div class="mt-4 grid grid-cols-1 gap-2 sm:flex sm:flex-wrap sm:items-center">
                            @if (($item['primary_action']['kind'] ?? null) === 'inline')
                                @php
                                    $primaryMethod = (string) $item['primary_action']['method'];
                                    $primaryId = (int) $item['primary_action']['id'];
                                    $primaryFallbackRoute = $primaryMethod === 'acceptInvitation'
                                        ? route('caregiver.invitations.accept', $primaryId)
                                        : '#';
                                @endphp
                                @if ($primaryFallbackRoute !== '#')
                                    <form
                                        method="POST"
                                        action="{{ $primaryFallbackRoute }}"
                                        class="w-full sm:w-auto"
                                    >
                                        @csrf
                                        <button
                                            type="submit"
                                            class="{{ $primaryMethod === 'acceptInvitation' ? $successActionClasses : $primaryActionClasses }}"
                                            aria-label="{{ $item['primary_action']['label'] }}"
                                        >
                                            {{ $item['primary_action']['label'] }}
                                        </button>
                                    </form>
                                @else
                                    <button
                                        type="button"
                                        wire:click="{{ $primaryMethod }}({{ $primaryId }})"
                                        wire:loading.attr="disabled"
                                        wire:target="{{ $primaryMethod }}"
                                        class="{{ $primaryActionClasses }}"
                                        aria-label="{{ $item['primary_action']['label'] }}"
                                    >
                                        {{ $item['primary_action']['label'] }}
                                    </button>
                                @endif
                            @elseif (($item['primary_action']['kind'] ?? null) === 'link')
                                <a href="{{ $item['primary_action']['href'] }}" wire:navigate class="{{ $primaryActionClasses }}" aria-label="{{ $item['primary_action']['label'] }}">
                                    {{ $item['primary_action']['label'] }}
                                </a>
                            @endif

                            @if (!empty($item['secondary_action']))
                                @if (($item['secondary_action']['kind'] ?? null) === 'inline')
                                    @php
                                        $secondaryMethod = (string) $item['secondary_action']['method'];
                                        $secondaryId = (int) $item['secondary_action']['id'];
                                        $secondaryFallbackRoute = $secondaryMethod === 'declineInvitation'
                                            ? route('caregiver.invitations.decline', $secondaryId)
                                            : '#';
                                    @endphp
                                    @if ($secondaryFallbackRoute !== '#')
                                        <form
                                            method="POST"
                                            action="{{ $secondaryFallbackRoute }}"
                                            class="w-full sm:w-auto"
                                        >
                                            @csrf
                                            <button
                                                type="submit"
                                                class="{{ $secondaryMethod === 'declineInvitation' ? $dangerActionClasses : $secondaryActionClasses }}"
                                                aria-label="{{ $item['secondary_action']['label'] }}"
                                            >
                                                {{ $item['secondary_action']['label'] }}
                                            </button>
                                        </form>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="{{ $secondaryMethod }}({{ $secondaryId }})"
                                            wire:loading.attr="disabled"
                                            wire:target="{{ $secondaryMethod }}"
                                            class="{{ $secondaryActionClasses }}"
                                            aria-label="{{ $item['secondary_action']['label'] }}"
                                        >
                                            {{ $item['secondary_action']['label'] }}
                                        </button>
                                    @endif
                                @elseif (($item['secondary_action']['kind'] ?? null) === 'link')
                                    <a href="{{ $item['secondary_action']['href'] }}" wire:navigate class="{{ $secondaryActionClasses }}" aria-label="{{ $item['secondary_action']['label'] }}">
                                        {{ $item['secondary_action']['label'] }}
                                    </a>
                                @endif
                            @endif
                        </div>
                    </div>

                    <div class="lg:pl-1">
                        @if (!empty($item['compensation']))
                            <div class="rounded-[1.4rem] border border-[#D7CCE9] bg-gradient-to-br from-[#0F3D3E] via-[#174A52] to-[#4F6FAF] p-4 text-white shadow-sm">
                                <p class="text-[11px] uppercase tracking-[0.14em] text-emerald-200">Estimated earnings</p>
                                <p class="mt-1 text-3xl font-display font-semibold text-emerald-300">
                                    ${{ number_format((float) data_get($item, 'compensation.total', 0), 2) }}
                                </p>
                                <div class="mt-3 space-y-1 text-sm">
                                    <p class="text-[#E7ECF1]">
                                        {{ data_get($item, 'compensation.hours_label') }}h visit
                                    </p>
                                    <p class="text-[#E7ECF1]">
                                        @ ${{ number_format((float) data_get($item, 'compensation.hourly_rate', 0), 2) }}/hr*
                                    </p>
                                    <p class="pt-1 text-xs text-[#D7DEE6]">*Gross earnings before Stripe processing fees.</p>
                                </div>
                            </div>
                        @else
                            <div class="rounded-[1.4rem] border border-[#DED6CA] bg-[#F5F1EB] p-4">
                                <p class="text-[11px] uppercase tracking-[0.14em] text-[#7B8794]">Earnings</p>
                                <p class="mt-2 text-sm text-[#607080]">Compensation estimate appears when duration is defined.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-[#D6CCBE] bg-white px-4 py-8 text-center text-sm text-[#607080]">
                No items for this filter yet.
            </div>
        @endforelse
    </section>
</div>



