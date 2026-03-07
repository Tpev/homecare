<div class="min-h-screen bg-slate-50 text-slate-900">
    {{-- NAV --}}
    <header class="sticky top-0 z-40 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-4">
            <a href="{{ url('/') }}" class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-2xl bg-gradient-to-br from-slate-900 via-indigo-800 to-emerald-500 shadow-sm ring-1 ring-black/5"></div>
                <div class="leading-tight">
                    <div class="text-lg font-extrabold tracking-tight text-slate-900">HomeCare</div>
                    <div class="text-xs text-slate-500">Agency growth + staffing • Raleigh, NC</div>
                </div>
            </a>

            <nav class="hidden lg:flex items-center gap-6 text-sm text-slate-600">
                <a href="#value" class="hover:text-slate-900 transition">Value</a>
                <a href="#how" class="hover:text-slate-900 transition">How it works</a>
                <a href="#use-cases" class="hover:text-slate-900 transition">Use cases</a>
                <a href="#faq" class="hover:text-slate-900 transition">FAQ</a>
            </nav>

            <div class="flex items-center gap-2">
                <a href="{{ route('login') }}">
                    <x-button color="slate" light sm>Sign in</x-button>
                </a>
                <a href="{{ route('register') }}">
                    <x-button color="indigo" sm>Join</x-button>
                </a>
            </div>
        </div>
    </header>

    {{-- HERO --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-white via-white to-slate-50"></div>
        <div class="absolute -top-32 -right-28 h-[28rem] w-[28rem] rounded-full bg-indigo-200/45 blur-3xl"></div>
        <div class="absolute -bottom-36 -left-28 h-[30rem] w-[30rem] rounded-full bg-emerald-200/35 blur-3xl"></div>
        <div class="absolute inset-0 opacity-[0.06]"
             style="background-image: radial-gradient(circle at 1px 1px, rgb(15 23 42) 1px, transparent 0); background-size: 28px 28px;">
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-14 pb-12 lg:pt-20 lg:pb-16">
            <div class="grid lg:grid-cols-2 gap-10 items-center">
                {{-- LEFT --}}
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-6">
                        <x-badge color="indigo" text="Agency early access" round light />
                        <x-badge color="emerald" text="More clients" round light />
                        <x-badge color="slate" text="Fill shifts fast" round light />
                    </div>

                    <h1 class="text-4xl sm:text-5xl font-black tracking-tight leading-[1.04] text-slate-900">
                        Grow your agency in Raleigh —
                        <span class="bg-gradient-to-r from-indigo-700 via-blue-700 to-emerald-600 bg-clip-text text-transparent">
                            and never miss coverage.
                        </span>
                    </h1>

                    <p class="mt-6 text-lg text-slate-600 max-w-xl">
                        HomeCare helps agencies win more inbound demand <span class="font-semibold text-slate-700">(new clients)</span>,
                        while also giving you a trusted pool of caregivers and independents to cover shifts
                        when your team is maxed out.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="#early-access">
                            <x-button color="indigo" lg icon="rocket-launch" position="left">
                                Get agency early access
                            </x-button>
                        </a>

                        <a href="#how">
                            <x-button color="emerald" lg outline icon="user-group" position="left">
                                See how staffing works
                            </x-button>
                        </a>
                    </div>

                    <div class="mt-7 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm text-slate-600">
                        <div class="flex items-center gap-2">
                            <x-icon name="inbox" class="w-5 h-5 text-indigo-700" />
                            More inbound leads
                        </div>
                        <div class="flex items-center gap-2">
                            <x-icon name="bolt" class="w-5 h-5 text-emerald-600" />
                            Faster shift coverage
                        </div>
                        <div class="flex items-center gap-2">
                            <x-icon name="chart-bar" class="w-5 h-5 text-blue-700" />
                            Clear ops visibility
                        </div>
                    </div>

                    <div class="mt-10 text-xs text-slate-500">
                        Launch area: <span class="font-semibold text-slate-700">Raleigh</span> • Cary • Apex • Wake Forest • Garner
                    </div>
                </div>

                {{-- RIGHT --}}
                <div class="space-y-5" id="early-access">
                    <x-card class="shadow-[0_10px_30px_-18px_rgba(2,6,23,0.35)] ring-1 ring-black/5">
                        <x-slot:header>
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-lg font-extrabold text-slate-900">Agency early access</div>
                                    <div class="text-sm text-slate-500">
                                        Tell us what you need — we’ll reach out with onboarding details.
                                    </div>
                                </div>
                                <x-badge color="emerald" text="Raleigh" round light />
                            </div>
                        </x-slot:header>

                        <form wire:submit.prevent="submit" class="space-y-4">
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">Agency name</div>
                                    <x-input wire:model.live="agencyName" placeholder="WakeCare Services" />
                                    @error('agencyName') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>
                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">Contact name</div>
                                    <x-input wire:model.live="contactName" placeholder="John Smith" />
                                    @error('contactName') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">Email</div>
                                    <x-input wire:model.live="email" type="email" placeholder="[email protected]" />
                                    @error('email') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>
                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">Phone (optional)</div>
                                    <x-input wire:model.live="phone" placeholder="(919) 555-0123" />
                                    @error('phone') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">Service area</div>
                                    <x-input wire:model.live="serviceArea" placeholder="Raleigh, NC" />
                                    @error('serviceArea') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">Primary goal</div>
                                    <x-select.styled
                                        wire:model.live="primaryGoal"
                                        :options="[
                                            ['label' => 'Get more clients (inbound demand)', 'value' => 'get_clients'],
                                            ['label' => 'Fill shifts (staffing coverage)', 'value' => 'fill_shifts'],
                                            ['label' => 'Both', 'value' => 'both'],
                                        ]"
                                    />
                                    @error('primaryGoal') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">Coverage needs</div>
                                    <x-select.styled
                                        wire:model.live="coverageNeeds"
                                        multiple
                                        :options="[
                                            ['label' => 'Same-day call-outs', 'value' => 'same_day'],
                                            ['label' => 'Overnights', 'value' => 'overnights'],
                                            ['label' => 'Weekends', 'value' => 'weekends'],
                                            ['label' => 'Short shifts (2–4h)', 'value' => 'short_shifts'],
                                            ['label' => 'Respite blocks', 'value' => 'respite'],
                                            ['label' => 'Specialty (dementia)', 'value' => 'dementia'],
                                        ]"
                                    />
                                    @error('coverageNeeds') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">How often do you need coverage?</div>
                                    <x-select.styled
                                        wire:model.live="staffingFrequency"
                                        :options="[
                                            ['label' => 'Daily', 'value' => 'daily'],
                                            ['label' => 'Weekly', 'value' => 'weekly'],
                                            ['label' => 'Sometimes', 'value' => 'sometimes'],
                                        ]"
                                    />
                                    @error('staffingFrequency') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="flex items-center justify-between flex-wrap gap-3 pt-1">
                                <div class="text-xs text-slate-500">
                                    We’ll only contact you about early access & onboarding.
                                </div>

                                <x-button color="indigo" type="submit" loading="submit" icon="paper-airplane" position="right">
                                    Request invite
                                </x-button>
                            </div>
                        </form>

                        <x-slot:footer>
                            <div class="grid sm:grid-cols-3 gap-3">
                                <div class="p-3 rounded-xl bg-slate-50 ring-1 ring-slate-200">
                                    <div class="text-sm font-semibold text-slate-900">Lead intake</div>
                                    <div class="text-xs text-slate-600 mt-1">Capture inbound demand in one place.</div>
                                </div>
                                <div class="p-3 rounded-xl bg-slate-50 ring-1 ring-slate-200">
                                    <div class="text-sm font-semibold text-slate-900">Coverage pool</div>
                                    <div class="text-xs text-slate-600 mt-1">Find staff/independents fast.</div>
                                </div>
                                <div class="p-3 rounded-xl bg-slate-50 ring-1 ring-slate-200">
                                    <div class="text-sm font-semibold text-slate-900">Clarity</div>
                                    <div class="text-xs text-slate-600 mt-1">Clear scheduling and request details.</div>
                                </div>
                            </div>
                        </x-slot:footer>
                    </x-card>
                </div>
            </div>
        </div>
    </section>

    {{-- VALUE --}}
    <section id="value" class="py-14">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-7">
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Two growth levers in one platform</h2>
                <p class="text-slate-600 mt-1">More demand + more coverage = fewer missed opportunities.</p>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="megaphone" class="w-5 h-5 text-indigo-700" />
                            <div class="font-bold">Get more clients</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        Show your services, coverage area, availability, and transparent pricing guidance.
                        Convert more inbound interest without endless phone-tag.
                    </p>
                    <div class="mt-4 grid sm:grid-cols-3 gap-3 text-sm text-slate-600">
                        <div class="flex items-center gap-2"><span class="text-emerald-600">✓</span> Better discovery</div>
                        <div class="flex items-center gap-2"><span class="text-emerald-600">✓</span> Faster response</div>
                        <div class="flex items-center gap-2"><span class="text-emerald-600">✓</span> Clear expectations</div>
                    </div>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="user-group" class="w-5 h-5 text-emerald-700" />
                            <div class="font-bold">Fill shifts you can’t cover</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        Post shift coverage needs and tap into vetted caregivers/independents locally.
                        Reduce cancellations and protect client satisfaction.
                    </p>
                    <div class="mt-4 grid sm:grid-cols-3 gap-3 text-sm text-slate-600">
                        <div class="flex items-center gap-2"><span class="text-emerald-600">✓</span> Same-day help</div>
                        <div class="flex items-center gap-2"><span class="text-emerald-600">✓</span> Weekends/overnights</div>
                        <div class="flex items-center gap-2"><span class="text-emerald-600">✓</span> Reduced churn</div>
                    </div>
                </x-card>
            </div>
        </div>
    </section>

    {{-- HOW IT WORKS --}}
    <section id="how" class="py-14 bg-white border-y border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between gap-6 mb-7">
                <div>
                    <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">How it works</h2>
                    <p class="text-slate-600 mt-1">Designed for agency ops: simple, fast, accountable.</p>
                </div>
                <x-badge color="slate" text="Raleigh-first rollout" round light />
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="building-office" class="w-5 h-5 text-indigo-700" />
                            <div class="font-bold">Create your agency profile</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">Service area, offerings, availability, and how families can reach you.</p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="inbox" class="w-5 h-5 text-blue-700" />
                            <div class="font-bold">Capture inbound demand</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">Receive requests, qualify quickly, and convert more leads.</p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="bolt" class="w-5 h-5 text-emerald-700" />
                            <div class="font-bold">Fill gaps with coverage</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">When you’re short-staffed, find independents to protect the schedule.</p>
                </x-card>
            </div>
        </div>
    </section>

    {{-- USE CASES --}}
    <section id="use-cases" class="py-14">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-7">
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Common agency scenarios</h2>
                <p class="text-slate-600 mt-1">Where the platform saves revenue and protects service quality.</p>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="phone-arrow-up-right" class="w-5 h-5 text-indigo-700" />
                            <div class="font-bold">More inbound leads</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        Convert “just browsing” families into qualified intakes with clearer info and faster responses.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="exclamation-triangle" class="w-5 h-5 text-emerald-700" />
                            <div class="font-bold">Last-minute call-outs</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        Fill urgent shifts with local coverage options to avoid cancellations and churn.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="calendar-days" class="w-5 h-5 text-blue-700" />
                            <div class="font-bold">Overflow coverage</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        When demand outpaces staffing, keep the client and staff the shift — instead of turning it away.
                    </p>
                </x-card>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="py-14 bg-white border-t border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-7">
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">FAQ</h2>
                <p class="text-slate-600 mt-1">Quick answers for agencies.</p>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Can we use this just for staffing?</div></x-slot:header>
                    <p class="text-sm text-slate-600">
                        Yes — you can focus purely on coverage needs or use both lead generation + staffing.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Do you work with independents?</div></x-slot:header>
                    <p class="text-sm text-slate-600">
                        Yes — agencies can find caregivers and independents to cover shifts when needed.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Is this only Raleigh?</div></x-slot:header>
                    <p class="text-sm text-slate-600">
                        Raleigh + Wake County first. We expand after we build strong matching density.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Is this medical care?</div></x-slot:header>
                    <p class="text-sm text-slate-600">
                        We focus on non-medical home care services. Medical tasks require appropriate licensure and supervision.
                    </p>
                </x-card>
            </div>

            <div class="mt-12 rounded-2xl bg-gradient-to-r from-slate-900 via-indigo-800 to-emerald-600 text-white p-6 sm:p-8 shadow-[0_20px_60px_-35px_rgba(2,6,23,0.6)]">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
                    <div>
                        <div class="text-xl font-extrabold">Want more clients and stronger coverage?</div>
                        <div class="text-sm text-white/85 mt-1">
                            Request early access — we’ll onboard agencies first in Raleigh.
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <a href="#early-access">
                            <x-button color="white">Request invite</x-button>
                        </a>
                        <a href="{{ route('caregivers.landing') }}">
                            <x-button color="white" light>For caregivers</x-button>
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-8 text-sm text-slate-500">
                Launch area: <span class="font-semibold text-slate-700">Raleigh</span> • Cary • Apex • Wake Forest • Garner
            </div>
        </div>
    </section>

    {{-- FOOTER --}}
    <footer class="bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 border-t border-slate-200">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div class="text-sm text-slate-600">
                    <div class="font-semibold text-slate-900">HomeCare</div>
                    <div>Agency growth + staffing • Raleigh, NC</div>
                </div>

                <div class="text-xs text-slate-500 max-w-xl">
                    Safety note: caregivers operate independently or through licensed agencies. This platform provides matching and communication tools.
                </div>
            </div>
        </div>
    </footer>

    {{-- Sticky mobile CTA --}}
    <div class="lg:hidden fixed bottom-0 inset-x-0 z-50 bg-white/95 backdrop-blur border-t border-slate-200 p-3">
        <div class="max-w-md mx-auto flex gap-2">
            <a href="#early-access" class="w-1/2">
                <x-button class="w-full" color="indigo" icon="rocket-launch" position="left">
                    Early access
                </x-button>
            </a>
            <a href="{{ route('caregivers.landing') }}" class="w-1/2">
                <x-button class="w-full" color="emerald" outline icon="user-plus" position="left">
                    Find caregivers
                </x-button>
            </a>
        </div>
    </div>
    <div class="lg:hidden h-20"></div>
</div>