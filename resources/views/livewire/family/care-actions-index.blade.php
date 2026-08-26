<div class="hc-page space-y-5 py-5 sm:space-y-6 sm:py-8">
    <section class="hc-brand-panel">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="hc-brand-kicker text-[#E8E0FF]">Actions</p>
                <h1 class="mt-1 font-display text-2xl font-semibold leading-tight sm:text-3xl">Everything waiting for your decision.</h1>
                <p class="mt-2 hidden max-w-2xl text-base text-[#F7F1E8]/82 sm:block">Approvals, caregiver replies, visit changes, and payment issues—ordered by urgency.</p>
            </div>
            <a href="{{ route('family.requests.index', ['person' => $recipient]) }}" wire:navigate class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-[#23483F] shadow-sm transition hover:bg-[#F7F2EA] sm:w-auto">Back to overview</a>
        </div>
    </section>

    <x-family-care-nav active="actions" />

    @if (count($recipientOptions ?? []) > 2)
        <section class="rounded-2xl border border-[#E4DDD3] bg-[#FFFCF8] p-3 shadow-sm sm:flex sm:items-end sm:justify-between sm:gap-5 sm:p-4">
            <div>
                <p class="text-sm font-semibold text-[#17313F]">Filter by family member</p>
                <p class="mt-1 hidden text-xs leading-5 text-[#607080] sm:block">Only actions for the selected person will remain.</p>
            </div>
            <div class="mt-3 w-full sm:mt-0 sm:w-64">
                <x-native-select-field label="Care recipient" wire:model.live="recipient" :options="$recipientOptions" id="care-actions-recipient" />
            </div>
        </section>
    @endif

    <section class="rounded-3xl border border-[#E4DDD3] bg-[#FFFCF8] p-4 shadow-sm sm:p-5">
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#C96B55]">Priority order</p>
                <h2 class="mt-1 font-display text-2xl font-semibold text-[#17313F]">{{ $totalActionCount }} {{ $totalActionCount === 1 ? 'action' : 'actions' }}</h2>
            </div>
        </div>

        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            @forelse ($actions as $action)
                <x-family-action-card :item="$action" />
            @empty
                <div class="rounded-2xl border border-[#D8E1D7] bg-[#F2F8F4] p-6 lg:col-span-2">
                    <p class="font-display text-xl font-semibold text-[#17313F]">You’re all caught up.</p>
                    <p class="mt-1 text-sm leading-6 text-[#607080]">There are no unresolved care decisions for this view.</p>
                </div>
            @endforelse
        </div>

        @if ($hasMoreActions)
            <div class="mt-4 border-t border-[#E4DDD3] pt-4 text-center">
                <p class="text-sm text-[#607080]">Showing {{ $actions->count() }} of {{ $totalActionCount }} actions.</p>
                <button type="button" wire:click="loadMoreActions" class="hc-secondary-button mt-3 w-full sm:w-auto">Show more actions</button>
            </div>
        @endif
    </section>
</div>
