@php
    $steps = [
        [
            'number' => '01',
            'title' => 'Post your request',
            'body' => 'Tell us where care is needed, what kind of help would make the day easier, and when you want support to begin.',
        ],
        [
            'number' => '02',
            'title' => 'Start receiving responses',
            'body' => 'Available caregivers can respond quickly once your request is live, so you can start reviewing options without the usual callback delays.',
        ],
        [
            'number' => '03',
            'title' => 'Review, chat, and choose',
            'body' => 'Compare caregiver profiles, message directly, ask questions, and select the person who feels like the right fit.',
        ],
        [
            'number' => '04',
            'title' => 'Get care started',
            'body' => 'Confirm the visit, keep the family aligned, and stay informed as support begins at home.',
        ],
    ];

    $services = [
        [
            'title' => 'Companionship',
            'body' => 'Warm conversation, check-ins, reading, games, and supportive presence that helps reduce isolation and makes the day feel lighter.',
        ],
        [
            'title' => 'Mobility support',
            'body' => 'Help with walking, standing, steady movement around the home, and support with routines that call for extra care and attention.',
        ],
        [
            'title' => 'Meal support',
            'body' => 'Simple meal preparation, snacks, reheating food, basic kitchen setup, and cleanup that helps keep mealtimes easier and more consistent.',
        ],
        [
            'title' => 'Light housekeeping',
            'body' => 'Tidying, dishes, laundry folding, wiping surfaces, and small home tasks that keep the environment calmer, cleaner, and safer.',
        ],
        [
            'title' => 'Dressing and grooming',
            'body' => 'Help with getting dressed, brushing hair, simple grooming routines, and personal appearance support that keeps someone comfortable and confident.',
        ],
        [
            'title' => 'Light personal assistance',
            'body' => 'Non-medical support such as hydration prompts, toileting reminders, and routine assistance that helps the day stay on track.',
        ],
        [
            'title' => 'Activities and engagement',
            'body' => 'Puzzles, music, crafts, conversation, and familiar activities that support focus, routine, and emotional well-being.',
        ],
        [
            'title' => 'Pet help',
            'body' => 'Support with feeding, walking, and small pet routines so beloved animals stay part of the daily rhythm.',
        ],
        [
            'title' => 'Medication reminders',
            'body' => 'Reminders for pre-filled medications and time-based routines that help care recipients stay organized and on schedule.',
        ],
        [
            'title' => 'Errands',
            'body' => 'Grocery pickup, prescription runs, mailing packages, and day-to-day errands that are hard to manage alone.',
        ],
        [
            'title' => 'Technology help',
            'body' => 'Assistance with video calls, streaming, phone basics, photos, and everyday tech tasks that can otherwise become frustrating.',
        ],
    ];

    $trustSignals = [
        'Non-medical home care support',
        'Direct caregiver messaging',
        'Secure checkout flow',
        'Identity verification before work begins',
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

    .podcast-card {
        background: linear-gradient(135deg, #111827 0%, #0f172a 100%);
    }

    .service-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .service-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 24px 40px -24px rgba(15, 23, 42, 0.18);
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
                <a href="#how-it-works" class="transition-colors hover:text-blue-600">How it works</a>
                <a href="#services" class="transition-colors hover:text-blue-600">Services</a>
                <a href="#podcast" class="transition-colors hover:text-blue-600">Podcast</a>
                <a href="#safety" class="transition-colors hover:text-blue-600">Safety</a>
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
                    Flexible non-medical support for families caring for parents in Raleigh
                </div>

                <h1 class="mx-auto max-w-5xl text-5xl font-black leading-[0.9] tracking-[-0.05em] text-slate-900 md:text-[5.4rem]">
                    Post a request.<br>
                    Get responses.<br>
                    Choose help and get going.
                </h1>

                <p class="mx-auto mt-8 max-w-3xl text-xl font-medium leading-relaxed text-slate-500">
                    HomeCare helps families arrange non-medical support at home without the usual agency black box.
                    Share the need, review responding caregivers, chat directly, and start care with better visibility.
                </p>

                <div class="mt-12 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <a href="{{ route('register') }}" class="w-full rounded-[1.25rem] bg-slate-950 px-12 py-5 text-lg font-bold text-white shadow-xl transition-all hover:bg-blue-600 sm:w-auto">
                        Post a Care Request
                    </a>
                    <a href="#how-it-works" class="w-full rounded-[1.25rem] border border-slate-200 bg-white px-12 py-5 text-lg font-bold text-slate-900 transition-all hover:bg-slate-50 sm:w-auto">
                        See How It Works
                    </a>
                </div>
            </div>

            <div class="mt-16 grid gap-6 lg:grid-cols-[1.15fr_0.85fr] lg:items-center">
                <div class="reveal rounded-[2rem] border border-blue-100 bg-white p-8 shadow-[0_30px_70px_-28px_rgba(59,130,246,0.24)]">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-[1.4rem] border border-slate-100 bg-slate-50 p-4">
                            <div class="text-[11px] font-black uppercase tracking-[0.16em] text-blue-600">Step one</div>
                            <p class="mt-3 text-sm font-semibold leading-6 text-slate-700">Post the schedule, tasks, and context for your loved one.</p>
                        </div>
                        <div class="rounded-[1.4rem] border border-slate-100 bg-slate-50 p-4">
                            <div class="text-[11px] font-black uppercase tracking-[0.16em] text-blue-600">Step two</div>
                            <p class="mt-3 text-sm font-semibold leading-6 text-slate-700">Review responses and message caregivers directly.</p>
                        </div>
                        <div class="rounded-[1.4rem] border border-slate-100 bg-slate-50 p-4">
                            <div class="text-[11px] font-black uppercase tracking-[0.16em] text-blue-600">Step three</div>
                            <p class="mt-3 text-sm font-semibold leading-6 text-slate-700">Choose the caregiver and begin support at home.</p>
                        </div>
                    </div>
                </div>

                <div class="reveal rounded-[2.4rem] border border-slate-900 bg-slate-950 p-7 text-white shadow-2xl">
                    <div class="rounded-[1.5rem] border border-white/10 bg-white/5 p-5">
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-300">Family view</p>
                                <p class="mt-1 text-lg font-black tracking-tight">Request, review, hire</p>
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
                                    <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-blue-600">Responded to your request</p>
                                </div>
                            </div>

                            <div class="mt-4 space-y-2">
                                <div class="flex items-center gap-2 text-[11px] font-bold text-slate-500">
                                    <span class="h-2 w-2 rounded-full bg-green-500"></span>
                                    Available for tomorrow morning
                                </div>
                                <div class="flex items-center gap-2 text-[11px] font-bold text-slate-500">
                                    <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                                    Meal support, errands, companionship
                                </div>
                            </div>

                            <button type="button" class="mt-4 w-full rounded-xl border border-blue-200 bg-blue-50 py-3 text-xs font-black uppercase tracking-[0.16em] text-blue-700">
                                Review profile
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 rounded-[1.5rem] border border-white/10 bg-white/5 p-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Built for family clarity</p>
                        <p class="mt-2 text-sm font-semibold leading-6 text-slate-200">
                            One request, one place to compare caregivers, one clearer path to getting help started.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="bg-white px-6 py-24">
        <div class="mx-auto max-w-7xl">
            <div class="mb-12 max-w-3xl">
                <h2 class="text-4xl font-black uppercase italic tracking-tight text-slate-950 md:text-5xl">
                    How HomeCare works.
                </h2>
                <p class="mt-6 text-lg font-medium leading-relaxed text-slate-500">
                    The system is simple: post what help is needed, start hearing back from available caregivers, choose the right fit, and move into care with clearer communication.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($steps as $step)
                    <div class="rounded-[2rem] border border-slate-100 bg-slate-50 p-6">
                        <div class="text-[11px] font-black uppercase tracking-[0.16em] text-blue-600">Step {{ $step['number'] }}</div>
                        <h3 class="mt-3 text-2xl font-black tracking-tight text-slate-950">{{ $step['title'] }}</h3>
                        <p class="mt-4 text-sm font-medium leading-7 text-slate-600">{{ $step['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="services" class="bg-slate-50 px-6 py-24">
        <div class="mx-auto max-w-7xl">
            <div class="mb-12 max-w-4xl">
                <h2 class="text-4xl font-black uppercase italic tracking-tight text-slate-950 md:text-5xl">
                    Support families can actually book.
                </h2>
                <p class="mt-6 text-lg font-medium leading-relaxed text-slate-500">
                    HomeCare is for non-medical support at home. These are the kinds of tasks families can request when they need another reliable person in the plan.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($services as $service)
                    <div class="service-card rounded-[2rem] border border-slate-100 bg-white p-6">
                        <div class="text-[11px] font-black uppercase tracking-[0.16em] text-blue-600">{{ $service['title'] }}</div>
                        <p class="mt-4 text-sm font-medium leading-7 text-slate-600">{{ $service['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="podcast" class="bg-white px-6 py-24">
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
                        {{ $podcast['episode_title'] ?? 'Practical guidance for families arranging care at home.' }}
                    </h2>
                    <p class="mt-4 font-medium text-slate-400">
                        {{ $podcast['episode_summary'] ?? ($podcast['description'] ?? 'Use the podcast to educate families, explain how the system works, and build more trust before they post a request.') }}
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
                    @endif

                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        @foreach ($podcastLinks as $link)
                            <a href="{{ $link['url'] }}" class="flex items-center gap-3 rounded-2xl {{ $loop->first ? 'bg-white text-slate-900' : 'border border-slate-700 bg-slate-800 text-white' }} px-8 py-4 font-bold transition-all hover:bg-blue-50 hover:text-slate-900">
                                {{ $link['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="safety" class="bg-blue-600 px-6 py-16 text-white">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-10 md:flex-row">
            <div class="reveal">
                <h2 class="text-3xl font-black uppercase italic md:text-4xl">Safety and clarity matter.</h2>
                <p class="mt-4 max-w-2xl font-medium text-blue-100">
                    Families need to understand what the platform is, how the communication works, and what protections are built into the experience.
                </p>
            </div>

            <div class="flex flex-wrap gap-3 reveal">
                @foreach ($trustSignals as $signal)
                    <div class="rounded-2xl border border-white/20 bg-white/10 px-5 py-4 text-center">
                        <div class="text-[11px] font-black uppercase tracking-[0.16em] text-white">{{ $signal }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="px-6 py-28 text-center">
        <div class="reveal mx-auto max-w-5xl">
            <h2 class="text-5xl font-black uppercase italic leading-[0.85] tracking-[-0.05em] text-slate-900 md:text-8xl">
                Get help started <br>
                without carrying <span class="text-blue-600 underline decoration-8 underline-offset-10">everything alone.</span>
            </h2>
            <p class="mx-auto mt-8 max-w-2xl text-xl font-medium text-slate-500">
                Post a request, review responses, choose a caregiver, and keep the family informed from one place.
            </p>
            <div class="mt-12 flex flex-col items-center justify-center gap-6 md:flex-row">
                <a href="{{ route('register') }}" class="w-full rounded-[2rem] bg-slate-950 px-16 py-7 text-2xl font-black uppercase italic tracking-tight text-white shadow-2xl transition-all hover:scale-[1.02] hover:bg-blue-600 md:w-auto">
                    Get Started Free
                </a>
            </div>
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
