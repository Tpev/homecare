<div class="min-h-screen bg-slate-50 text-slate-900">
    <header class="sticky top-0 z-50 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('landing') }}" class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-2xl bg-gradient-to-br from-emerald-700 via-cyan-700 to-blue-700 shadow-sm ring-1 ring-black/5"></div>
                <div class="leading-tight">
                    <div class="text-lg font-extrabold tracking-tight">HomeCare</div>
                    <div class="text-xs text-slate-500">For caregivers</div>
                </div>
            </a>
            <div class="flex items-center gap-2">
                <a href="{{ route('landing.family') }}">
                    <x-button color="slate" sm light>Families</x-button>
                </a>
                <a href="{{ route('login') }}">
                    <x-button color="slate" sm light>Sign in</x-button>
                </a>
                <a href="{{ route('caregiver.register') }}">
                    <x-button color="emerald" sm>Create account</x-button>
                </a>
            </div>
        </div>
    </header>

    <section class="mx-auto grid max-w-7xl gap-10 px-4 pb-16 pt-14 sm:px-6 lg:grid-cols-2 lg:px-8 lg:pt-20">
        <div>
            <x-badge color="emerald" text="Raleigh + Wake County pre-launch" round light />
            <h1 class="mt-4 text-4xl font-black leading-tight tracking-tight sm:text-5xl">
                Get in early.
                <span class="bg-gradient-to-r from-emerald-700 via-cyan-700 to-blue-700 bg-clip-text text-transparent">
                    Be first in line for family requests.
                </span>
            </h1>
            <p class="mt-6 max-w-xl text-lg text-slate-600">
                We are launching caregiver supply first in Raleigh and Wake County. Create your account today, complete onboarding and KYC, and your profile will be ready to activate as soon as matching opens.
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('caregiver.register') }}">
                    <x-button color="emerald" lg icon="user-plus" position="left">Join pre-launch</x-button>
                </a>
                <a href="{{ route('login') }}">
                    <x-button color="slate" lg light icon="arrow-right-circle" position="left">Already registered? Sign in</x-button>
                </a>
            </div>

            <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-sm font-semibold text-emerald-900">Pre-launch priority access (Raleigh + Wake County)</p>
                <ul class="mt-2 space-y-1 text-sm text-emerald-800">
                    <li>Complete profile + KYC now</li>
                    <li>Go into the first activation wave</li>
                    <li>First complete profiles are first routed to family demand</li>
                </ul>
            </div>

            <div class="mt-8 grid gap-3 text-sm sm:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">Priority activation for early completed profiles</div>
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">Choose your clients and your schedule</div>
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">Apply to requests or receive direct invites</div>
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">Build reputation with reviews and trust badges</div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="overflow-hidden rounded-3xl ring-1 ring-black/10 shadow-xl">
                <img
                    src="https://images.unsplash.com/photo-1527613426441-4da17471b66d?auto=format&fit=crop&w=1400&q=80"
                    alt="Caregiver smiling while assisting at home"
                    class="h-72 w-full object-cover sm:h-80"
                />
            </div>
            <x-card class="ring-1 ring-black/5 shadow-sm">
                <x-slot:header><div class="font-bold">Pre-launch in Raleigh & Wake County</div></x-slot:header>
                <p class="text-sm text-slate-600">Complete onboarding now so your profile is in the first caregiver wave we connect with families at launch.</p>
            </x-card>
        </div>
    </section>

    <section class="border-y border-slate-200 bg-white py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h2 class="text-3xl font-extrabold tracking-tight">Why caregivers choose HomeCare</h2>
                <p class="mt-2 text-slate-600">More income potential, more control, and a reputation you own.</p>
            </div>
            <div class="grid gap-6 md:grid-cols-3">
                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Earn more money</div></x-slot:header>
                    <p class="text-sm text-slate-600">Platform rates are transparent, and you keep strong hourly earnings while staying independent.</p>
                </x-card>
                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Be autonomous</div></x-slot:header>
                    <p class="text-sm text-slate-600">Decide your availability, service area, and who you work with. You stay in control.</p>
                </x-card>
                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Build your reputation</div></x-slot:header>
                    <p class="text-sm text-slate-600">Reviews, response score, and trust badges help you win better requests and repeat clients.</p>
                </x-card>
            </div>
        </div>
    </section>

    <section class="border-b border-slate-200 bg-slate-100 py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h2 class="text-3xl font-extrabold tracking-tight">How caregiver onboarding works</h2>
            </div>
            <div class="grid gap-5 md:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-emerald-700">Step 1</div>
                    <div class="mt-2 font-bold">Create account</div>
                    <p class="mt-1 text-sm text-slate-600">Register with your base profile and location.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-emerald-700">Step 2</div>
                    <div class="mt-2 font-bold">Complete onboarding</div>
                    <p class="mt-1 text-sm text-slate-600">Add skills, languages, availability, and complete identity verification.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-emerald-700">Step 3</div>
                    <div class="mt-2 font-bold">Under review</div>
                    <p class="mt-1 text-sm text-slate-600">We review profile quality so families get trusted caregivers from day one.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-emerald-700">Step 4</div>
                    <div class="mt-2 font-bold">Launch priority</div>
                    <p class="mt-1 text-sm text-slate-600">Early completed profiles are activated first and routed to first family requests.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-extrabold tracking-tight">Raleigh & Wake County caregiver opportunity guides</h2>
                    <p class="mt-1 text-sm text-slate-600">See where demand is growing and what families are requesting most before launch.</p>
                </div>
                <a href="{{ route('seo.page', ['seoSlug' => 'caregiver-jobs-raleigh-nc']) }}" class="text-sm font-semibold text-emerald-700 hover:underline">
                    Caregiver jobs in Raleigh
                </a>
            </div>
            <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-home-care']) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:bg-white">Raleigh home care demand</a>
                <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-overnight-caregiver']) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:bg-white">Overnight caregiver requests</a>
                <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-senior-transportation-help']) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:bg-white">Senior transportation requests</a>
                <a href="{{ route('seo.page', ['seoSlug' => 'trusted-caregiver-screening']) }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:bg-white">Trust badges and screening</a>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="rounded-3xl bg-gradient-to-r from-emerald-700 via-cyan-700 to-blue-700 p-7 text-white shadow-2xl sm:p-10">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-3xl font-extrabold tracking-tight">Get pre-launch priority now</h3>
                    <p class="mt-2 text-white/90">Complete your profile today to be among the first caregivers we connect with families in Raleigh and Wake County.</p>
                </div>
                <a href="{{ route('caregiver.register') }}">
                    <x-button color="white" lg>Join caregiver pre-launch</x-button>
                </a>
            </div>
        </div>
    </section>
</div>
