<div class="hc-page py-6 sm:py-8 space-y-5">
    @if (session('status'))
        <x-alert color="green">{{ session('status') }}</x-alert>
    @endif

    <section class="relative overflow-hidden rounded-3xl border border-[#0F3D3E]/80 bg-[#0F3D3E] p-5 text-white shadow-xl">
        <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-emerald-500/20 blur-2xl"></div>
        <div class="pointer-events-none absolute -left-10 -bottom-14 h-40 w-40 rounded-full bg-[#7C5DDC]/20 blur-2xl"></div>

        <div class="relative space-y-4">
            <p class="text-[11px] uppercase tracking-[0.16em] text-[#D7DEE6]">Caregiver setup</p>
            <h1 class="text-2xl font-display font-semibold leading-tight sm:text-3xl">Finish setup to start getting booked.</h1>
            <p class="text-sm text-[#D7DEE6] max-w-2xl">
                To be searchable by families, complete the 3 required steps, then submit for review.
                Typical review time is up to 1 business day.
            </p>

            <div class="rounded-xl border border-white/20 bg-white/10 p-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs uppercase tracking-[0.12em] text-[#D7DEE6]">Required progress</p>
                    <p class="text-sm font-semibold text-white">{{ $state['required_completed'] }}/{{ $state['required_total'] }}</p>
                </div>
                <div class="mt-2 h-2 rounded-full bg-white/20">
                    <div class="h-2 rounded-full bg-[#4F6FAF] transition-all duration-300" style="width: {{ $state['progress_percent'] }}%"></div>
                </div>
            </div>
        </div>
    </section>

    @if ($state['is_under_review'])
        <section class="rounded-2xl border border-[#C8D9F5] bg-[#F2F7FF] p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-[#28486F]">Profile under review</p>
                    <p class="mt-1 text-sm text-[#355983]">Your profile is submitted. We review most profiles within 1 business day.</p>
                </div>
                <x-badge color="blue" text="UNDER REVIEW" />
            </div>
            @if (!empty($state['rejection_reason']))
                <div class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    <p class="font-semibold">Action needed</p>
                    <p class="mt-1">{{ $state['rejection_reason'] }}</p>
                    <a href="{{ route('caregiver.onboarding', ['step' => 4]) }}" wire:navigate class="mt-2 inline-block font-semibold underline underline-offset-2">
                        Fix and resubmit
                    </a>
                </div>
            @endif
        </section>
    @endif

    <section class="space-y-3">
        <h2 class="font-display text-lg font-semibold text-[#17313F]">Required steps</h2>
        <div class="grid grid-cols-1 gap-3">
            @foreach ($state['required_steps'] as $step)
                <a href="{{ $step['route'] }}" wire:navigate class="block rounded-2xl border {{ $step['done'] ? 'border-emerald-200 bg-emerald-50' : 'border-[#E4DDD3] bg-white' }} p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.12em] {{ $step['done'] ? 'text-emerald-700' : 'text-[#7B8794]' }}">
                                {{ $step['done'] ? 'Done' : 'Required' }}  -  ~{{ $step['minutes'] }} min
                            </p>
                            <p class="mt-1 text-base font-semibold text-[#17313F]">{{ $step['title'] }}</p>
                            <p class="mt-1 text-sm text-[#607080]">{{ $step['description'] }}</p>
                        </div>
                        <x-badge :color="$step['done'] ? 'green' : 'amber'" :text="$step['done'] ? 'DONE' : 'PENDING'" />
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    <section class="rounded-2xl border border-[#E4DDD3] bg-white p-4">
        <details>
            <summary class="cursor-pointer text-sm font-semibold text-[#17313F]">Optional profile boosters</summary>
            <div class="mt-3 grid grid-cols-1 gap-3">
                @foreach ($state['optional_steps'] as $step)
                    <a href="{{ $step['route'] }}" wire:navigate class="block rounded-xl border {{ $step['done'] ? 'border-emerald-200 bg-emerald-50' : 'border-[#E4DDD3] bg-[#F7F2EA]' }} p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-[#17313F]">{{ $step['title'] }}</p>
                                <p class="mt-1 text-xs text-[#607080]">{{ $step['description'] }}</p>
                            </div>
                            <x-badge :color="$step['done'] ? 'green' : 'slate'" :text="$step['done'] ? 'DONE' : 'OPTIONAL'" />
                        </div>
                    </a>
                @endforeach
            </div>
        </details>
    </section>

    <div class="h-20 sm:h-0"></div>

    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-[#E4DDD3] bg-white/95 p-3 backdrop-blur sm:static sm:border-0 sm:bg-transparent sm:p-0">
        <div class="mx-auto max-w-5xl">
            @if ($state['can_submit_for_review'])
                <a href="{{ route('caregiver.onboarding', ['step' => 4]) }}" wire:navigate class="block">
                    <x-button color="green" class="w-full">Submit for review</x-button>
                </a>
            @elseif ($state['is_under_review'])
                <x-button color="blue" light class="w-full" :disabled="true">Under review</x-button>
            @else
                <x-button color="blue" class="w-full" wire:click="continueSetup">
                    {{ $state['next_required_label'] }}
                </x-button>
            @endif
        </div>
    </div>
</div>


