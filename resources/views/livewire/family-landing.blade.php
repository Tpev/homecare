@php
    $serviceGroups = [
        [
            'title' => 'Companionship',
            'accent' => 'blue',
            'items' => ['Conversation and check-ins', 'Reading, games, and engagement', 'Supportive presence at home'],
            'icon' => 'chat',
        ],
        [
            'title' => 'Everyday support',
            'accent' => 'sky',
            'items' => ['Errands and groceries', 'Meal prep and light household help', 'Help around a changing schedule'],
            'icon' => 'clock',
        ],
    ];

    $supportPlans = [
        [
            'step' => '1',
            'title' => 'Quick check-ins',
            'body' => 'Short visits for reassurance, updates, or help with a very specific need.',
        ],
        [
            'step' => '2',
            'title' => 'Standard support blocks',
            'body' => 'A few focused hours for companionship, errands, meals, and home support.',
        ],
        [
            'step' => '3',
            'title' => 'Longer coverage',
            'body' => 'More complete daytime support when your family needs broader coverage.',
        ],
    ];

    $visibilityFeatures = [
        [
            'title' => 'Arrival and shift visibility',
            'body' => 'See when care begins and keep the visit grounded in one clear workflow.',
        ],
        [
            'title' => 'Direct in-platform messaging',
            'body' => 'Share context, preferences, and important details without scattered texts and callbacks.',
        ],
        [
            'title' => 'Clear caregiver review flow',
            'body' => 'Compare profiles, align on needs, and make a better decision before care starts.',
        ],
    ];

    $profiles = [
        [
            'initials' => 'AL',
            'name' => 'Alyssa L.',
            'role' => 'Caregiver with companionship focus',
            'quote' => 'Families want someone dependable, calm, and easy to communicate with. That is the part I take seriously.',
            'tags' => ['Conversation', 'Check-ins'],
            'accent' => 'blue',
        ],
        [
            'initials' => 'MP',
            'name' => 'Maria P.',
            'role' => 'Family support and errands',
            'quote' => 'A great visit is not just helpful. It should make the whole family feel less alone carrying the plan.',
            'tags' => ['Errands', 'Meal prep'],
            'accent' => 'emerald',
        ],
        [
            'initials' => 'DB',
            'name' => 'David B.',
            'role' => 'Experienced support professional',
            'quote' => 'What matters most is consistency, kindness, and making sure the family knows what actually happened during the visit.',
            'tags' => ['Routine support', 'Presence'],
            'accent' => 'slate',
        ],
    ];

    $trustSignals = [
        'Identity verification before work begins',
        'Secure checkout and payment flow',
        'Private in-platform communication',
        'Profiles built for family review, not anonymous guessing',
    ];

    $podcast = config('marketing.podcast', []);
    $podcastLinks = array_values(array_filter([
        ['label' => 'Spotify', 'url' => $podcast['spotify_url'] ?? null],
        ['label' => 'Apple Podcasts', 'url' => $podcast['apple_url'] ?? null],
        ['label' => 'YouTube', 'url' => $podcast['youtube_url'] ?? null],
        ['label' => 'Transcript', 'url' => $podcast['transcript_url'] ?? null],
    ], fn (array $link): bool => filled($link['url'])));
@endphp

