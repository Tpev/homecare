@php
    $heroImage = asset('images/marketing/homepage/hero-care.jpg');
    $caregiverImage = asset('images/marketing/homepage/caregiver-1.jpg');
    $familiesImage = asset('images/marketing/homepage/families.jpg');
    $caregiversSideImage = asset('images/marketing/homepage/caregivers-side.jpg');
    $humanMomentImage = asset('images/marketing/homepage/human-moment.jpg');
    $podcastImage = asset('images/marketing/homepage/podcast.jpg');

    $proofItems = [
        ['value' => '4.9', 'label' => 'rating'],
        ['value' => 'Background-checked', 'label' => 'caregivers'],
        ['value' => '12k+', 'label' => 'families'],
    ];

    $audiences = [
        [
            'eyebrow' => 'For families',
            'title' => 'Help your loved one, today.',
            'body' => 'Book trusted support quickly, for one visit or recurring help at home.',
            'image' => $familiesImage,
            'items' => ['Find help fast', 'Flexible scheduling', 'Real, vetted caregivers'],
            'button' => ['label' => 'Find Care', 'href' => '#quick-request'],
            'dark' => false,
        ],
        [
            'eyebrow' => 'For caregivers',
            'title' => 'Work that fits your week.',
            'body' => 'Choose nearby opportunities, build trust, and work around your life.',
            'image' => $caregiversSideImage,
            'items' => ['Flexible work', 'Choose your schedule', 'Get booked faster'],
            'button' => ['label' => 'Join as Caregiver', 'href' => route('landing.caregiver')],
            'dark' => true,
        ],
    ];

    $valueCards = [
        [
            'title' => 'Care you can trust',
            'body' => 'Every caregiver is reviewed and checked before families make a decision.',
            'icon' => 'shield',
        ],
        [
            'title' => 'Book in minutes',
            'body' => 'Tell us what you need. Get matched with available caregivers in your area, fast.',
            'icon' => 'bolt',
        ],
        [
            'title' => 'Stay connected',
            'body' => 'Message your caregiver, manage schedules, and share updates with family.',
            'icon' => 'message',
        ],
    ];

    $liveSignals = [
        ['label' => 'Available now', 'value' => 'Maya R. · Companion care'],
        ['label' => '4.9 family rating', 'value' => 'Verified profile'],
        ['label' => 'Typical response', 'value' => 'Within minutes'],
    ];

    $scheduleRows = [
        ['day' => 'Mon', 'time' => '9:00 AM', 'name' => 'Companion visit'],
        ['day' => 'Wed', 'time' => '1:30 PM', 'name' => 'Meal prep + check-in'],
        ['day' => 'Fri', 'time' => '4:00 PM', 'name' => 'Errands and support'],
    ];

    $steps = [
        ['number' => '01', 'title' => 'Tell us what you need', 'body' => 'Service type, location, and when you need it.'],
        ['number' => '02', 'title' => 'Get matched fast', 'body' => 'We surface available, vetted caregivers near you.'],
        ['number' => '03', 'title' => 'Book and manage care', 'body' => 'Confirm, message, and adjust — all in one place.'],
    ];

    $differenceRows = [
        ['title' => 'No long-term commitments', 'body' => 'Book one visit or a hundred. You decide.'],
        ['title' => 'No intake delays', 'body' => 'Get matched in minutes, not days.'],
        ['title' => 'Care on your schedule', 'body' => 'Mornings, evenings, weekends — flex as life shifts.'],
    ];

    $stats = [
        ['value' => '4.9', 'label' => 'Average rating', 'sub' => 'from 12,000+ reviews'],
        ['value' => '100%', 'label' => 'Background-checked', 'sub' => 'every caregiver, every time'],
        ['value' => '12k+', 'label' => 'Loved by families', 'sub' => 'across 40+ cities'],
    ];
@endphp

