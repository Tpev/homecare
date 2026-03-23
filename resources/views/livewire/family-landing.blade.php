<div class="min-h-screen bg-slate-100 text-slate-900">
    <header class="sticky top-0 z-50 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('landing') }}" class="flex items-center gap-3">
                <x-application-logo class="h-10 w-10 text-cyan-800" />
                <div class="leading-tight">
                    <div class="text-lg font-extrabold tracking-tight">HomeCare</div>
                    <div class="text-xs text-slate-500">For families</div>
                </div>
            </a>
            <div class="flex items-center gap-2">
                <a href="{{ route('landing.caregiver') }}">
                    <x-button color="slate" sm light>Caregivers</x-button>
                </a>
                <a href="{{ route('login') }}">
                    <x-button color="slate" sm light>Sign in</x-button>
                </a>
                <a href="{{ route('register') }}">
                    <x-button color="blue" sm>Create account</x-button>
                </a>
            </div>
        </div>
    </header>

    <section class="mx-auto max-w-6xl px-4 pb-8 pt-10 sm:px-6 lg:px-8 lg:pt-14">
        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl">
            <div class="h-4 bg-gradient-to-r from-cyan-800 via-blue-700 to-emerald-500"></div>
            <div class="grid gap-8 p-6 lg:grid-cols-2 lg:p-8">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-900">
                        HUB HOME CARE | TRUSTED HOME SUPPORT IN RALEIGH, NC
                    </div>
                    <h1 class="mt-4 text-4xl font-black leading-tight tracking-tight sm:text-5xl">
                        Need help caring for your mom or dad?
                    </h1>
                    <p class="mt-4 text-lg text-slate-700">
                        Need support <span class="rounded-full bg-slate-200 px-2 py-0.5 text-sm font-semibold text-slate-900">now</span>?
                        HomeCare helps Raleigh families find
                        <span class="rounded-full bg-cyan-100 px-2 py-0.5 text-sm font-semibold text-cyan-900">trusted</span>
                        caregivers quickly, with
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-sm font-semibold text-emerald-900">affordable</span>
                        options for companionship, meal prep, errands, and daily support.
                    </p>
                    <p class="mt-4 text-base text-slate-600">
                        When you are juggling work, family, and worry, we help bring calm, dignity, and reliable support at home.
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('register') }}">
                            <x-button color="blue" lg icon="heart" position="left">Get trusted help today</x-button>
                        </a>
                        <a href="#how-it-works">
                            <x-button color="slate" lg light icon="play-circle" position="left">How it works</x-button>
                        </a>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="overflow-hidden rounded-2xl border border-slate-200 shadow-lg">
                        <img
                            src="{{ asset('images/marketing/flyer.png') }}"
                            alt="Adult daughter helping her older mother at home"
                            class="h-[420px] w-full object-cover"
                        />
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        Real support. Real peace of mind.
                    </div>
                </div>
            </div>

            <div class="grid gap-4 border-t border-slate-200 bg-slate-50 p-6 md:grid-cols-3 lg:p-8">
                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">What we help with</div></x-slot:header>
                    <p class="text-sm text-slate-600">Meal prep, companionship, groceries and errands, light housekeeping, rides to appointments, and routine daily support.</p>
                </x-card>
                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Why families choose us</div></x-slot:header>
                    <p class="text-sm text-slate-600">Raleigh-based, faster than traditional agency callbacks, and built around transparent profiles and direct communication.</p>
                </x-card>
                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Flexible care</div></x-slot:header>
                    <p class="text-sm text-slate-600">Book one-time help or ongoing weekly support based on your loved one’s real schedule and needs.</p>
                </x-card>
            </div>
            <div class="border-t border-slate-200 bg-white px-6 pb-6 pt-5 lg:px-8">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="font-bold text-slate-900">Care is not just tasks. It is trust, comfort, and presence.</p>
                    <p class="mt-1 text-sm text-slate-600">Our goal is to make sure your loved one feels safe and seen, and you feel less alone in the process.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="border-y border-slate-200 bg-white py-14">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8 text-center">
                <h2 class="text-3xl font-extrabold tracking-tight">How it works</h2>
                <p class="mt-2 text-slate-600">A simple flow designed to get your family real help quickly.</p>
            </div>
            <div class="grid gap-5 md:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-cyan-800">Step 1</div>
                    <div class="mt-2 text-lg font-bold">Tell us what you need</div>
                    <p class="mt-2 text-sm text-slate-600">Share your schedule, support needs, and who is receiving care.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-cyan-800">Step 2</div>
                    <div class="mt-2 text-lg font-bold">Match and chat</div>
                    <p class="mt-2 text-sm text-slate-600">Review caregiver profiles, compare trust signals, and message directly before hiring.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-cyan-800">Step 3</div>
                    <div class="mt-2 text-lg font-bold">Start care with confidence</div>
                    <p class="mt-2 text-sm text-slate-600">Track the shift lifecycle in-app and keep everyone aligned from start to review.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="rounded-3xl bg-gradient-to-r from-cyan-900 via-blue-800 to-emerald-700 p-6 text-white shadow-2xl sm:p-8">
            <h3 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Trust and safety are our priority</h3>
            <div class="mt-5 grid gap-3 md:grid-cols-2">
                <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm">Caregiver identity verification before care begins</div>
                <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm">Secure checkout with transparent payment policies</div>
                <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm">Private in-platform messaging to protect your family</div>
                <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm">Responsive support team for urgent issues</div>
                <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm">Transparent profiles and accountability standards</div>
                <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm">Clear safety and quality policies across the platform</div>
            </div>
        </div>
    </section>

    <section class="bg-white py-12">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-extrabold tracking-tight">Raleigh family care guides</h2>
                    <p class="mt-1 text-sm text-slate-600">Explore local guides before posting your request.</p>
                </div>
                <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-home-care']) }}" class="text-sm font-semibold text-blue-700 hover:underline">
                    View all Raleigh guides
                </a>
            </div>
            <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-companion-care']) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:bg-white">Companion care in Raleigh</a>
                <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-respite-care']) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:bg-white">Respite care in Raleigh</a>
                <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-post-hospital-home-help']) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:bg-white">Post-hospital home help</a>
                <a href="{{ route('seo.page', ['seoSlug' => 'home-care-cost-raleigh-nc']) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:bg-white">Home care cost in Raleigh</a>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="rounded-3xl border-2 border-dashed border-cyan-200 bg-white p-7 text-center shadow-lg sm:p-10">
            <h3 class="text-3xl font-extrabold tracking-tight">Call HomeCare and get trusted help today.</h3>
            <p class="mx-auto mt-2 max-w-3xl text-slate-600">Because your loved one deserves warm, reliable care and you deserve peace of mind.</p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('register') }}">
                    <x-button color="blue" lg icon="phone" position="left">Get help now</x-button>
                </a>
                <a href="{{ route('login') }}">
                    <x-button color="slate" lg light>Already have an account</x-button>
                </a>
            </div>
        </div>
    </section>
</div>
