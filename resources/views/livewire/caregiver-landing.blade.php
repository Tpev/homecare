<div class="min-h-screen bg-[#f3f6f8] text-slate-900">
    <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('landing') }}" class="flex items-center gap-3">
                <x-application-logo class="h-10 w-10 text-cyan-800" />
                <div class="leading-tight">
                    <div class="text-lg font-extrabold tracking-tight">HomeCare</div>
                    <div class="text-xs text-slate-500">For caregivers</div>
                </div>
            </a>
            <div class="flex items-center gap-2">
                <a href="{{ route('landing.family') }}" class="hidden sm:block">
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

    <section class="mx-auto grid max-w-7xl items-center gap-10 px-4 pb-16 pt-12 sm:px-6 lg:grid-cols-2 lg:px-8 lg:pb-24 lg:pt-20">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-900">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                Raleigh · Durham · Chapel Hill
            </div>
            <h1 class="mt-5 text-4xl font-black leading-tight tracking-tight text-slate-900 sm:text-6xl">
                Flexible caregiving
                <br>
                work, on your
                <br>
                schedule.
            </h1>
            <p class="mt-5 max-w-xl text-lg text-slate-600">
                Join early access in the Triangle. Complete your profile now to be among the first caregivers matched when we launch.
            </p>

            <div class="mt-8 grid grid-cols-1 gap-3 sm:flex sm:flex-wrap">
                <a href="{{ route('caregiver.register') }}">
                    <x-button color="emerald" lg icon="user-plus" position="right" class="w-full justify-center sm:w-auto">Create your profile</x-button>
                </a>
                <a href="#how-it-works">
                    <x-button color="slate" lg light class="w-full justify-center sm:w-auto">Learn how it works</x-button>
                </a>
            </div>
            <a href="{{ route('landing.family') }}" class="mt-3 inline-flex text-sm font-medium text-slate-600 underline decoration-slate-300 underline-offset-4 sm:hidden">
                Looking for care for a parent? See the family page
            </a>

            <p class="mt-4 text-sm font-medium text-emerald-800">Pre-launch now: Raleigh + Wake County priority cohort</p>

            <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-700">No experience required</p>
                <p class="mt-2 text-sm text-slate-700">
                    You don't need formal caregiving experience to get started. What matters most is being reliable, kind, and willing to help with simple, everyday tasks, like spending time with someone, assisting with light routines, or just being present. Think of it like helping a family member, but on your schedule and getting paid for your time.
                </p>
            </div>
        </div>

        <div
            x-data="{
                index: 0,
                offers: [
                    { title: 'Companionship morning shift', meta: '3.0h · Raleigh · $30/hr' },
                    { title: 'Meal prep + light housekeeping', meta: '4.5h · Cary · $30/hr' },
                    { title: 'Mobility support and errands', meta: '2.5h · Durham · $30/hr' },
                    { title: 'Medication reminders + check-in', meta: '2.0h · Chapel Hill · $30/hr' }
                ],
                start() { setInterval(() => { this.index = (this.index + 1) % this.offers.length }, 3200) }
            }"
            x-init="start()"
            class="relative"
        >
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white p-2 shadow-2xl">
                <img
                    src="{{ asset('images/marketing/caregiver-hero-raleigh.jpg') }}"
                    onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1516302752625-fcc3c50ae61f?auto=format&fit=crop&w=1200&q=80';"
                    alt="Caregiver supporting an older adult"
                    class="h-[540px] w-full rounded-2xl object-cover"
                />
            </div>

            <div class="absolute bottom-5 right-5 w-[250px] rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-xl backdrop-blur">
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-emerald-700">New request</p>
                <p class="mt-1 text-sm font-semibold text-slate-900" x-text="offers[index].title"></p>
                <p class="mt-1 text-xs text-slate-500" x-text="offers[index].meta"></p>

                <div class="mt-2 flex items-center gap-1">
                    <template x-for="(offer, dotIndex) in offers" :key="dotIndex">
                        <span
                            class="h-1.5 w-1.5 rounded-full transition"
                            :class="dotIndex === index ? 'bg-emerald-500' : 'bg-slate-300'"
                        ></span>
                    </template>
                </div>
            </div>
        </div>
    </section>

    <section class="border-y border-slate-200 bg-white py-16">
        <div class="mx-auto grid max-w-7xl gap-5 px-4 sm:px-6 md:grid-cols-3 lg:px-8">
            <div class="rounded-2xl border border-slate-200 p-6 shadow-sm">
                <div class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">$</div>
                <h3 class="mt-4 text-xl font-bold">Make money</h3>
                <p class="mt-2 text-sm text-slate-600">Earn strong hourly pay for meaningful work that improves lives daily.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 p-6 shadow-sm">
                <div class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-cyan-100 text-cyan-700">◷</div>
                <h3 class="mt-4 text-xl font-bold">Your schedule</h3>
                <p class="mt-2 text-sm text-slate-600">Choose when and how often you work. You stay fully flexible.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 p-6 shadow-sm">
                <div class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-700">♥</div>
                <h3 class="mt-4 text-xl font-bold">Make an impact</h3>
                <p class="mt-2 text-sm text-slate-600">Help older adults stay independent at home with trusted support.</p>
            </div>
        </div>
    </section>

    <section class="bg-[#f3f6f8] py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8 max-w-3xl">
                <h2 class="text-4xl font-black tracking-tight text-slate-900">What you get with HomeCare pre-launch</h2>
                <p class="mt-3 text-slate-600">Same simple gig logic people know from rideshare platforms, but focused on trusted non-medical care for older adults.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-2xl border border-emerald-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Pay model</p>
                    <h3 class="mt-2 text-2xl font-black text-slate-900">Up to $30/hr* + 25% on night gigs</h3>
                    <p class="mt-2 text-sm text-slate-600">Night shifts are boosted to reward tougher hours. You see compensation before you accept a request.</p>
                    <p class="mt-3 text-xs text-slate-500">*Gross shift rate shown before platform fees.</p>
                </div>

                <div class="rounded-2xl border border-cyan-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-700">Secure payouts</p>
                    <h3 class="mt-2 text-2xl font-black text-slate-900">Family funds are secured before shift start</h3>
                    <p class="mt-2 text-sm text-slate-600">HomeCare captures payment authorization before work begins, then releases payout for the work you completed.</p>
                </div>

                <div class="rounded-2xl border border-blue-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-blue-700">Your comfort, your rules</p>
                    <h3 class="mt-2 text-2xl font-black text-slate-900">Choose exactly which tasks you accept</h3>
                    <p class="mt-2 text-sm text-slate-600">Set what you are comfortable doing and what you do not do. Families only see you for matching tasks.</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-950 p-6 text-white shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-300">Gig experience</p>
                    <h3 class="mt-2 text-2xl font-black">Uber-style workflow for elder care</h3>
                    <p class="mt-2 text-sm text-slate-200">Get requests, accept what fits, chat in-app, complete shifts, and build your rating over time.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="bg-[#f3f6f8] py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h2 class="text-4xl font-black tracking-tight text-slate-900">How it works</h2>
                <p class="mt-2 text-slate-600">Get started in four simple steps.</p>
            </div>

            <div class="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Step 01</p>
                    <h3 class="mt-3 text-lg font-bold">Create your profile</h3>
                    <p class="mt-2 text-sm text-slate-600">Add availability, skills, and preferences.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Step 02</p>
                    <h3 class="mt-3 text-lg font-bold">Get verified</h3>
                    <p class="mt-2 text-sm text-slate-600">Complete identity check and onboarding setup.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Step 03</p>
                    <h3 class="mt-3 text-lg font-bold">Get matched</h3>
                    <p class="mt-2 text-sm text-slate-600">Receive care requests that fit your schedule.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Step 04</p>
                    <h3 class="mt-3 text-lg font-bold">Start earning</h3>
                    <p class="mt-2 text-sm text-slate-600">Accept jobs and get paid for completed shifts.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-[#0a4a45] py-16 text-white">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <h2 class="text-4xl font-black tracking-tight">A new way to do caregiving</h2>
            <div class="mx-auto mt-7 grid max-w-2xl gap-3 text-left sm:grid-cols-2">
                <p class="rounded-xl bg-white/10 px-4 py-3 text-sm">✓ No fixed schedules</p>
                <p class="rounded-xl bg-white/10 px-4 py-3 text-sm">✓ No long-term commitments</p>
                <p class="rounded-xl bg-white/10 px-4 py-3 text-sm">✓ Choose the work you want</p>
                <p class="rounded-xl bg-white/10 px-4 py-3 text-sm">✓ Build your reputation over time</p>
                <p class="rounded-xl bg-white/10 px-4 py-3 text-sm">✓ Up to $30/hr gross + 25% night premium</p>
                <p class="rounded-xl bg-white/10 px-4 py-3 text-sm">✓ Money secured before the shift starts</p>
            </div>
        </div>
    </section>

    <section class="bg-white py-16">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-slate-200 px-7 py-10 text-center shadow-sm">
                <div class="mx-auto inline-flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">⌁</div>
                <h3 class="mt-4 text-3xl font-black tracking-tight">Great for students and future healthcare professionals</h3>
                <p class="mt-3 text-slate-600">Gain real-world experience, build hours, and work on your own schedule.</p>
            </div>
        </div>
    </section>

    <section class="border-t border-slate-200 bg-[#f3f6f8] py-16">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <h2 class="text-4xl font-black tracking-tight text-slate-900">Get early access to caregiving opportunities</h2>
            <p class="mx-auto mt-3 max-w-2xl text-slate-600">Complete your profile now to be part of the first caregiver group we activate in Raleigh and Wake County.</p>
            <div class="mt-8">
                <a href="{{ route('caregiver.register') }}">
                    <x-button color="emerald" lg icon="arrow-right" position="right">Create your profile</x-button>
                </a>
            </div>
            <p class="mt-3 text-sm text-slate-500">Takes just a few minutes to get started.</p>
            <p class="mt-2 text-xs text-slate-500">
                We use first-party analytics cookies to understand page performance and improve caregiver onboarding.
                See our
                <a class="underline hover:text-slate-700" href="{{ route('legal.show', ['slug' => 'privacy-policy']) }}">Privacy Policy</a>.
            </p>
        </div>
    </section>
</div>
