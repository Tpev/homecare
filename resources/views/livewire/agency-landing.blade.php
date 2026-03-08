<div class="min-h-screen bg-slate-50 text-slate-900">
    <header class="sticky top-0 z-50 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('landing') }}" class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-2xl bg-gradient-to-br from-slate-900 via-blue-700 to-emerald-600 shadow-sm ring-1 ring-black/5"></div>
                <div class="leading-tight">
                    <div class="text-lg font-extrabold tracking-tight">HomeCare</div>
                    <div class="text-xs text-slate-500">For agencies</div>
                </div>
            </a>
            <div class="flex items-center gap-2">
                <a href="{{ route('landing.family') }}">
                    <x-button color="slate" sm light>Families</x-button>
                </a>
                <a href="{{ route('landing.caregiver') }}">
                    <x-button color="slate" sm light>Caregivers</x-button>
                </a>
                <a href="{{ route('login') }}">
                    <x-button color="slate" sm light>Sign in</x-button>
                </a>
            </div>
        </div>
    </header>

    <section class="mx-auto grid max-w-7xl gap-10 px-4 pb-16 pt-14 sm:px-6 lg:grid-cols-2 lg:px-8 lg:pt-20">
        <div>
            <x-badge color="indigo" text="Agency partnerships" round light />
            <h1 class="mt-4 text-4xl font-black leading-tight tracking-tight sm:text-5xl">
                Grow occupancy and stabilize staffing with one modern platform.
            </h1>
            <p class="mt-6 max-w-xl text-lg text-slate-600">
                HomeCare helps agencies win new demand and move faster when schedules are tight. Keep quality high while reducing coordination overhead.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('register') }}">
                    <x-button color="blue" lg icon="building-office-2" position="left">Create agency account</x-button>
                </a>
                <a href="{{ route('caregiver.register') }}">
                    <x-button color="emerald" lg outline icon="user-group" position="left">Onboard caregivers</x-button>
                </a>
            </div>
            <div class="mt-8 grid gap-3 text-sm sm:grid-cols-2">
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">Higher visibility to family demand</div>
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">Faster response to urgent coverage</div>
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">Structured request lifecycle and audit trail</div>
                <div class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700">Built-in messaging and support records</div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="overflow-hidden rounded-3xl ring-1 ring-black/10 shadow-xl">
                <img
                    src="https://images.unsplash.com/photo-1573497019940-1c28c88b4f3e?auto=format&fit=crop&w=1400&q=80"
                    alt="Agency care team collaborating"
                    class="h-72 w-full object-cover sm:h-80"
                />
            </div>
            <x-card class="ring-1 ring-black/5 shadow-sm">
                <x-slot:header><div class="font-bold">Designed for operations teams</div></x-slot:header>
                <p class="text-sm text-slate-600">From intake to completion, your team gets clear status tracking, notes, and conversation history per request.</p>
            </x-card>
        </div>
    </section>

    <section class="border-y border-slate-200 bg-white py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h2 class="text-3xl font-extrabold tracking-tight">Why agencies choose HomeCare</h2>
                <p class="mt-2 text-slate-600">Built to protect both growth and service quality.</p>
            </div>
            <div class="grid gap-6 md:grid-cols-3">
                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Demand engine</div></x-slot:header>
                    <p class="text-sm text-slate-600">Capture better quality requests with structured intake and complete household context.</p>
                </x-card>
                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Coverage velocity</div></x-slot:header>
                    <p class="text-sm text-slate-600">Invite known caregiver profiles quickly and reduce back-and-forth during urgent scheduling gaps.</p>
                </x-card>
                <x-card class="ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Quality controls</div></x-slot:header>
                    <p class="text-sm text-slate-600">Use lifecycle tracking, review data, and support logs to maintain strong client experience.</p>
                </x-card>
            </div>
        </div>
    </section>

    <section class="border-b border-slate-200 bg-slate-100 py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h2 class="text-3xl font-extrabold tracking-tight">Agency journey in 4 steps</h2>
            </div>
            <div class="grid gap-5 md:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-indigo-700">Step 1</div>
                    <div class="mt-2 font-bold">Create account</div>
                    <p class="mt-1 text-sm text-slate-600">Set up your operating account and core service details.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-indigo-700">Step 2</div>
                    <div class="mt-2 font-bold">Receive requests</div>
                    <p class="mt-1 text-sm text-slate-600">Capture demand with rich context for quicker qualification.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-indigo-700">Step 3</div>
                    <div class="mt-2 font-bold">Coordinate staffing</div>
                    <p class="mt-1 text-sm text-slate-600">Invite caregivers and manage conversations in one thread.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <div class="text-xs font-bold uppercase tracking-wider text-indigo-700">Step 4</div>
                    <div class="mt-2 font-bold">Close quality loop</div>
                    <p class="mt-1 text-sm text-slate-600">Track completion and review signals to improve performance.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-700 to-emerald-600 p-7 text-white shadow-2xl sm:p-10">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-3xl font-extrabold tracking-tight">Ready to scale your agency operations?</h3>
                    <p class="mt-2 text-white/90">Create your account and start coordinating with better speed and structure.</p>
                </div>
                <a href="{{ route('register') }}">
                    <x-button color="white" lg>Create account</x-button>
                </a>
            </div>
        </div>
    </section>
</div>