<style>
    .glass-nav {
        backdrop-filter: blur(20px);
        background: rgba(255, 255, 255, 0.88);
        border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    }

    .bg-grid {
        background-image: radial-gradient(rgba(148, 163, 184, 0.2) 1px, transparent 1px);
        background-size: 38px 38px;
    }

    .hero-gradient {
        background:
            radial-gradient(circle at 50% 10%, rgba(59, 130, 246, 0.12), transparent 34%),
            linear-gradient(180deg, #f6fbff 0%, #ffffff 62%);
    }

    .reveal {
        opacity: 0;
        transform: translateY(24px);
        transition: opacity 0.65s cubic-bezier(0.16, 1, 0.3, 1), transform 0.65s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .reveal.active {
        opacity: 1;
        transform: translateY(0);
    }

    .profile-card {
        transition: transform 0.35s ease, box-shadow 0.35s ease;
    }

    .profile-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 26px 50px -24px rgba(15, 23, 42, 0.22);
    }

    .podcast-card {
        background: linear-gradient(135deg, #111827 0%, #0f172a 100%);
    }
</style>

<div class="overflow-x-hidden bg-white text-slate-900">
    <nav class="glass-nav fixed top-0 z-50 w-full">
        <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-6">
            <a href="{{ route('landing') }}" class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-lg shadow-blue-200/80">
                    <x-application-logo class="h-5 w-5 text-white" />
                </div>
                <span class="text-xl font-extrabold uppercase italic tracking-tight text-slate-950">
                    Home<span class="text-blue-600">Care</span>
                </span>
            </a>

            <div class="hidden items-center gap-8 text-sm font-bold tracking-tight text-slate-600 md:flex">
                <a href="#services" class="transition-colors hover:text-blue-600">Our Services</a>
                <a href="#podcast" class="transition-colors hover:text-blue-600">Podcast</a>
                <a href="#safety" class="transition-colors hover:text-blue-600">Safety</a>
                <a href="{{ route('caregivers.search') }}" class="transition-colors hover:text-blue-600">Browse caregivers</a>
                <a href="{{ route('register') }}" class="rounded-2xl bg-blue-600 px-6 py-3 text-white shadow-md transition-all hover:bg-slate-950">
                    Get Started
                </a>
            </div>

            <div class="flex items-center gap-2 md:hidden">
                <a href="{{ route('login') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700">
                    Sign in
                </a>
            </div>
        </div>
    </nav>

    <section class="bg-grid hero-gradient px-6 pb-24 pt-40">
        <div class="mx-auto max-w-7xl">
            <div class="reveal active text-center">
                <div class="mb-8 inline-flex items-center gap-2 rounded-full border border-blue-100 bg-white px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-blue-600 shadow-sm">
                    <span class="flex h-2 w-2 rounded-full bg-blue-600"></span>
                    Built for families coordinating non-medical home care in Raleigh
                </div>

                <h1 class="mx-auto max-w-5xl text-5xl font-black leading-[0.9] tracking-[-0.05em] text-slate-900 md:text-[5.4rem]">
                    Stop managing care.<br>
                    Start being a <span class="text-blue-600">daughter again.</span>
                </h1>

                <p class="mx-auto mt-8 max-w-3xl text-xl font-medium leading-relaxed text-slate-500">
                    HomeCare gives families a more transparent path to non-medical support at home:
                    review caregivers, message directly, track the visit, and keep everyone aligned without the usual black box.
                </p>

                <div class="mt-12 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <a href="{{ route('register') }}" class="w-full rounded-[1.25rem] bg-slate-950 px-12 py-5 text-lg font-bold text-white shadow-xl transition-all hover:bg-blue-600 sm:w-auto">
                        Find Help Now
                    </a>
                    <a href="{{ route('caregivers.search') }}" class="w-full rounded-[1.25rem] border border-slate-200 bg-white px-12 py-5 text-lg font-bold text-slate-900 transition-all hover:bg-slate-50 sm:w-auto">
                        Browse Local Pros
                    </a>
                </div>
            </div>

            <div class="mt-16 grid gap-6 lg:grid-cols-[1.15fr_0.85fr] lg:items-center">
                <div class="reveal rounded-[2rem] border border-blue-100 bg-white p-8 shadow-[0_30px_70px_-28px_rgba(59,130,246,0.24)]">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-[1.4rem] border border-slate-100 bg-slate-50 p-4">
                            <div class="text-[11px] font-black uppercase tracking-[0.16em] text-blue-600">Clearer fit</div>
                            <p class="mt-3 text-sm font-semibold leading-6 text-slate-700">Review caregiver profiles before you make a hiring decision.</p>
                        </div>
                        <div class="rounded-[1.4rem] border border-slate-100 bg-slate-50 p-4">
                            <div class="text-[11px] font-black uppercase tracking-[0.16em] text-blue-600">Direct chat</div>
                            <p class="mt-3 text-sm font-semibold leading-6 text-slate-700">Handle care details in one place instead of scattered callbacks.</p>
                        </div>
                        <div class="rounded-[1.4rem] border border-slate-100 bg-slate-50 p-4">
                            <div class="text-[11px] font-black uppercase tracking-[0.16em] text-blue-600">Better visibility</div>
                            <p class="mt-3 text-sm font-semibold leading-6 text-slate-700">Keep the family informed once support at home begins.</p>
                        </div>
                    </div>
                </div>

                <div class="reveal rounded-[2.4rem] border border-slate-900 bg-slate-950 p-7 text-white shadow-2xl">
                    <div class="rounded-[1.5rem] border border-white/10 bg-white/5 p-5">
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-300">Family dashboard</p>
                                <p class="mt-1 text-lg font-black tracking-tight">Today’s care plan</p>
                            </div>
                            <div class="rounded-full bg-blue-600/20 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-blue-200">
                                Live
                            </div>
                        </div>

                        <div class="rounded-[1.25rem] bg-white p-4 text-slate-900">
                            <div class="flex items-center gap-3">
                                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-600 text-sm font-black text-white">SM</div>
                                <div>
                                    <p class="text-sm font-black">Sarah M.</p>
                                    <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-blue-600">Active visit</p>
                                </div>
                            </div>

                            <div class="mt-4 space-y-2">
                                <div class="flex items-center gap-2 text-[11px] font-bold text-slate-500">
                                    <span class="h-2 w-2 rounded-full bg-green-500"></span>
                                    Caregiver has arrived
                                </div>
                                <div class="flex items-center gap-2 text-[11px] font-bold text-slate-500">
                                    <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                                    Groceries and lunch support planned
                                </div>
                            </div>

                            <button type="button" class="mt-4 w-full rounded-xl border border-blue-200 bg-blue-50 py-3 text-xs font-black uppercase tracking-[0.16em] text-blue-700">
                                Message caregiver
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 rounded-[1.5rem] border border-white/10 bg-white/5 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">What families actually want</p>
                        <p class="mt-2 text-sm font-semibold leading-6 text-slate-200">
                            Less chasing. Better context. More confidence in who is showing up and what is happening at home.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="services" class="bg-white px-6 py-24">
        <div class="mx-auto max-w-7xl">
            <div class="grid items-start gap-20 lg:grid-cols-2">
                <div class="reveal">
                    <h2 class="text-4xl font-black uppercase italic tracking-tight text-slate-950 md:text-5xl">
                        More than just a visit.
                    </h2>
                    <p class="mt-6 max-w-2xl text-lg font-medium leading-relaxed text-slate-500">
                        Families are not just hiring time. They are trying to restore calm, coverage, and confidence at home.
                        The page should feel that way too.
                    </p>

                    <div class="mt-12 grid gap-4 sm:grid-cols-2">
                        @foreach ($serviceGroups as $group)
                            <div class="rounded-[2rem] border border-slate-100 bg-slate-50 p-6">
                                <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl {{ $group['accent'] === 'blue' ? 'bg-blue-100 text-blue-600' : 'bg-sky-100 text-sky-600' }}">
                                    @if ($group['icon'] === 'chat')
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.95-1.325L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                        </svg>
                                    @else
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    @endif
                                </div>
                                <h4 class="text-xs font-black uppercase italic tracking-[0.2em] {{ $group['accent'] === 'blue' ? 'text-blue-600' : 'text-sky-600' }}">
                                    {{ $group['title'] }}
                                </h4>
                                <ul class="mt-4 space-y-2 text-sm font-medium text-slate-600">
                                    @foreach ($group['items'] as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="reveal relative overflow-hidden rounded-[3rem] bg-slate-900 p-10 text-white">
                    <div class="relative z-10">
                        <h3 class="text-3xl font-black italic tracking-tight">Ultimate flexibility.</h3>
                        <p class="mt-4 max-w-xl text-sm font-medium leading-7 text-slate-400">
                            This section mirrors the feeling of your reference design, but keeps the message grounded in what HomeCare actually helps families do.
                        </p>

                        <div class="mt-8 space-y-6">
                            @foreach ($supportPlans as $plan)
                                <div class="flex items-start gap-4">
                                    <div class="mt-1 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-blue-500 text-[10px] font-black text-white">
                                        {{ $plan['step'] }}
                                    </div>
                                    <div>
                                        <h4 class="text-lg font-bold leading-tight">{{ $plan['title'] }}</h4>
                                        <p class="mt-1 text-sm text-slate-400">{{ $plan['body'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <a href="{{ route('register') }}" class="mt-10 inline-flex w-full items-center justify-center rounded-[1.25rem] bg-white py-4 text-center text-sm font-black uppercase tracking-[0.18em] text-slate-900">
                            Start a care request
                        </a>
                    </div>

                    <div class="absolute -bottom-12 -right-12 h-72 w-72 rounded-full bg-blue-600/20 blur-3xl"></div>
                </div>
            </div>
        </div>
    </section>

    <section id="podcast" class="bg-slate-50 px-6 py-24">
        <div class="mx-auto max-w-5xl">
            <div class="podcast-card reveal flex flex-col items-center gap-12 rounded-[3rem] p-10 text-white shadow-2xl md:flex-row md:p-16">
                <div class="relative flex h-48 w-48 flex-shrink-0 items-center justify-center overflow-hidden rounded-[2rem] border-4 border-slate-700 bg-slate-800 shadow-inner">
                    <div class="absolute inset-0 bg-blue-600 opacity-20"></div>
                    <svg class="relative z-10 h-20 w-20 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C9.24 2 7 4.24 7 7v6c0 2.76 2.24 5 5 5s5-2.24 5-5V7c0-2.76-2.24-5-5-5zm8 11h-2c0 3.31-2.69 6-6 6s-6-2.69-6-6H4c0 4.07 3.06 7.44 7 7.93V22h2v-1.07c3.94-.49 7-3.86 7-7.93z"/>
                    </svg>
                </div>

                <div class="flex-1 text-center md:text-left">
                    <div class="mb-4 inline-block rounded-full bg-blue-600 px-4 py-1 text-[10px] font-black uppercase tracking-[0.2em]">
                        Featured Podcast
                    </div>
                    <h2 class="text-3xl font-black italic leading-tight md:text-5xl">
                        {{ $podcast['episode_title'] ?? 'The future of care feels more personal, visible, and direct.' }}
                    </h2>
                    <p class="mt-4 font-medium text-slate-400">
                        {{ $podcast['episode_summary'] ?? ($podcast['description'] ?? 'Use this section for a founder conversation, family education series, or short audio brief that reinforces expertise and trust.') }}
                    </p>

                    @if (filled($podcast['embed_url'] ?? null))
                        <div class="mt-8 overflow-hidden rounded-[1.5rem] bg-white">
                            <iframe
                                src="{{ $podcast['embed_url'] }}"
                                title="{{ $podcast['episode_title'] ?? 'HomeCare podcast' }}"
                                loading="lazy"
                                class="h-[232px] w-full border-0"
                                allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
                            ></iframe>
                        </div>
                    @elseif (filled($podcast['audio_url'] ?? null))
                        <div class="mt-8 rounded-[1.5rem] bg-white p-4">
                            <audio controls preload="none" class="w-full">
                                <source src="{{ $podcast['audio_url'] }}">
                                Your browser does not support the audio element.
                            </audio>
                        </div>
                    @else
                        <div class="mt-8 rounded-[1.5rem] border border-slate-700 bg-slate-800/80 p-5">
                            <p class="text-sm font-semibold text-white">Podcast player area ready</p>
                            <p class="mt-2 text-sm text-slate-400">
                                Add a Spotify embed URL or hosted MP3 in config and this section becomes a live player without changing the page design again.
                            </p>
                        </div>
                    @endif

                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        @foreach ($podcastLinks as $link)
                            <a href="{{ $link['url'] }}" class="flex items-center gap-3 rounded-2xl {{ $loop->first ? 'bg-white text-slate-900' : 'border border-slate-700 bg-slate-800 text-white' }} px-8 py-4 font-bold transition-all hover:bg-blue-50 hover:text-slate-900">
                                {{ $link['label'] }}
                            </a>
                        @endforeach

                        @if (count($podcastLinks) === 0)
                            <button type="button" class="flex items-center gap-3 rounded-2xl bg-white px-8 py-4 font-bold text-slate-900">
                                Listen on Spotify
                            </button>
                            <button type="button" class="flex items-center gap-3 rounded-2xl border border-slate-700 bg-slate-800 px-8 py-4 font-bold text-white">
                                Apple Podcasts
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white px-6 py-24">
        <div class="mx-auto grid max-w-7xl items-center gap-20 lg:grid-cols-2">
            <div class="reveal">
                <h2 class="text-4xl font-black uppercase italic tracking-tight text-slate-950 md:text-5xl">
                    Visibility is <br>
                    <span class="text-blue-600 underline decoration-4 underline-offset-8">peace of mind.</span>
                </h2>
                <p class="mt-6 text-lg font-medium leading-relaxed text-slate-500">
                    Distance should not mean disconnect. The right product feeling here is calm, informed, and specific.
                </p>

                <div class="mt-8 space-y-5">
                    @foreach ($visibilityFeatures as $feature)
                        <div class="flex gap-4 rounded-[1.5rem] p-4 transition-colors hover:bg-blue-50">
                            <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-blue-100 text-blue-600">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold">{{ $feature['title'] }}</h4>
                                <p class="text-sm font-medium text-slate-500">{{ $feature['body'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="reveal relative">
                <div class="rounded-[3rem] border-4 border-slate-800 bg-slate-900 p-3 shadow-2xl">
                    <div class="aspect-[9/19] overflow-hidden rounded-[2.5rem] bg-white">
                        <div class="p-6">
                            <div class="mb-8 flex items-center justify-between">
                                <span class="text-xl font-black uppercase italic tracking-tight">Home<span class="text-blue-600">Care</span></span>
                                <div class="h-8 w-8 rounded-full bg-slate-100"></div>
                            </div>

                            <h3 class="mb-4 text-lg font-bold">Active visit</h3>
                            <div class="mb-4 rounded-[1.7rem] border border-blue-100 bg-blue-50 p-5">
                                <div class="mb-4 flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-xs font-black text-white">SM</div>
                                    <div>
                                        <p class="text-xs font-bold">Sarah M.</p>
                                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-blue-600">Visiting Mom</p>
                                    </div>
                                </div>

                                <div class="mb-4 space-y-2">
                                    <div class="flex items-center gap-2 text-[10px] font-bold text-slate-500">
                                        <div class="h-1.5 w-1.5 rounded-full bg-green-500"></div>
                                        Caregiver checked in
                                    </div>
                                    <div class="flex items-center gap-2 text-[10px] font-bold text-slate-500">
                                        <div class="h-1.5 w-1.5 rounded-full bg-blue-500"></div>
                                        Grocery and meal support complete
                                    </div>
                                </div>

                                <button type="button" class="w-full rounded-xl border border-blue-200 bg-white py-2 text-[10px] font-bold uppercase tracking-[0.16em] text-blue-600">
                                    Message Sarah
                                </button>
                            </div>

                            <h3 class="mb-4 text-lg font-bold">Visit summary</h3>
                            <div class="flex aspect-square flex-col items-center justify-center gap-2 rounded-[1.7rem] bg-slate-100 text-slate-400">
                                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span class="text-[10px] font-bold uppercase tracking-[0.16em]">Summary updates here</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="reveal active absolute -right-4 top-1/2 rounded-2xl border border-slate-100 bg-white p-4 shadow-xl">
                    <div class="flex items-center gap-3">
                        <div class="h-2 w-2 rounded-full bg-green-500"></div>
                        <p class="text-xs font-bold">Verification complete</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-slate-50 px-6 py-24">
        <div class="mx-auto max-w-7xl">
            <div class="mb-16 text-center">
                <h2 class="text-4xl font-black uppercase italic tracking-tight text-slate-950">
                    Empathy you can trust.
                </h2>
                <p class="mt-4 font-medium text-slate-500">
                    More professional presentation, but still human. That balance is the point.
                </p>
            </div>

            <div class="grid gap-8 md:grid-cols-3">
                @foreach ($profiles as $profile)
                    <div class="profile-card rounded-[2.5rem] border border-slate-100 bg-white p-8">
                        <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl {{ $profile['accent'] === 'blue' ? 'bg-blue-100 text-blue-600' : ($profile['accent'] === 'emerald' ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-600') }} text-2xl font-black uppercase italic">
                            {{ $profile['initials'] }}
                        </div>

                        <div class="mb-6 text-center">
                            <h4 class="text-lg font-black">{{ $profile['name'] }}</h4>
                            <p class="mb-2 text-[11px] font-bold uppercase tracking-[0.16em] {{ $profile['accent'] === 'blue' ? 'text-blue-600' : ($profile['accent'] === 'emerald' ? 'text-emerald-600' : 'text-slate-600') }}">
                                {{ $profile['role'] }}
                            </p>
                            <div class="text-xs text-yellow-400">★★★★★ <span class="ml-1 text-slate-400">(Verified)</span></div>
                        </div>

                        <p class="mb-6 text-center text-sm font-medium italic leading-6 text-slate-500">
                            "{{ $profile['quote'] }}"
                        </p>

                        <div class="flex flex-wrap justify-center gap-2">
                            @foreach ($profile['tags'] as $tag)
                                <span class="rounded-full border border-slate-100 bg-slate-50 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.12em]">
                                    {{ $tag }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="safety" class="relative overflow-hidden bg-blue-600 py-16 text-white">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-10 px-6 md:flex-row">
            <div class="reveal">
                <h2 class="text-3xl font-black uppercase italic md:text-4xl">Safety is not optional.</h2>
                <p class="mt-4 max-w-2xl font-medium text-blue-100">
                    This section is intentionally sharper and more direct. Credibility comes from clear operational signals families can understand quickly.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    @foreach ($trustSignals as $signal)
                        <span class="rounded-full border border-white/20 bg-white/10 px-4 py-2 text-xs font-black uppercase tracking-[0.14em] text-white">
                            {{ $signal }}
                        </span>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-4 reveal">
                <div class="rounded-2xl border border-white/20 bg-white/10 px-6 py-4 text-center">
                    <div class="text-2xl font-black uppercase italic">KYC</div>
                    <div class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-100">Verification</div>
                </div>
                <div class="rounded-2xl border border-white/20 bg-white/10 px-6 py-4 text-center">
                    <div class="text-2xl font-black uppercase italic">Chat</div>
                    <div class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-100">Protected</div>
                </div>
            </div>
        </div>
    </section>

    <section class="px-6 py-28 text-center">
        <div class="reveal mx-auto max-w-5xl">
            <h2 class="text-5xl font-black uppercase italic leading-[0.85] tracking-[-0.05em] text-slate-900 md:text-8xl">
                Restore your role <br>
                as a <span class="text-blue-600 underline decoration-8 underline-offset-10">family member.</span>
            </h2>
            <p class="mx-auto mt-8 max-w-2xl text-xl font-medium text-slate-500">
                The homepage now leans into a clearer, more premium editorial style while still explaining what HomeCare actually does.
            </p>
            <div class="mt-12 flex flex-col items-center justify-center gap-6 md:flex-row">
                <a href="{{ route('register') }}" class="w-full rounded-[2rem] bg-slate-950 px-16 py-7 text-2xl font-black uppercase italic tracking-tight text-white shadow-2xl transition-all hover:scale-[1.02] hover:bg-blue-600 md:w-auto">
                    Get Started Free
                </a>
            </div>
            <p class="mt-8 text-sm font-black uppercase tracking-[0.2em] text-slate-400">
                Built for families arranging non-medical support at home
            </p>
        </div>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                }
            });
        }, {
            threshold: 0.15,
        });

        document.querySelectorAll('.reveal').forEach((element) => observer.observe(element));
    });
</script>
