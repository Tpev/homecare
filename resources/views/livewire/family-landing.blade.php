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

    $defaultPodcastFile = 'How_HomeCare_fixes_senior_care.m4a';

    $podcastAudioUrl = filled($podcast['audio_url'] ?? null)
        ? $podcast['audio_url']

        : asset($defaultPodcastFile);

    $podcastTitle = $podcast['episode_title'] ?? 'How HomeCare fixes senior care';

    $podcastSummary = $podcast['episode_summary'] ?? ($podcast['description'] ?? 'Listen to the HomeCare podcast directly from the homepage.');
    $podcastLinks = array_values(array_filter([

        ['label' => 'Spotify', 'url' => $podcast['spotify_url'] ?? null],

        ['label' => 'Apple Podcasts', 'url' => $podcast['apple_url'] ?? null],

        ['label' => 'YouTube', 'url' => $podcast['youtube_url'] ?? null],

        ['label' => 'Transcript', 'url' => $podcast['transcript_url'] ?? null],

    ], fn (array $link): bool => filled($link['url'])));

@endphp



<div class="hub-family-landing overflow-x-hidden text-slate-900">

    <nav class="glass-nav fixed top-0 z-50 w-full">

        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:h-20 sm:px-6">

            <a href="{{ route('landing') }}" class="flex items-center gap-3">

                <div class="flex h-10 w-10 items-center justify-center rounded-2xl border border-[#DED6CA] bg-[#FFFCF8] shadow-lg sm:h-11 sm:w-11"><x-application-logo class="h-5 w-5 text-[#0F3D3E]" /></div>

                <div>
                    <span class="block text-lg font-extrabold tracking-tight text-slate-950 sm:text-xl">Home Care <span class="text-blue-600">HUB</span></span>
                    <span class="hidden text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500 sm:block sm:text-[11px]">Thoughtful care. Smarter by design.</span>
                </div>

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

                <a href="{{ route('login') }}" class="min-w-[92px] rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700">

                    Sign in

                </a>

            </div>

        </div>

    </nav>



    <section class="bg-grid hero-gradient px-4 pb-16 pt-28 sm:px-6 sm:pb-24 sm:pt-40">

        <div class="mx-auto max-w-7xl">
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.05fr)_minmax(420px,0.95fr)] xl:items-start">

            <div class="hub-hero-card reveal active mt-2 rounded-[2rem] p-5 text-left backdrop-blur sm:mt-0 sm:p-8 xl:p-10">

                <p class="hub-hero-eyebrow text-[11px] font-extrabold sm:text-xs">
                    Support for older adults at home
                </p>

                <h1 class="mt-3 max-w-[12ch] text-balance text-[2.05rem] leading-[1] tracking-[-0.05em] text-slate-950 sm:mt-5 sm:max-w-3xl sm:text-center sm:text-5xl xl:max-w-[11ch] xl:text-left xl:text-[4.35rem]">

                    Trusted help for mom or dad, right when you need it.

                </h1>

                <p class="mt-4 max-w-2xl text-[1rem] font-medium leading-7 text-slate-600 sm:mx-auto sm:mt-5 sm:text-center sm:text-[1.08rem] sm:leading-relaxed sm:text-slate-500 xl:mx-0 xl:text-left">

                    Home Care HUB helps families arrange trusted, non-medical support at home for an older parent, from a quick 30-minute visit to a few focused hours or more complete daytime coverage.

                </p>

                <div class="mt-5 grid gap-2.5 sm:mt-6 sm:grid-cols-3 xl:mt-8">

                    <div class="hub-range-card rounded-[1.35rem] px-4 py-3">
                        <strong class="text-sm">30-minute quick help</strong>
                        <span class="mt-1 block text-xs font-medium text-slate-500">Check-ins, reassurance, and a specific need handled fast.</span>
                    </div>

                    <div class="hub-range-card rounded-[1.35rem] px-4 py-3">
                        <strong class="text-sm">A few hours of support</strong>
                        <span class="mt-1 block text-xs font-medium text-slate-500">Companionship, meals, errands, and steady help during the day.</span>
                    </div>

                    <div class="hub-range-card rounded-[1.35rem] px-4 py-3">
                        <strong class="text-sm">Traditional full coverage</strong>
                        <span class="mt-1 block text-xs font-medium text-slate-500">Longer blocks when your family needs broader daytime support.</span>
                    </div>

                </div>

                <div class="mt-5 flex flex-col items-start gap-3 sm:mt-6 sm:items-center xl:items-start">
                    <a href="tel:9844004008" class="hub-contact-chip inline-flex min-h-12 items-center justify-center rounded-[1.1rem] px-5 text-sm font-bold transition-all hover:bg-[#7C5DDC]/15 sm:min-h-11">

                        Call or text (984) 400-4008

                    </a>
                    <p class="text-sm font-medium text-slate-500 sm:text-center xl:text-left">
                        For families who need trusted support quickly, without agency runaround.
                    </p>
                </div>

                <div class="hub-trust-row mt-5 sm:mt-6">
                    <div class="hub-proof rounded-2xl px-4 py-3 text-left text-xs font-semibold sm:text-sm">Screened and reviewed caregivers</div>
                    <div class="hub-proof rounded-2xl px-4 py-3 text-left text-xs font-semibold sm:text-sm">Flexible support from short visits to longer coverage</div>
                    <div class="hub-proof rounded-2xl px-4 py-3 text-left text-xs font-semibold sm:text-sm">Secure booking and payment flow</div>
                </div>

            </div>



            <div class="hub-request-stack mt-2 xl:mt-0">

                <div class="reveal order-1 xl:order-1">
                    <div class="mb-3 hidden xl:flex xl:items-center xl:justify-between xl:px-1">
                        <div>
                            <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-[#7C5DDC]">Start in minutes</p>
                            <p class="mt-1 text-sm font-semibold text-slate-500">Share the basics now. We will guide the rest step by step.</p>
                        </div>
                        <a href="tel:9844004008" class="text-sm font-bold text-[#7C5DDC] hover:text-[#0F3D3E]">Call (984) 400-4008</a>
                    </div>

                    <livewire:family.homepage-quick-request />

                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-center xl:justify-start">

                        <a href="{{ route('caregivers.search') }}" class="inline-flex min-h-12 items-center justify-center rounded-[1.1rem] border border-slate-200 bg-white px-6 text-sm font-bold text-slate-900 transition-all hover:bg-slate-50">

                            Browse caregivers first

                        </a>

                        <a href="{{ route('login') }}" class="inline-flex min-h-12 items-center justify-center rounded-[1.1rem] text-sm font-semibold text-slate-600 transition-all hover:text-slate-900">

                            Already started? Sign in

                        </a>

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

                    </p>

                    <p class="mt-4 max-w-2xl text-sm font-semibold leading-6 text-slate-500">
                        Think of it like Uber, but built around trusted home support for older adults.
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

                            Families can start with a short visit, book a few focused hours, or arrange broader daytime support depending on what the week looks like.

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

                        {{ $podcastTitle }}

                    </h2>

                    <p class="mt-4 font-medium text-slate-400">

                        {{ $podcastSummary }}

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

                    @elseif (filled($podcastAudioUrl))

                        <div class="mt-8 rounded-[1.5rem] bg-white p-4">

                            <audio controls preload="none" class="w-full">

                                <source src="{{ $podcastAudioUrl }}">

                                Your browser does not support the audio element.

                            </audio>

                        </div>

                    @endif



                    <div class="mt-8 flex flex-wrap items-center gap-4">

                        @foreach ($podcastLinks as $link)

                            <a href="{{ $link['url'] }}" class="flex items-center gap-3 rounded-2xl {{ $loop->first ? 'bg-white text-slate-900' : 'border border-slate-700 bg-slate-800 text-white' }} px-8 py-4 font-bold transition-all hover:bg-blue-50 hover:text-slate-900">

                                {{ $link['label'] }}

                            </a>

                        @endforeach



                        @if (filled($podcastAudioUrl))

                            <a href="{{ $podcastAudioUrl }}" class="flex items-center gap-3 rounded-2xl border border-slate-700 bg-slate-800 px-8 py-4 font-bold text-white transition-all hover:bg-slate-700">

                                Open Audio

                            </a>

                        @endif

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

                    Distance should not mean disconnect. The right experience gives families better visibility and fewer surprises.

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

                                <span class="text-xl font-black uppercase italic tracking-tight">Home Care <span class="text-blue-600">HUB</span></span>

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

        

    </section>



    <section class="bg-slate-50 px-6 py-24">

        <div class="mx-auto max-w-7xl">

            <div class="mb-16 text-center">

                <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-[#7C5DDC] sm:text-xs">
                    Trusted caregivers
                </p>

                <h2 class="mt-3 text-4xl tracking-tight text-slate-950 md:text-5xl">

                    Empathy you can trust.

                </h2>

                <p class="mx-auto mt-4 max-w-2xl text-base font-medium leading-7 text-slate-500 md:text-lg">

                    Local support should feel capable, warm, and dependable.

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

                            <div class="text-xs text-yellow-400">5-star <span class="ml-1 text-slate-400">(Verified)</span></div>

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



    <section id="safety" class="relative overflow-hidden bg-blue-600 py-20 text-white">

        <div class="mx-auto grid max-w-7xl gap-10 px-6 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">

            <div class="reveal">

                <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-blue-100">Built-in protection</p>

                <h2 class="mt-3 text-3xl tracking-tight text-white md:text-5xl">Safety is not optional.</h2>

                <p class="mt-4 max-w-2xl text-base font-medium leading-7 text-blue-100">

                    Families need to understand what protections and communication tools are built into the experience.

                </p>

            </div>



            <div class="reveal grid gap-4 sm:grid-cols-2">

                @foreach ($trustSignals as $signal)

                    <div class="rounded-[1.5rem] border border-white/15 bg-[#FFFCF8] px-5 py-5 text-left shadow-[0_18px_40px_-24px_rgba(0,0,0,0.28)]">

                        <div class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-[#0F3D3E]">{{ $signal }}</div>

                    </div>

                @endforeach

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

                Get support in place faster, stay informed, and spend less energy managing every moving part alone.

            </p>

            <div class="mt-12 flex flex-col items-center justify-center gap-6 md:flex-row">

                <a href="{{ route('register') }}" class="w-full rounded-[2rem] bg-slate-950 px-16 py-7 text-2xl font-black uppercase italic tracking-tight text-white shadow-2xl transition-all hover:scale-[1.02] hover:bg-blue-600 md:w-auto">

                    Get Started Free

                </a>

            </div>

        </div>

    </section>
</div>


