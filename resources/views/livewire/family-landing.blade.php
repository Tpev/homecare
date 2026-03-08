<div class="min-h-screen bg-slate-50 text-slate-900">
    <header class="sticky top-0 z-50 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('landing') }}" class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-2xl bg-gradient-to-br from-cyan-700 via-blue-700 to-emerald-600 shadow-sm ring-1 ring-black/5"></div>
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

    <section class="mx-auto grid max-w-7xl gap-10 px-4 pb-16 pt-14 sm:px-6 lg:grid-cols-2 lg:px-8 lg:pt-20">
        <div>
            <x-badge color="blue" text="Trusted care, fast response" round light />
            <h1 class="mt-4 text-4xl font-black leading-tight tracking-tight sm:text-5xl">
                Get trusted help today.
                <span class="bg-gradient-to-r from-blue-700 via-cyan-700 to-emerald-600 bg-clip-text text-transparent">
                    Not a callback in 3 days.
                </span>
            </h1>
            <p class="mt-6 max-w-xl text-lg text-slate-600">
                Post your request in minutes, receive responses quickly, and chat directly with caregivers so you can hire with confidence right now.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('register') }}">
                    <x-button color="blue" lg icon="clipboard-document-list" position="left">Get help now</x-button>
                </a>
                <a href="{{ route('login') }}">
                    <x-button color="emerald" lg outline icon="chat-bubble-left-right" position="left">Already have an account? Sign in</x-button>
                </a>
            </div>
            <div class="mt-8 grid gap-3 text-sm sm:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">Trusted profiles with reviews and trust badges</div>
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">One-time and recurring care requests</div>
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">Real-time chat, no phone-tag delays</div>
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">Support center if anything goes wrong</div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="overflow-hidden rounded-3xl ring-1 ring-black/10 shadow-xl">
                <img
                    src="https://images.unsplash.com/photo-1492724441997-5dc865305da7?auto=format&fit=crop&w=1400&q=80"
                    alt="Family discussing home care plan"
                    class="h-72 w-full object-cover sm:h-80"
                />
            </div>
            <x-card class="ring-1 ring-black/5 shadow-sm">
                <x-slot:header><div class="font-bold">Built for trust and speed</div></x-slot:header>
                <p class="text-sm text-slate-600">Every request captures recipient context and care details so you can get serious matches fast, without endless back-and-forth.</p>
            </x-card>
        </div>
    </section>

    <section class="border-y border-slate-200 bg-white py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h2 class="text-3xl font-extrabold tracking-tight">Why families trust HomeCare</h2>
                <p class="mt-2 text-slate-600">A faster, safer way to get the right help when you actually need it.</p>
            </div>
            <div class="grid gap-6 md:grid-cols-3">
                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Trust you can see</div></x-slot:header>
                    <p class="text-sm text-slate-600">Profiles include moderation status, reviews, and trust badges so you know who you are speaking with.</p>
                </x-card>
                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Help right now</div></x-slot:header>
                    <p class="text-sm text-slate-600">Post in minutes and get responses quickly, instead of waiting days for a callback.</p>
                </x-card>
                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Simple and clear</div></x-slot:header>
                    <p class="text-sm text-slate-600">Chat, hire, track shift status, and review in one place with support available if needed.</p>
                </x-card>
            </div>
        </div>
    </section>

    <section class="border-b border-slate-200 bg-slate-100 py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h2 class="text-3xl font-extrabold tracking-tight">Family journey in 4 steps</h2>
            </div>
            <div class="grid gap-5 md:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-blue-700">Step 1</div>
                    <div class="mt-2 font-bold">Create request</div>
                    <p class="mt-1 text-sm text-slate-600">Define address, schedule, tasks, and recipient profile.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-blue-700">Step 2</div>
                    <div class="mt-2 font-bold">Get replies fast</div>
                    <p class="mt-1 text-sm text-slate-600">Caregivers apply or respond to invites, and you can compare with full context.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-blue-700">Step 3</div>
                    <div class="mt-2 font-bold">Chat and choose</div>
                    <p class="mt-1 text-sm text-slate-600">Message directly and hire the caregiver you trust most.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-blue-700">Step 4</div>
                    <div class="mt-2 font-bold">Complete with confidence</div>
                    <p class="mt-1 text-sm text-slate-600">Track the shift and leave a review to strengthen trust in the network.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-extrabold tracking-tight">Raleigh family care guides</h2>
                    <p class="mt-1 text-sm text-slate-600">Explore detailed local pages before posting your request.</p>
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

    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="rounded-3xl bg-gradient-to-r from-blue-700 via-cyan-700 to-emerald-600 p-7 text-white shadow-2xl sm:p-10">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-3xl font-extrabold tracking-tight">Need care now? Start in minutes.</h3>
                    <p class="mt-2 text-white/90">Create your account, post your request, and start getting responses today.</p>
                </div>
                <a href="{{ route('register') }}">
                    <x-button color="white" lg>Get help now</x-button>
                </a>
            </div>
        </div>
    </section>
</div>
