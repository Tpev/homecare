<div class="min-h-screen bg-slate-50 text-slate-900">
    <header class="sticky top-0 z-50 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('landing') }}" class="flex items-center gap-3">
                <x-application-logo class="h-10 w-10 text-cyan-800" />
                <div class="leading-tight">
                    <div class="text-lg font-extrabold tracking-tight">HomeCare</div>
                    <div class="text-xs text-slate-500">Raleigh, NC</div>
                </div>
            </a>

            <nav class="hidden items-center gap-6 text-sm font-medium text-slate-600 lg:flex">
                <a href="{{ route('landing.family') }}" class="transition hover:text-slate-900">Families</a>
                <a href="{{ route('landing.caregiver') }}" class="transition hover:text-slate-900">Caregivers</a>
                <a href="{{ route('landing.agency') }}" class="transition hover:text-slate-900">Agencies</a>
            </nav>

            <div class="flex items-center gap-2">
                <a href="{{ route('login') }}">
                    <x-button color="slate" sm light>Sign in</x-button>
                </a>
                <a href="{{ route('register') }}">
                    <x-button color="blue" sm>Family sign up</x-button>
                </a>
                <a href="{{ route('caregiver.register') }}" class="hidden sm:block">
                    <x-button color="emerald" sm outline>Caregiver sign up</x-button>
                </a>
            </div>
        </div>
    </header>

    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-white via-slate-50 to-slate-100"></div>
        <div class="absolute -top-24 -right-20 h-80 w-80 rounded-full bg-cyan-200/45 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-20 h-80 w-80 rounded-full bg-emerald-200/45 blur-3xl"></div>

        <div class="relative mx-auto grid max-w-7xl gap-12 px-4 pb-16 pt-14 sm:px-6 lg:grid-cols-2 lg:px-8 lg:pb-20 lg:pt-20">
            <div>
                <div class="mb-5 flex flex-wrap gap-2">
                    <x-badge color="blue" text="Built for real families" round light />
                    <x-badge color="emerald" text="Independent caregivers" round light />
                    <x-badge color="slate" text="Non-medical home care" round light />
                </div>

                <h1 class="text-4xl font-black leading-tight tracking-tight sm:text-5xl">
                    Home care that feels human.
                    <span class="bg-gradient-to-r from-cyan-700 via-blue-700 to-emerald-600 bg-clip-text text-transparent">
                        Platform flow that feels effortless.
                    </span>
                </h1>

                <p class="mt-6 max-w-xl text-lg text-slate-600">
                    Families can post care requests in minutes, caregivers can apply or get invited, both sides chat in real-time, and shifts are tracked to completion with reviews.
                </p>

                <div class="mt-8 grid grid-cols-1 gap-3 sm:flex sm:flex-wrap">
                    <a href="{{ route('register') }}">
                        <x-button color="blue" lg icon="heart" position="left" class="w-full justify-center sm:w-auto">I need care</x-button>
                    </a>
                    <a href="{{ route('caregiver.register') }}">
                        <x-button color="emerald" lg outline icon="user-plus" position="left" class="w-full justify-center sm:w-auto">I provide care</x-button>
                    </a>
                </div>

                <div class="mt-8 grid gap-3 text-sm sm:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">Request and hire flow</div>
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">In-app messaging</div>
                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">Shift completion + reviews</div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="overflow-hidden rounded-3xl ring-1 ring-black/10 shadow-xl">
                    <img
                        src="https://images.unsplash.com/photo-1516302752625-fcc3c50ae61f?auto=format&fit=crop&w=1400&q=80"
                        alt="Caregiver assisting an older adult at home"
                        class="h-72 w-full object-cover sm:h-80"
                    />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-card class="ring-1 ring-black/5 shadow-sm">
                        <x-slot:header><div class="font-bold">For families</div></x-slot:header>
                        <p class="text-sm text-slate-600">Find the right caregiver profile, compare clearly, and hire with confidence.</p>
                    </x-card>
                    <x-card class="ring-1 ring-black/5 shadow-sm">
                        <x-slot:header><div class="font-bold">For caregivers</div></x-slot:header>
                        <p class="text-sm text-slate-600">Choose requests that fit your schedule and build reputation through reviews.</p>
                    </x-card>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h2 class="text-3xl font-extrabold tracking-tight">Why people choose HomeCare</h2>
                <p class="mt-2 text-slate-600">Clear expectations, faster coordination, and better outcomes for both sides.</p>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Clarity before commitment</div></x-slot:header>
                    <p class="text-sm text-slate-600">Requests include schedule, tasks, recipient details, and additional notes before anyone says yes.</p>
                </x-card>
                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Trust-first marketplace</div></x-slot:header>
                    <p class="text-sm text-slate-600">Profile moderation, trust badges, review history, and support tickets create accountability.</p>
                </x-card>
                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Smooth full-cycle operations</div></x-slot:header>
                    <p class="text-sm text-slate-600">From posting and chat to hiring and completion, all core actions are in one place.</p>
                </x-card>
            </div>
        </div>
    </section>

    <section class="border-y border-slate-200 bg-slate-100 py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h2 class="text-3xl font-extrabold tracking-tight">How it works</h2>
                <p class="mt-2 text-slate-600">A simple cycle designed for speed and confidence.</p>
            </div>

            <div class="grid gap-5 md:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-blue-700">Step 1</div>
                    <div class="mt-2 font-bold">Post request</div>
                    <p class="mt-1 text-sm text-slate-600">Family shares address, schedule, tasks, and care context.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-blue-700">Step 2</div>
                    <div class="mt-2 font-bold">Apply or invite</div>
                    <p class="mt-1 text-sm text-slate-600">Caregivers apply, or families invite specific profiles directly.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-blue-700">Step 3</div>
                    <div class="mt-2 font-bold">Chat and hire</div>
                    <p class="mt-1 text-sm text-slate-600">Shortlist candidates, message in-app, and choose the best fit.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-blue-700">Step 4</div>
                    <div class="mt-2 font-bold">Complete and review</div>
                    <p class="mt-1 text-sm text-slate-600">Track shift status and leave a quality review after completion.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-extrabold tracking-tight">Raleigh care guides</h2>
                    <p class="mt-1 text-sm text-slate-600">Local pages built around real family and caregiver needs in Raleigh, NC.</p>
                </div>
                <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-home-care']) }}" class="text-sm font-semibold text-blue-700 hover:underline">
                    Explore all Raleigh pages
                </a>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-home-care']) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:bg-white">
                    <p class="text-sm font-semibold text-slate-900">Home care in Raleigh, NC</p>
                </a>
                <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-companion-care']) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:bg-white">
                    <p class="text-sm font-semibold text-slate-900">Companion care in Raleigh</p>
                </a>
                <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-respite-care']) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:bg-white">
                    <p class="text-sm font-semibold text-slate-900">Respite care in Raleigh</p>
                </a>
                <a href="{{ route('seo.page', ['seoSlug' => 'home-care-cost-raleigh-nc']) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:bg-white">
                    <p class="text-sm font-semibold text-slate-900">Home care cost in Raleigh</p>
                </a>
                <a href="{{ route('seo.page', ['seoSlug' => 'trusted-caregiver-screening']) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:bg-white">
                    <p class="text-sm font-semibold text-slate-900">Trusted caregiver screening</p>
                </a>
                <a href="{{ route('seo.page', ['seoSlug' => 'caregiver-jobs-raleigh-nc']) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:bg-white">
                    <p class="text-sm font-semibold text-slate-900">Caregiver jobs in Raleigh</p>
                </a>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="rounded-3xl bg-gradient-to-r from-cyan-700 via-blue-700 to-emerald-600 p-7 text-white shadow-2xl sm:p-10">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-3xl font-extrabold tracking-tight">Ready to launch your first care cycle?</h3>
                    <p class="mt-2 text-white/90">Start as family or caregiver and get set up in minutes.</p>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:flex sm:flex-wrap">
                    <a href="{{ route('register') }}">
                        <x-button color="white" lg class="w-full justify-center sm:w-auto">Family sign up</x-button>
                    </a>
                    <a href="{{ route('caregiver.register') }}">
                        <x-button color="white" lg light class="w-full justify-center sm:w-auto">Caregiver sign up</x-button>
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>