<div class="hub-home overflow-x-hidden">
    <nav class="hub-nav">
        <div class="hub-container flex items-center justify-between gap-4 py-4 md:py-5">
            <a href="{{ route('landing') }}" class="flex items-center gap-3">
                <span class="hub-logo-mark">
                    <span class="hub-logo-letter">h</span>
                </span>
                <span class="flex flex-col leading-none">
                    <span class="hub-brand-text">HomeCare <span>HUB</span></span>
                </span>
            </a>

            <div class="hub-desktop-nav hidden items-center gap-7 md:flex">
                <a class="hub-nav-link" href="#how">How It Works</a>
                <a class="hub-nav-link" href="#caregivers">Caregivers</a>
                <a class="hub-nav-link" href="#contact">Contact</a>
            </div>

            <div class="flex items-center gap-2 md:gap-3">
                <a href="#quick-request" class="hub-button-primary hub-nav-cta">Find Care Now</a>
            </div>
        </div>
    </nav>

    <section class="hub-hero">
        <div class="hub-container hub-hero-grid">
            <div class="hub-hero-copy">
                <span class="hub-pill"><span class="hub-dot"></span> Now booking in your area</span>
                <h1 class="hub-hero-title">Care when you<br>need it <em>most.</em></h1>
                <p class="hub-hero-body">
                    Simple scheduling. Trusted caregivers. Peace of mind — delivered to your door in minutes.
                </p>

                <div class="hub-hero-actions">
                    <a href="#quick-request" class="hub-button-primary">Find Care Now
                        <svg viewBox="0 0 20 20" fill="none" class="h-4 w-4" aria-hidden="true"><path d="M5 10h10M11 4l4 6-4 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <a href="#how" class="hub-button-secondary">How it works
                        <svg viewBox="0 0 20 20" fill="none" class="h-3.5 w-3.5" aria-hidden="true"><path d="M5 10h10M11 4l4 6-4 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                </div>

                <div class="hub-hero-proofline mt-6 md:mt-8">
                    @foreach ($proofItems as $proof)
                        <div class="hub-proof-inline">
                            <strong>{{ $proof['value'] }}</strong>
                            <span>{{ $proof['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="hub-hero-visual">
                <div class="hub-quick-wrap">
                    <livewire:family.homepage-quick-request />
                </div>

                <div class="hub-mini-toast">
                    <span class="hub-mini-avatar">SM</span>
                    <div>
                        <p>Sarah just booked</p>
                        <span>2 minutes ago</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="hub-section hub-section-soft hub-support-section">
        <div class="hub-container text-center">
            <p class="hub-kicker">A quieter approach</p>
            <h2 class="hub-section-title">Support that<br>fits <em>your life.</em></h2>
            <p class="hub-section-text mx-auto">Care that adapts to you — not the other way around.</p>
        </div>
    </section>

    <section id="experience" class="hub-section hub-section-alt hub-section-anchor hub-experience-section">
        <div class="hub-container hub-live-grid">
            <div class="hub-experience-copy">
                <p class="hub-kicker">Live experience</p>
                <h2 class="hub-section-title">Real people. Real <em>availability.</em></h2>
                <p class="hub-section-text">See who's free near you, right now. Browse profiles, read reviews, and book in a few taps.</p>
            </div>

            <div class="hub-live-stack">
                <div class="hub-surface-card hub-profile-card">
                    <img src="{{ $caregiverImage }}" alt="Featured caregiver profile photo." loading="lazy">
                    <div class="mt-4 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-lg font-bold text-[var(--hub-deep)]">Maya R.</p>
                            <p class="mt-1 text-sm text-[var(--hub-copy-soft)]">Companion care · Meal support · 6 years</p>
                        </div>
                        <div class="rounded-full bg-[rgba(124,93,220,0.12)] px-3 py-2 text-xs font-bold text-[var(--hub-lavender)]">4.9</div>
                    </div>
                    <div class="mt-4 space-y-2 text-sm">
                        @foreach ($liveSignals as $signal)
                            <div class="hub-visit-row rounded-[1rem] bg-[rgba(246,239,230,0.8)] px-3 py-3">
                                <span class="text-[var(--hub-copy-soft)]">{{ $signal['label'] }}</span>
                                <span class="font-semibold text-[var(--hub-deep)]">{{ $signal['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="hub-surface-card hub-visit-card">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/12">
                            <svg viewBox="0 0 20 20" fill="none" class="h-5 w-5" aria-hidden="true"><path d="M4 10.5l3.2 3.2L16 5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-white/65">Confirmed</p>
                            <p class="mt-1 text-xl font-semibold text-white">Booking #4821</p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3 text-sm text-white/82">
                        <div class="hub-visit-row"><span>When</span><strong class="text-white">Today, 4 PM</strong></div>
                        <div class="hub-visit-row"><span>Caregiver</span><strong class="text-white">Maya R.</strong></div>
                        <div class="hub-visit-row"><span>Support</span><strong class="text-white">Companion care</strong></div>
                    </div>

                    <p class="mt-5 text-xs font-semibold uppercase tracking-[0.16em] text-[rgba(255,255,255,0.64)]">Maya is on her way · ETA 22 min</p>
                </div>

                <div class="hub-surface-card hub-week-card">
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[var(--hub-lavender)]">This week</p>
                    <div class="mt-3">
                        @foreach ($scheduleRows as $row)
                            <div class="hub-week-item">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-[0.9rem] bg-[var(--hub-deep)] text-xs font-bold text-white">{{ $row['day'] }}</span>
                                    <div>
                                        <p class="text-sm font-semibold text-[var(--hub-deep)]">{{ $row['time'] }}</p>
                                        <p class="text-xs text-[var(--hub-copy-soft)]">{{ $row['name'] }}</p>
                                    </div>
                                </div>
                                <span class="hub-inline-icon">✓</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="caregivers" class="hub-section hub-section-anchor">
        <div class="hub-container hub-audience-grid">
            @foreach ($audiences as $audience)
                <article class="hub-audience-card {{ $audience['dark'] ? 'hub-audience-card-dark' : '' }}">
                    <img src="{{ $audience['image'] }}" alt="{{ $audience['title'] }}" loading="lazy">
                    <div class="p-6 md:p-8">
                        <p class="hub-kicker" style="color: {{ $audience['dark'] ? 'rgba(255,255,255,0.72)' : 'var(--hub-lavender)' }}">{{ $audience['eyebrow'] }}</p>
                        <h3 class="mt-2 text-[2rem] leading-[1] {{ $audience['dark'] ? 'text-white' : '' }}">{{ $audience['title'] }}</h3>
                        <p class="mt-4 text-sm leading-7 {{ $audience['dark'] ? 'text-white/74' : 'text-[var(--hub-copy)]' }}">{{ $audience['body'] }}</p>
                        <ul class="mt-5 space-y-2 text-sm {{ $audience['dark'] ? 'text-white/82' : 'text-[var(--hub-copy)]' }}">
                            @foreach ($audience['items'] as $item)
                                <li class="flex items-center gap-2"><span class="hub-inline-icon">•</span>{{ $item }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ $audience['button']['href'] }}" class="{{ $audience['dark'] ? 'hub-button-ghost mt-6 !bg-white !text-[var(--hub-deep)]' : 'hub-button-primary mt-6' }}">{{ $audience['button']['label'] }}</a>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="hub-section hub-section-soft hub-values-section">
        <div class="hub-container">
            <div class="max-w-2xl">
                <p class="hub-kicker">Why Home Care HUB</p>
                <h2 class="hub-section-title">Built around the moments <em>that matter.</em></h2>
            </div>
            <div class="hub-value-grid mt-8 md:mt-12">
                @foreach ($valueCards as $index => $card)
                    <article class="hub-value-card {{ $index === 1 ? 'hub-value-shift-1' : ($index === 2 ? 'hub-value-shift-2' : '') }}">
                        <span class="hub-value-icon">
                            @if ($card['icon'] === 'shield')
                                <svg viewBox="0 0 20 20" fill="none" class="h-5 w-5" aria-hidden="true"><path d="M10 2l6 2.5v4.9c0 3.6-2.3 6.7-6 8.6-3.7-1.9-6-5-6-8.6V4.5L10 2z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M7.5 10.1l1.5 1.5 3.4-3.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            @elseif ($card['icon'] === 'bolt')
                                <svg viewBox="0 0 20 20" fill="none" class="h-5 w-5" aria-hidden="true"><path d="M11.8 1.8L5.6 10h3.3L8.2 18.2 14.4 10h-3.3l.7-8.2z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                            @else
                                <svg viewBox="0 0 20 20" fill="none" class="h-5 w-5" aria-hidden="true"><path d="M4 5.8A1.8 1.8 0 015.8 4h8.4A1.8 1.8 0 0116 5.8v5.5a1.8 1.8 0 01-1.8 1.8H9.6L6 16v-2.9H5.8A1.8 1.8 0 014 11.3V5.8z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                            @endif
                        </span>
                        <h3 class="text-[1.85rem] leading-[1.05]">{{ $card['title'] }}</h3>
                        <p class="mt-3 text-sm leading-7 text-[var(--hub-copy)]">{{ $card['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="hub-human">
        <img src="{{ $humanMomentImage }}" alt="Caregiver and older adult laughing together in a warm kitchen." loading="lazy">
        <div class="hub-container hub-human-copy">
            <div class="max-w-2xl">
                <h2 class="hub-human-title">It’s not just care.<br><em class="hub-highlight" style="color:#f6efe6;">It’s connection.</em></h2>
            </div>
        </div>
    </section>

    <section id="how" class="hub-section hub-section-anchor hub-how-section">
        <div class="hub-container hub-how-layout">
            <div>
                <p class="hub-kicker">How it works</p>
                <h2 class="hub-section-title">Three steps. <em>That’s it.</em></h2>
                <p class="hub-section-text">No phone trees, no back-and-forth intake loop. Just a clear path from “we need help” to actual support at home.</p>
                <div class="hub-how-grid mt-8 md:mt-10 !grid-cols-1 md:!grid-cols-3">
                    @foreach ($steps as $step)
                        <article class="hub-how-card">
                            <p class="hub-step-no">{{ $step['number'] }}</p>
                            <h3 class="mt-3 text-[1.5rem] leading-[1.08]">{{ $step['title'] }}</h3>
                            <p class="mt-3 text-sm leading-7 text-[var(--hub-copy)]">{{ $step['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>

            <div id="guidance" class="hub-podcast-card hub-section-anchor">
                <div class="hub-podcast-image">
                    <img src="{{ $podcastImage }}" alt="Podcast cover art for Home Care HUB guidance." loading="lazy">
                    <div class="hub-podcast-play">
                        <span class="hub-play-circle">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5" aria-hidden="true"><path d="M7 5.5v9l7-4.5-7-4.5z"/></svg>
                        </span>
                    </div>
                </div>
                <div class="p-6 md:p-8">
                    <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-white/62">Episode 14 · 28 min</p>
                    <h3 class="mt-3 text-[2rem] leading-[1] text-white">The first conversation about care</h3>
                    <p class="mt-4 text-sm leading-7 text-white/74">How to talk to your parents about getting help — without the guilt, the awkwardness, or the script.</p>
                    <blockquote class="mt-6 border-l-2 border-[rgba(201,184,255,0.55)] pl-4 text-base italic text-white/82">“This helped us understand what to expect.”</blockquote>
                    <p class="mt-2 text-xs font-semibold uppercase tracking-[0.16em] text-white/48">Marisol, daughter & caregiver</p>
                    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                        <a href="#" class="hub-button-secondary !bg-white !text-[var(--hub-deep)] !border-transparent">Listen now</a>
                        <a href="#" class="hub-button-ghost !border-white/14 !bg-white/10 !text-white">All episodes</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="hub-section hub-section-dark hub-difference-section">
        <div class="hub-container hub-live-grid">
            <div>
                <p class="hub-kicker" style="color:rgba(201,184,255,0.78)">The difference</p>
                <h2 class="hub-section-title hub-dark-title">Not your typical <em>agency.</em></h2>
            </div>
            <div>
                @foreach ($differenceRows as $row)
                    <div class="hub-diff-row">
                        <span class="hub-plus">+</span>
                        <div>
                            <h3 class="text-[1.65rem] leading-[1.05] text-white">{{ $row['title'] }}</h3>
                            <p class="mt-2 text-sm leading-7 text-white/64">{{ $row['body'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="hub-section hub-section-alt">
        <div class="hub-container hub-stat-grid">
            @foreach ($stats as $stat)
                <article class="hub-stat-card">
                    <strong>{{ $stat['value'] }}</strong>
                    <p class="mt-4 text-sm font-semibold text-[var(--hub-deep)]">{{ $stat['label'] }}</p>
                    <p class="mt-1 text-xs text-[var(--hub-copy-soft)]">{{ $stat['sub'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="hub-section hub-final-section">
        <div class="hub-container hub-final">
            <h2 class="hub-final-title">Get help <em>today.</em></h2>
            <p class="hub-section-text mx-auto">Start the request, bring calm back into the plan, and get the right support moving faster.</p>
            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="#quick-request" class="hub-button-primary">Find care now</a>
                <a href="tel:9844004008" class="hub-button-ghost">Call (984) 400-4008</a>
            </div>
        </div>
    </section>
</div>
