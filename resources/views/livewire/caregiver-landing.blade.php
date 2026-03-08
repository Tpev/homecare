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
            <x-badge color="emerald" text="Earn more • Work on your terms" round light />
            <h1 class="mt-4 text-4xl font-black leading-tight tracking-tight sm:text-5xl">
                Earn more money.
                <span class="bg-gradient-to-r from-emerald-700 via-cyan-700 to-blue-700 bg-clip-text text-transparent">
                    Be your own boss.
                </span>
            </h1>
            <p class="mt-6 max-w-xl text-lg text-slate-600">
                HomeCare helps independent caregivers work directly with families, choose the requests they want, and build a reputation that grows over time.
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('caregiver.register') }}">
                    <x-button color="emerald" lg icon="user-plus" position="left">Become a caregiver</x-button>
                </a>
                <a href="{{ route('login') }}">
                    <x-button color="slate" lg light icon="arrow-right-circle" position="left">Already registered? Sign in</x-button>
                </a>
            </div>

            <div class="mt-8 grid gap-3 text-sm sm:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">Keep more per hour by working directly</div>
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
                <x-slot:header><div class="font-bold">Your profile becomes your brand</div></x-slot:header>
                <p class="text-sm text-slate-600">Profiles are moderated for quality, and top caregivers stand out with trust badges and strong review history.</p>
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
                    <p class="text-sm text-slate-600">Set your own rate, accept the work you want, and keep more of what you earn.</p>
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
                    <p class="mt-1 text-sm text-slate-600">Add rate, skills, languages, and availability ranges.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-emerald-700">Step 3</div>
                    <div class="mt-2 font-bold">Under review</div>
                    <p class="mt-1 text-sm text-slate-600">Admin checks profile quality before activation.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-emerald-700">Step 4</div>
                    <div class="mt-2 font-bold">Go live</div>
                    <p class="mt-1 text-sm text-slate-600">Start receiving invites, applying fast, and building your independent business.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="rounded-3xl bg-gradient-to-r from-emerald-700 via-cyan-700 to-blue-700 p-7 text-white shadow-2xl sm:p-10">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-3xl font-extrabold tracking-tight">Start earning on your terms</h3>
                    <p class="mt-2 text-white/90">Create your caregiver account, complete onboarding, and go live.</p>
                </div>
                <a href="{{ route('caregiver.register') }}">
                    <x-button color="white" lg>Become a caregiver</x-button>
                </a>
            </div>
        </div>
    </section>
</div>
