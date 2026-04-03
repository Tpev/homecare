@php
    $trustPillars = [
        [
            'title' => 'Clear before you commit',
            'body' => 'Families can review caregiver profiles, ask questions, and align on expectations before making a hiring decision.',
        ],
        [
            'title' => 'Built-in trust signals',
            'body' => 'Identity verification, transparent profiles, and secure in-platform communication make the process feel more accountable.',
        ],
        [
            'title' => 'Visibility after care starts',
            'body' => 'Stay informed with shift tracking, payment clarity, and one place for the care conversation.',
        ],
    ];

    $careMoments = [
        'A hospital discharge or rehab return home',
        'A parent who should not be alone all day',
        'Long-distance coordination with local execution',
        'Coverage gaps while family is working or traveling',
    ];

    $workflow = [
        [
            'step' => '01',
            'title' => 'Share the care need',
            'body' => 'Post the schedule, support tasks, care context, and anything a caregiver should know before applying.',
        ],
        [
            'step' => '02',
            'title' => 'Review and message caregivers',
            'body' => 'Compare profiles, trust signals, and fit, then chat directly to narrow the decision with more confidence.',
        ],
        [
            'step' => '03',
            'title' => 'Hire with a clearer plan',
            'body' => 'Confirm timing, expectations, and the support needed so everyone starts on the same page.',
        ],
        [
            'step' => '04',
            'title' => 'Stay informed as care happens',
            'body' => 'Track the shift, review updates, and avoid chasing details through scattered calls and texts.',
        ],
    ];

    $safetyStandards = [
        'Identity verification before work begins',
        'Private in-platform messaging for family coordination',
        'Secure checkout with transparent payment steps',
        'Profile visibility that helps families compare more carefully',
        'Responsive support when plans shift or something feels urgent',
        'A process designed for non-medical home care, not vague marketplace chaos',
    ];

    $faqs = [
        [
            'question' => 'Is HomeCare an agency?',
            'answer' => 'HomeCare is a marketplace built for non-medical home care support. Families can post a request, review caregivers, message directly, and manage the hiring process with more clarity.',
        ],
        [
            'question' => 'What kind of support is this for?',
            'answer' => 'The platform is designed for non-medical support like companionship, check-ins, meal prep, errands, light household help, and day-to-day assistance at home.',
        ],
        [
            'question' => 'Can I use this if I do not live in Raleigh full-time?',
            'answer' => 'Yes. The experience is especially helpful for long-distance family members who still need visibility into the request, caregiver communication, and shift status.',
        ],
        [
            'question' => 'How does the platform help families feel safer?',
            'answer' => 'Families can review trust signals, message inside the platform, understand the payment flow clearly, and keep the care conversation in one place instead of relying on fragmented texts and callbacks.',
        ],
    ];

    $guideLinks = [
        [
            'label' => 'Home care in Raleigh',
            'href' => route('seo.page', ['seoSlug' => 'raleigh-home-care']),
        ],
        [
            'label' => 'Companion care in Raleigh',
            'href' => route('seo.page', ['seoSlug' => 'raleigh-companion-care']),
        ],
        [
            'label' => 'Respite care in Raleigh',
            'href' => route('seo.page', ['seoSlug' => 'raleigh-respite-care']),
        ],
        [
            'label' => 'Home care cost in Raleigh',
            'href' => route('seo.page', ['seoSlug' => 'home-care-cost-raleigh-nc']),
        ],
    ];

    $podcast = config('marketing.podcast', []);
    $podcastLinks = array_values(array_filter([
        ['label' => 'Spotify', 'url' => $podcast['spotify_url'] ?? null],
        ['label' => 'Apple Podcasts', 'url' => $podcast['apple_url'] ?? null],
        ['label' => 'YouTube', 'url' => $podcast['youtube_url'] ?? null],
        ['label' => 'Transcript', 'url' => $podcast['transcript_url'] ?? null],
    ], fn (array $link): bool => filled($link['url'])));

    $hasPodcastPlayer = filled($podcast['embed_url'] ?? null) || filled($podcast['audio_url'] ?? null);
    $showPodcastSection = $hasPodcastPlayer || count($podcastLinks) > 0 || app()->isLocal();
@endphp

