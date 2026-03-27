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

            <div class="grid gap-8 p-5 sm:p-6 lg:grid-cols-[1.05fr_0.95fr] lg:gap-10 lg:p-8">
                <div>
                    <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-cyan-900 sm:text-xs">
                        Trusted home support in Raleigh, NC
                    </div>

                    <h1 class="mt-4 max-w-2xl text-4xl font-black leading-[1.02] tracking-tight text-slate-950 sm:text-5xl lg:text-[3.65rem]">
                        Need help for your mom or dad?
                    </h1>

                    <p class="mt-5 max-w-2xl text-lg leading-8 text-slate-700">
                        HomeCare helps Raleigh families find
                        <span class="rounded-full bg-cyan-100 px-2 py-0.5 text-sm font-semibold text-cyan-900">trusted caregivers faster</span>
                        when a parent needs help at home, without forcing you into days of agency callbacks, uncertainty, and back-and-forth.
                    </p>

                    <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg">
                        If you are balancing work, family, and worry, we give you a simpler way to get help in place, stay informed, and feel better about who is showing up for the person you love.
                    </p>

                    <div class="mt-7 grid grid-cols-1 gap-3 sm:flex sm:flex-wrap">
                        <a href="{{ route('register') }}">
                            <x-button color="blue" lg icon="heart" position="left" class="w-full justify-center sm:w-auto">
                                Get trusted help today
                            </x-button>
                        </a>
                        <a href="#how-it-works">
                            <x-button color="slate" lg light icon="play-circle" position="left" class="w-full justify-center sm:w-auto">
                                See how it works
                            </x-button>
                        </a>
                    </div>

                    <div class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700">Post a request in minutes</div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700">Chat before you hire</div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-slate-700">Track the shift clearly</div>
                    </div>

                    <div class="mt-6 rounded-3xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                        <p class="text-sm leading-7 text-slate-700 sm:text-base">
                            Sometimes the moment is obvious: a fall, a hospital discharge, a scare, or the point where you realize
                            <span class="font-semibold text-slate-900">this cannot keep going like this.</span>
                            Sometimes it is quieter than that. Either way, the problem feels the same: you need dependable help, and you need it without a long, confusing process.
                        </p>
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

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-700">
                        <div class="font-semibold text-slate-900">Real support. Real peace of mind.</div>
                        <p class="mt-1 leading-6 text-slate-600">
                            Find someone trustworthy, talk before you commit, and stop carrying every part of the care plan by yourself.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 border-t border-slate-200 bg-slate-50 p-5 sm:p-6 lg:grid-cols-3 lg:p-8">
                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">For the family member doing everything</div></x-slot:header>
                    <p class="text-sm leading-6 text-slate-600">You may live nearby and be paying for care yourself, but still have almost no time to research agencies, wait for callbacks, and manage the details alone.</p>
                </x-card>

                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">For the daughter or son coordinating from a distance</div></x-slot:header>
                    <p class="text-sm leading-6 text-slate-600">If you are not in Raleigh every day, you still need visibility into who applied, who was hired, and what happened during the shift.</p>
                </x-card>

                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">What you are really buying</div></x-slot:header>
                    <p class="text-sm leading-6 text-slate-600">Not just care hours. You are buying speed, trust, communication, and the feeling that your parent is not being left to chance.</p>
                </x-card>
            </div>

            <div class="border-t border-slate-200 bg-white px-5 pb-6 pt-5 sm:px-6 lg:px-8">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <p class="text-base font-bold text-slate-900">Care at home should feel calmer, not more chaotic.</p>
                    <p class="mt-2 text-sm leading-6 text-slate-600 sm:text-base">
                        HomeCare is built for the family member trying to get help in place quickly, make a smart decision, and stop feeling like every update depends on another phone call.
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
                    We designed the flow for families who need to move quickly but still want clarity and confidence before someone new walks into their parent’s home.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6">
                    <div class="text-xs font-bold uppercase tracking-[0.18em] text-cyan-800">Step 1</div>
                    <div class="mt-2 text-lg font-bold">Tell us what is going on</div>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Share where care is needed, what kind of support would help, and who should stay informed.</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6">
                    <div class="text-xs font-bold uppercase tracking-[0.18em] text-cyan-800">Step 2</div>
                    <div class="mt-2 text-lg font-bold">Review caregivers and chat directly</div>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Compare profiles, trust signals, and applications in one place, then message before you decide.</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 sm:p-6">
                    <div class="text-xs font-bold uppercase tracking-[0.18em] text-cyan-800">Step 3</div>
                    <div class="mt-2 text-lg font-bold">Stay informed after care begins</div>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Track the shift, review the timesheet, and know what happened without chasing updates through texts and calls.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="rounded-3xl bg-gradient-to-r from-cyan-900 via-blue-800 to-emerald-700 p-6 text-white shadow-2xl sm:p-8">
            <h3 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Trust and safety are not optional</h3>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-white/85 sm:text-base">
                When you are arranging help for a parent, the emotional stakes are high. Families need to move fast, but they also need to trust the person, the communication, and the payment flow.
            </p>

            <div class="mt-5 grid gap-3 md:grid-cols-2">
                <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm">Caregiver identity verification before care begins</div>
                <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm">Secure checkout with transparent payment policies</div>
                <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm">Private in-platform messaging to protect your family</div>
                <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm">Responsive support when plans change or something feels urgent</div>
                <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm">Transparent profiles, reviews, and accountability standards</div>
                <div class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm">Better visibility for local and long-distance family decision-makers</div>
            </div>
        </div>
    </section>

    <section class="bg-white py-12">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">When you are local</div>
                    <h3 class="mt-3 text-2xl font-extrabold tracking-tight">You should not have to choose between being a good son or daughter and keeping life together.</h3>
                    <p class="mt-4 text-sm leading-6 text-slate-600">
                        Maybe you are handling the bills, the doctor appointments, the calls, and the “what are we going to do now?” conversations. HomeCare is meant to reduce friction, not add another project to your week.
                    </p>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-6">
                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">When you are farther away</div>
                    <h3 class="mt-3 text-2xl font-extrabold tracking-tight">You deserve more than secondhand updates and constant uncertainty.</h3>
                    <p class="mt-4 text-sm leading-6 text-slate-600">
                        If you are coordinating care from another city, you still need a clear picture of what is happening. The platform is built so you can understand the request, the hire, and the shift without feeling shut out of the process.
                    </p>
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
            <h3 class="text-3xl font-extrabold tracking-tight">Get trusted help in place without carrying the whole situation alone.</h3>
            <p class="mx-auto mt-3 max-w-3xl text-sm leading-7 text-slate-600 sm:text-base">
                HomeCare gives Raleigh families a faster, clearer path to support at home for the parent who depends on them.
            </p>

            <div class="mt-6 grid grid-cols-1 gap-3 sm:flex sm:flex-wrap sm:justify-center">
                <a href="{{ route('register') }}">
                    <x-button color="blue" lg icon="phone" position="left" class="w-full justify-center sm:w-auto">Get trusted help today</x-button>
                </a>
                <a href="{{ route('login') }}">
                    <x-button color="slate" lg light class="w-full justify-center sm:w-auto">Already have an account</x-button>
                </a>
            </div>
        </div>
    </section>
</div>
