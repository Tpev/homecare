<section class="rounded-2xl border border-[#E4DDD3] bg-white p-4 shadow-sm">
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm font-semibold text-[#17313F]">Profile completeness</p>
        <span class="rounded-full bg-[#17313F] px-2.5 py-1 text-xs font-semibold text-white">{{ $percent }}%</span>
    </div>

    <div class="mt-3 h-2 overflow-hidden rounded-full bg-[#F0E9E1]">
        <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $percent }}%"></div>
    </div>

    <div class="mt-3 space-y-2">
        @foreach($checks as $label => $ok)
            <div class="flex items-center justify-between rounded-lg border border-[#EEE8DF] px-2.5 py-2 text-sm">
                <span class="{{ $ok ? 'text-[#4B5B6B]' : 'text-[#7B8794]' }}">{{ $label }}</span>
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full {{ $ok ? 'bg-emerald-100 text-emerald-700' : 'bg-[#F0E9E1] text-[#B8C2CC]' }}">
                    @if($ok)
                        <svg viewBox="0 0 20 20" class="h-3.5 w-3.5" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.415 0l-3-3a1 1 0 111.414-1.42l2.293 2.294 6.543-6.544a1 1 0 011.415 0z" clip-rule="evenodd" />
                        </svg>
                    @else
                        <svg viewBox="0 0 20 20" class="h-3.5 w-3.5" fill="currentColor" aria-hidden="true">
                            <path d="M10 3a7 7 0 100 14 7 7 0 000-14zM2 10a8 8 0 1116 0A8 8 0 012 10z" />
                        </svg>
                    @endif
                </span>
            </div>
        @endforeach
    </div>
</section>