<div class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(14,165,233,0.16),_transparent_28%),linear-gradient(180deg,_#f7fafc_0%,_#eef5f7_48%,_#f8fbfd_100%)] text-slate-900">
    <header class="sticky top-0 z-50 border-b border-white/70 bg-white/88 backdrop-blur-xl">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('landing') }}" class="flex items-center gap-3">
                <x-application-logo class="h-10 w-10 text-cyan-800" />
                <div class="leading-tight">
                    <div class="text-lg font-extrabold tracking-tight text-slate-950">HomeCare</div>
                    <div class="text-xs uppercase tracking-[0.18em] text-slate-500">For families</div>
                </div>
            </a>

            <nav class="hidden items-center gap-6 text-sm font-semibold text-slate-600 lg:flex">
                <a href="#why-families-trust" class="transition hover:text-slate-950">Why families trust it</a>
                <a href="#how-it-works" class="transition hover:text-slate-950">How it works</a>
                @if ($showPodcastSection)
                    <a href="#podcast" class="transition hover:text-slate-950">Podcast</a>
                @endif
                <a href="#faq" class="transition hover:text-slate-950">FAQ</a>
            </nav>

            <div class="flex items-center gap-2">
                <a
                    href="{{ route('landing.caregiver') }}"
                    class="hidden min-h-11 items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 sm:inline-flex"
                >
                    Caregivers
                </a>
                <a
                    href="{{ route('login') }}"
                    class="inline-flex min-h-11 items-center rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                >
                    Sign in
                </a>
                <a
                    href="{{ route('register') }}"
                    class="inline-flex min-h-11 items-center rounded-full bg-slate-950 px-5 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
                >
                    Get started
                </a>
            </div>
        </div>
    </header>

    <section class="relative overflow-hidden">
        <div class="absolute inset-x-0 top-0 h-64 bg-[radial-gradient(circle_at_top,_rgba(6,182,212,0.18),_transparent_50%)]"></div>
        <div class="mx-auto grid max-w-7xl gap-10 px-4 pb-14 pt-10 sm:px-6 lg:grid-cols-[1.1fr_0.9fr] lg:px-8 lg:pb-20 lg:pt-16">
            <div class="relative">
                <div class="inline-flex items-center gap-2 rounded-full border border-cyan-200 bg-white/90 px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-cyan-900 shadow-sm">
                    Raleigh families. Non-medical home care. Better clarity.
                </div>

                <h1 class="mt-6 max-w-3xl text-4xl font-black leading-[0.98] tracking-tight text-slate-950 sm:text-5xl lg:text-[4.25rem]">
                    Professional home care support for families making hard decisions fast.
                </h1>

                <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-700 sm:text-xl">
                    HomeCare helps families arrange non-medical support for an aging parent with more confidence:
                    clearer profiles, direct communication, secure checkout, and one place to stay informed.
                </p>

                <div class="mt-8 grid grid-cols-1 gap-3 sm:flex sm:flex-wrap">
                    <a
                        href="{{ route('register') }}"
                        class="inline-flex min-h-14 items-center justify-center rounded-2xl bg-slate-950 px-6 py-4 text-base font-semibold text-white transition hover:bg-slate-800"
                    >
                        Find support for my family
                    </a>
                    <a
                        href="#how-it-works"
                        class="inline-flex min-h-14 items-center justify-center rounded-2xl border border-slate-300 bg-white px-6 py-4 text-base font-semibold text-slate-800 transition hover:border-slate-400 hover:bg-slate-50"
                    >
                        See how the process works
                    </a>
                </div>

                <div class="mt-8 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-white/80 bg-white/85 px-4 py-4 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Designed for</div>
                        <div class="mt-2 text-sm font-semibold text-slate-900">Adult children coordinating care</div>
                    </div>
                    <div class="rounded-2xl border border-white/80 bg-white/85 px-4 py-4 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Best for</div>
                        <div class="mt-2 text-sm font-semibold text-slate-900">Companion care, check-ins, errands, home support</div>
                    </div>
                    <div class="rounded-2xl border border-white/80 bg-white/85 px-4 py-4 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Built around</div>
                        <div class="mt-2 text-sm font-semibold text-slate-900">Trust, communication, and visibility</div>
                    </div>
                </div>

                <div class="mt-8 rounded-[2rem] border border-slate-200/80 bg-white p-5 shadow-[0_24px_80px_rgba(15,23,42,0.08)] sm:p-6">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                        <div class="max-w-2xl">
                            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-800">What credibility looks like on this page</div>
                            <p class="mt-3 text-base leading-7 text-slate-700">
                                Families are not looking for hype. They want to know what the platform actually helps them do,
                                what safeguards are built in, and whether the process will reduce chaos instead of adding more of it.
                            </p>
                        </div>
                        <div class="grid gap-2 text-sm text-slate-700">
                            <div class="rounded-xl bg-slate-50 px-4 py-3">Marketplace for non-medical home care support</div>
                            <div class="rounded-xl bg-slate-50 px-4 py-3">Direct caregiver communication before hiring</div>
                            <div class="rounded-xl bg-slate-50 px-4 py-3">Shift visibility once care begins</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="relative">
                <div class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-[0_30px_90px_rgba(15,23,42,0.12)]">
                    <img
                        src="{{ asset('images/marketing/flyer.png') }}"
                        alt="Family member supporting an older parent at home"
                        class="h-[250px] w-full object-cover object-center sm:h-[340px] lg:h-[410px]"
                    />

                    <div class="grid gap-4 p-5 sm:p-6">
                        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-800">Built for family decision-makers</div>
                            <p class="mt-2 text-sm leading-6 text-emerald-950">
                                The experience is designed to help you compare options, message clearly, and move forward without relying on vague callbacks.
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($careMoments as $moment)
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm font-medium text-slate-700">
                                    {{ $moment }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mt-5 rounded-[2rem] border border-slate-200/80 bg-slate-950 p-6 text-white shadow-[0_20px_70px_rgba(15,23,42,0.18)]">
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-300">For long-distance and local families</div>
                    <p class="mt-3 text-lg font-semibold leading-8">
                        You should not have to coordinate your parent's support through scattered texts, missed calls, and uncertainty.
                    </p>
                    <p class="mt-3 text-sm leading-6 text-slate-300">
                        HomeCare gives families one clearer place to post the need, evaluate caregivers, and keep the care plan moving.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section id="why-families-trust" class="border-y border-slate-200/80 bg-white py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <div class="text-sm font-semibold uppercase tracking-[0.22em] text-slate-500">Why families trust it</div>
                <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                    A more credible homepage starts by sounding like a real care decision, not a generic app.
                </h2>
                <p class="mt-4 text-lg leading-8 text-slate-600">
                    The strongest signals here are practical ones: what the platform is for, how families evaluate caregivers, and how HomeCare reduces uncertainty at every step.
                </p>
            </div>

            <div class="mt-10 grid gap-5 lg:grid-cols-3">
                @foreach ($trustPillars as $pillar)
                    <div class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-6 shadow-sm">
                        <div class="text-lg font-bold tracking-tight text-slate-950">{{ $pillar['title'] }}</div>
                        <p class="mt-3 text-sm leading-7 text-slate-600">{{ $pillar['body'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-10 grid gap-5 lg:grid-cols-[1.05fr_0.95fr]">
                <div class="rounded-[1.75rem] border border-slate-200 bg-slate-950 p-7 text-white">
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-300">What HomeCare is</div>
                    <h3 class="mt-3 text-2xl font-black tracking-tight">A structured way to arrange non-medical care support at home.</h3>
                    <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-300 sm:text-base">
                        Families can post a request, compare caregivers more carefully, and manage the process with clearer communication.
                        That framing is more credible than promising everything to everyone.
                    </p>
                </div>

                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-7 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Helpful framing</div>
                    <ul class="mt-4 space-y-3 text-sm leading-7 text-slate-700">
                        <li>Independent caregivers, not vague anonymous listings</li>
                        <li>Non-medical support, clearly positioned for family needs at home</li>
                        <li>Better communication before hiring and better visibility after care starts</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-[0.8fr_1.2fr]">
                <div class="rounded-[2rem] border border-slate-200 bg-white p-7 shadow-sm">
                    <div class="text-sm font-semibold uppercase tracking-[0.22em] text-cyan-800">How it works</div>
                    <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">A cleaner, more professional journey for families.</h2>
                    <p class="mt-4 text-base leading-7 text-slate-600">
                        This section now makes the workflow easier to scan and easier to trust. It focuses on decisions families actually make, not abstract product language.
                    </p>

                    <div class="mt-6 rounded-[1.5rem] bg-slate-50 p-5">
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Before you hire</div>
                        <ul class="mt-3 space-y-3 text-sm leading-7 text-slate-700">
                            <li>Review caregiver fit, availability, and trust signals</li>
                            <li>Message directly to clarify the care situation</li>
                            <li>Bring other family decision-makers into the conversation with more context</li>
                        </ul>
                    </div>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    @foreach ($workflow as $item)
                        <div class="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm">
                            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-800">Step {{ $item['step'] }}</div>
                            <div class="mt-3 text-xl font-bold tracking-tight text-slate-950">{{ $item['title'] }}</div>
                            <p class="mt-3 text-sm leading-7 text-slate-600">{{ $item['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="bg-slate-950 py-16 text-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-[0.95fr_1.05fr] lg:items-start">
                <div>
                    <div class="text-sm font-semibold uppercase tracking-[0.24em] text-cyan-300">Trust and safety</div>
                    <h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Professional credibility comes from operational detail.</h2>
                    <p class="mt-4 max-w-2xl text-base leading-8 text-slate-300">
                        Families feel safer when the site is explicit about how communication, verification, and payment work.
                        This section brings those signals higher in the page and presents them more clearly.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($safetyStandards as $standard)
                        <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-4 text-sm leading-6 text-slate-200">
                            {{ $standard }}
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    @if ($showPodcastSection)
        <section id="podcast" class="border-y border-slate-200 bg-white py-16">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
                <div>
                    <div class="text-sm font-semibold uppercase tracking-[0.24em] text-cyan-800">
                        {{ $podcast['eyebrow'] ?? 'Podcast for family care decisions' }}
                    </div>
                    <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                        {{ $podcast['title'] ?? 'A podcast section that adds authority to the homepage' }}
                    </h2>
                    <p class="mt-4 text-base leading-8 text-slate-600">
                        {{ $podcast['description'] ?? 'Use this area for short, practical episodes that help families navigate care planning, hiring questions, and support at home.' }}
                    </p>

                    <div class="mt-6 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-5">
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Featured episode</div>
                        <div class="mt-3 text-xl font-bold tracking-tight text-slate-950">
                            {{ $podcast['episode_title'] ?? 'Your latest episode will appear here' }}
                        </div>
                        <p class="mt-3 text-sm leading-7 text-slate-600">
                            {{ $podcast['episode_summary'] ?? 'Once you add a podcast embed URL or audio file, this section becomes a strong credibility and education asset for families landing on the homepage.' }}
                        </p>
                        @if (filled($podcast['episode_length'] ?? null))
                            <div class="mt-4 inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                {{ $podcast['episode_length'] }}
                            </div>
                        @endif
                    </div>

                    @if (count($podcastLinks) > 0)
                        <div class="mt-5 flex flex-wrap gap-3">
                            @foreach ($podcastLinks as $link)
                                <a
                                    href="{{ $link['url'] }}"
                                    class="inline-flex min-h-11 items-center rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800 transition hover:border-slate-400 hover:bg-slate-50"
                                >
                                    {{ $link['label'] }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="rounded-[2rem] border border-slate-200 bg-slate-950 p-5 shadow-[0_30px_90px_rgba(15,23,42,0.16)] sm:p-6">
                    @if (filled($podcast['embed_url'] ?? null))
                        <div class="overflow-hidden rounded-[1.5rem] bg-white">
                            <iframe
                                src="{{ $podcast['embed_url'] }}"
                                title="{{ $podcast['episode_title'] ?? ($podcast['title'] ?? 'HomeCare podcast player') }}"
                                loading="lazy"
                                class="h-[420px] w-full border-0"
                                allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
                            ></iframe>
                        </div>
                    @elseif (filled($podcast['audio_url'] ?? null))
                        <div class="flex h-full flex-col rounded-[1.5rem] bg-white p-6">
                            <div class="flex items-center gap-4">
                                <img
                                    src="{{ asset('images/marketing/logo.png') }}"
                                    alt="HomeCare podcast artwork"
                                    class="h-20 w-20 rounded-2xl border border-slate-200 object-cover shadow-sm"
                                >
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-800">{{ $podcast['eyebrow'] ?? 'Family care podcast' }}</div>
                                    <div class="mt-1 text-xl font-bold tracking-tight text-slate-950">{{ $podcast['episode_title'] ?? 'Latest episode' }}</div>
                                </div>
                            </div>

                            <p class="mt-5 text-sm leading-7 text-slate-600">
                                {{ $podcast['episode_summary'] ?? 'Stream the featured episode directly from the homepage so families can hear your guidance without leaving the site.' }}
                            </p>

                            <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <audio controls preload="none" class="w-full">
                                    <source src="{{ $podcast['audio_url'] }}">
                                    Your browser does not support the audio element.
                                </audio>
                            </div>
                        </div>
                    @else
                        <div class="flex h-full flex-col justify-between rounded-[1.5rem] bg-white p-6">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-800">Podcast player ready</div>
                                <h3 class="mt-3 text-2xl font-black tracking-tight text-slate-950">Add your Spotify embed or audio file to make this section live.</h3>
                                <p class="mt-4 text-sm leading-7 text-slate-600">
                                    The layout is in place for a featured episode, but it needs one real episode source before the player can be shown to visitors.
                                </p>
                            </div>

                            <div class="mt-6 rounded-[1.5rem] border border-dashed border-slate-300 bg-slate-50 p-6 text-sm leading-7 text-slate-500">
                                Suggested sources: Spotify embed URL, Apple-compatible player embed, or a hosted MP3 trailer episode.
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <section class="py-16" id="faq">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-[0.85fr_1.15fr]">
                <div>
                    <div class="text-sm font-semibold uppercase tracking-[0.22em] text-slate-500">Family FAQ</div>
                    <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Questions people ask before trusting a home care platform.</h2>
                    <p class="mt-4 text-base leading-8 text-slate-600">
                        A short FAQ improves credibility because it answers the practical concerns families usually have before they click into sign-up.
                    </p>
                </div>

                <div class="space-y-4">
                    @foreach ($faqs as $faq)
                        <details class="group rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm">
                            <summary class="cursor-pointer list-none text-base font-bold tracking-tight text-slate-950">
                                {{ $faq['question'] }}
                            </summary>
                            <p class="mt-3 text-sm leading-7 text-slate-600">{{ $faq['answer'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="border-y border-slate-200 bg-white py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div class="text-sm font-semibold uppercase tracking-[0.22em] text-slate-500">Resource layer</div>
                    <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">Guides and articles make the homepage feel more established.</h2>
                    <p class="mt-4 max-w-3xl text-base leading-8 text-slate-600">
                        Educational content helps families trust the brand before they are ready to post a request. It also gives the homepage stronger depth and a more professional finish.
                    </p>
                </div>
                <a href="{{ route('blog.index') }}" class="text-sm font-semibold text-cyan-800 transition hover:text-cyan-900">
                    Visit the blog
                </a>
            </div>

            <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($guideLinks as $guide)
                    <a href="{{ $guide['href'] }}" class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-5 text-sm font-semibold text-slate-800 transition hover:border-slate-300 hover:bg-white">
                        {{ $guide['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="rounded-[2rem] bg-[linear-gradient(135deg,_#082f49_0%,_#0f766e_50%,_#16a34a_100%)] p-8 text-white shadow-[0_30px_100px_rgba(8,47,73,0.28)] sm:p-10">
            <div class="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-100">Final call to action</div>
                    <h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                        When your family needs help at home, the next step should feel calmer and clearer.
                    </h2>
                    <p class="mt-4 max-w-3xl text-base leading-8 text-white/85">
                        This version of the homepage is designed to feel more credible because it explains the process better, surfaces trust signals earlier, and gives families more reasons to stay and explore.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:flex sm:flex-wrap lg:justify-end">
                    <a
                        href="{{ route('register') }}"
                        class="inline-flex min-h-14 items-center justify-center rounded-2xl bg-white px-6 py-4 text-base font-semibold text-slate-950 transition hover:bg-slate-100"
                    >
                        Create a family account
                    </a>
                    <a
                        href="{{ route('login') }}"
                        class="inline-flex min-h-14 items-center justify-center rounded-2xl border border-white/25 px-6 py-4 text-base font-semibold text-white transition hover:bg-white/10"
                    >
                        Sign in
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>
