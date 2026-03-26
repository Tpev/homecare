<div class="min-h-screen bg-slate-100 text-slate-900">
    <header class="sticky top-0 z-50 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('landing') }}" class="flex items-center gap-3">
                <x-application-logo class="h-10 w-10 text-cyan-800" />
                <div class="leading-tight">
                    <div class="text-lg font-extrabold tracking-tight">HomeCare</div>
                    <div class="text-xs text-slate-500">For families</div>
                </div>
            </a>

            <div class="flex items-center gap-2">
                <a href="{{ route('landing.caregiver') }}" class="hidden sm:block">
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

    <section class="mx-auto max-w-6xl px-4 pb-8 pt-8 sm:px-6 lg:px-8 lg:pt-12">
        <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-[0_24px_80px_rgba(15,23,42,0.08)]">
            <div class="h-4 bg-gradient-to-r from-cyan-900 via-blue-700 to-emerald-500"></div>

            <div class="grid gap-8 p-5 sm:p-6 lg:grid-cols-[1.08fr_0.92fr] lg:gap-10 lg:p-8">
                <div>
                    <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-cyan-900 sm:text-xs">
                        Trusted home support in Raleigh, NC
                    </div>

                    <h1 class="mt-4 max-w-2xl text-4xl font-black leading-[1.02] tracking-tight text-slate-950 sm:text-5xl lg:text-[3.6rem]">
                        Need help caring for your mom or dad?
                    </h1>

                    <p class="mt-5 max-w-2xl text-lg leading-8 text-slate-700">
                        HomeCare helps busy adult children find
                        <span class="rounded-full bg-cyan-100 px-2 py-0.5 text-sm font-semibold text-cyan-900">trusted help fast</span>
                        for an aging parent, without waiting days for agency callbacks or coordinating everything alone.
                    </p>

                    <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg">
                        If your parent had a fall, just came home from the hospital, or you are realizing
                        <span class="font-semibold text-slate-900">this cannot keep going like this</span>,
                        we help you move quickly, stay informed, and feel confident about who is showing up.
                    </p>

                    <div class="mt-7 grid grid-cols-1 gap-3 sm:flex sm:flex-wrap">
                        <a href="{{ route('register') }}">
                            <x-button color="blue" lg icon="heart" position="left" class="w-full justify-center sm:w-auto">
                                Get trusted help for my parent
                            </x-button>
                        </a>
                        <a href="#how-it-works">
                            <x-button color="slate" lg light icon="play-circle" position="left" class="w-full justify-center sm:w-auto">
                                See how it works
                            </x-button>
                        </a>
                    </div>

                    <div class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700">Fast request posting</div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700">Direct chat with caregivers</div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700">Shift updates and clear review flow</div>
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-800">Built for Busy Ben</div>
                            <p class="mt-2 text-sm leading-6 text-slate-700">Local son, primary payer, needs reliable help now without disrupting work and family life.</p>
                        </div>
                        <div class="rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-800">Also for Caring Carol</div>
                            <p class="mt-2 text-sm leading-6 text-slate-700">Out-of-town daughter who needs visibility, updates, and confidence from a distance.</p>
                        </div>
                    </div>

                    <a href="{{ route('landing.caregiver') }}" class="mt-4 inline-flex text-sm font-medium text-slate-600 underline decoration-slate-300 underline-offset-4 sm:hidden">
                        Are you a caregiver? Learn more here
                    </a>
                </div>

                <div class="space-y-4">
                    <div class="overflow-hidden rounded-[2rem] border border-slate-200 shadow-[0_24px_60px_rgba(15,23,42,0.14)]">
                        <img
                            src="{{ asset('images/marketing/flyer.png') }}"
                            alt="Adult daughter supporting her mother at home"
                            class="h-[220px] w-full object-cover object-center sm:h-[320px] lg:h-[430px]"
                        />
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        Real support. Real peace of mind.
                    </div>
                </div>
            </div>

            <div class="grid gap-4 border-t border-slate-200 bg-slate-50 p-5 sm:p-6 lg:grid-cols-3 lg:p-8">
                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">What you are really buying</div></x-slot:header>
                    <p class="text-sm leading-6 text-slate-600">Speed, trust, and simplicity. Less scrambling, less uncertainty, and more confidence that your parent has reliable support at home.</p>
                </x-card>

                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Why families choose us</div></x-slot:header>
                    <p class="text-sm leading-6 text-slate-600">Faster than traditional agency callback loops, with direct caregiver profiles, transparent communication, and a clear path from request to completed shift.</p>
                </x-card>

                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Made for remote coordination too</div></x-slot:header>
                    <p class="text-sm leading-6 text-slate-600">If you live out of town, you still get visibility into who was hired, what was scheduled, and what happened during the shift.</p>
                </x-card>
            </div>

            <div class="border-t border-slate-200 bg-white px-5 pb-6 pt-5 sm:px-6 lg:px-8">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <p class="text-base font-bold text-slate-900">Care is not just tasks. It is trust, comfort, and presence.</p>
                    <p class="mt-2 text-sm leading-6 text-slate-600 sm:text-base">
                        We are here for the son trying to keep work on track while helping his parents, and for the daughter trying to manage everything from another city without feeling in the dark.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="border-y border-slate-200 bg-white py-14">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8 text-center">
                <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">How HomeCare works</h2>
                <p class="mx-auto mt-3 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg">
                    The goal is simple: help you get trusted support in place quickly, with less back-and-forth and more confidence.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6">
                    <div class="text-xs font-bold uppercase tracking-[0.18em] text-cyan-800">Step 1</div>
                    <div class="mt-2 text-lg font-bold">Tell us what is happening</div>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Share the schedule, address, what kind of help your parent needs, and who should stay in the loop.</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6">
                    <div class="text-xs font-bold uppercase tracking-[0.18em] text-cyan-800">Step 2</div>
                    <div class="mt-2 text-lg font-bold">Review, chat, and choose</div>
                    <p class="mt-2 text-sm leading-6 text-slate-600">See caregiver profiles, trust signals, and applications in one place, then message before you hire.</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6">
                    <div class="text-xs font-bold uppercase tracking-[0.18em] text-cyan-800">Step 3</div>
                    <div class="mt-2 text-lg font-bold">Keep visibility after the shift starts</div>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Track progress, review the timesheet, and know what happened without chasing updates by phone.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="rounded-3xl bg-gradient-to-r from-cyan-900 via-blue-800 to-emerald-700 p-6 text-white shadow-2xl sm:p-8">
            <h3 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Trust and safety are our priority</h3>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-white/85 sm:text-base">
                Families come to us because they need help quickly, but they still need to trust who is entering their parent’s home. The product should lower anxiety, not add to it.
            </p>

            <div class="mt-5 grid gap-3 md:grid-cols-2">
                <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm">Caregiver identity verification before care begins</div>
                <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm">Secure checkout with transparent payment policies</div>
                <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm">Private in-platform messaging to protect your family</div>
                <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm">Responsive support when something changes or feels urgent</div>
                <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm">Transparent profiles, reviews, and accountability standards</div>
                <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm">Visibility for local and long-distance family decision-makers</div>
            </div>
        </div>
    </section>

    <section class="bg-white py-12">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">For Busy Ben</div>
                    <h3 class="mt-3 text-2xl font-extrabold tracking-tight">You need to solve this fast and still make a smart decision.</h3>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                        <li>Speed matters because work and family life are still moving.</li>
                        <li>Trust matters because you do not want to gamble on a random caregiver.</li>
                        <li>Simplicity matters because you are buying peace of mind, not just care hours.</li>
                    </ul>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">For Caring Carol</div>
                    <h3 class="mt-3 text-2xl font-extrabold tracking-tight">You need visibility when you are managing everything from far away.</h3>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-slate-600">
                        <li>Know who applied, who was hired, and what was scheduled.</li>
                        <li>Stay in the loop through direct messaging and shift status tracking.</li>
                        <li>Reduce guilt and uncertainty by having one place to coordinate care.</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-slate-50 py-12">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-extrabold tracking-tight">Raleigh family care guides</h2>
                    <p class="mt-1 text-sm text-slate-600">Local guides for families comparing options and trying to move quickly.</p>
                </div>
                <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-home-care']) }}" class="hidden text-sm font-semibold text-blue-700 hover:underline sm:inline">
                    View all Raleigh guides
                </a>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-companion-care']) }}" class="rounded-2xl border border-slate-200 bg-white p-4 text-sm font-medium text-slate-800 hover:bg-slate-50">Companion care in Raleigh</a>
                <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-respite-care']) }}" class="rounded-2xl border border-slate-200 bg-white p-4 text-sm font-medium text-slate-800 hover:bg-slate-50">Respite care in Raleigh</a>
                <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-post-hospital-home-help']) }}" class="rounded-2xl border border-slate-200 bg-white p-4 text-sm font-medium text-slate-800 hover:bg-slate-50">Post-hospital home help</a>
                <a href="{{ route('seo.page', ['seoSlug' => 'home-care-cost-raleigh-nc']) }}" class="rounded-2xl border border-slate-200 bg-white p-4 text-sm font-medium text-slate-800 hover:bg-slate-50">Home care cost in Raleigh</a>
            </div>

            <a href="{{ route('seo.page', ['seoSlug' => 'raleigh-home-care']) }}" class="mt-4 inline-flex text-sm font-semibold text-blue-700 hover:underline sm:hidden">
                View all Raleigh guides
            </a>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="rounded-3xl border-2 border-dashed border-cyan-200 bg-white p-7 text-center shadow-lg sm:p-10">
            <h3 class="text-3xl font-extrabold tracking-tight">Get trusted help in place without doing all the coordination alone.</h3>
            <p class="mx-auto mt-3 max-w-3xl text-sm leading-7 text-slate-600 sm:text-base">
                Whether you live in Raleigh or you are organizing care from another city, HomeCare gives you a faster path to trusted home support for the parent who depends on you.
            </p>

            <div class="mt-6 grid grid-cols-1 gap-3 sm:flex sm:flex-wrap sm:justify-center">
                <a href="{{ route('register') }}">
                    <x-button color="blue" lg icon="phone" position="left" class="w-full justify-center sm:w-auto">Get help now</x-button>
                </a>
                <a href="{{ route('login') }}">
                    <x-button color="slate" lg light class="w-full justify-center sm:w-auto">Already have an account</x-button>
                </a>
            </div>
        </div>
    </section>
</div>
