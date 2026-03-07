<div class="min-h-screen bg-slate-50 text-slate-900">
    {{-- NAV --}}
    <header class="sticky top-0 z-40 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-4">
            <a href="{{ url('/') }}" class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-2xl bg-gradient-to-br from-indigo-700 via-blue-700 to-emerald-500 shadow-sm ring-1 ring-black/5"></div>
                <div class="leading-tight">
                    <div class="text-lg font-extrabold tracking-tight text-slate-900">HomeCare</div>
                    <div class="text-xs text-slate-500">Caregiver marketplace • Raleigh, NC</div>
                </div>
            </a>

            <nav class="hidden lg:flex items-center gap-6 text-sm text-slate-600">
                <a href="#earnings" class="hover:text-slate-900 transition">Earnings</a>
                <a href="#benefits" class="hover:text-slate-900 transition">Benefits</a>
                <a href="#how" class="hover:text-slate-900 transition">How it works</a>
                <a href="#trust" class="hover:text-slate-900 transition">Trust</a>
                <a href="#faq" class="hover:text-slate-900 transition">FAQ</a>
            </nav>

            <div class="flex items-center gap-2">
                <a href="{{ route('login') }}">
                    <x-button color="slate" light sm>Sign in</x-button>
                </a>
                <a href="#early-access">
                    <x-button color="emerald" sm icon="sparkles" position="left">Get invite</x-button>
                </a>
            </div>
        </div>
    </header>

    {{-- HERO --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-white via-white to-slate-50"></div>
        <div class="absolute -top-32 -right-28 h-[28rem] w-[28rem] rounded-full bg-indigo-200/50 blur-3xl"></div>
        <div class="absolute -bottom-36 -left-28 h-[30rem] w-[30rem] rounded-full bg-emerald-200/40 blur-3xl"></div>
        <div class="absolute inset-0 opacity-[0.06]"
             style="background-image: radial-gradient(circle at 1px 1px, rgb(15 23 42) 1px, transparent 0); background-size: 28px 28px;">
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-14 pb-12 lg:pt-20 lg:pb-16">
            <div class="grid lg:grid-cols-2 gap-10 items-center">
                {{-- LEFT --}}
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-6">
                        <x-badge color="emerald" text="Caregiver early access" round light />
                        <x-badge color="indigo" text="Raleigh-first launch" round light />
                        <x-badge color="slate" text="Set your own rates" round light />
                    </div>

                    <h1 class="text-4xl sm:text-5xl font-black tracking-tight leading-[1.04] text-slate-900">
                        Earn more in Raleigh.
                        <span class="bg-gradient-to-r from-indigo-700 via-blue-700 to-emerald-600 bg-clip-text text-transparent">
                            Work with families directly.
                        </span>
                        Keep your schedule.
                    </h1>

                    <p class="mt-6 text-lg text-slate-600 max-w-xl">
                        HomeCare helps caregivers get discovered by local families with clear job details upfront —
                        so you can pick the work that fits your life and earn what you’re worth.
                    </p>

                    {{-- Earnings callout --}}
                    <div class="mt-7 max-w-xl">
                        <div class="rounded-2xl bg-white/80 backdrop-blur ring-1 ring-black/5 p-4 shadow-sm">
                            <div class="flex items-start gap-3">
                                <div class="h-10 w-10 rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 flex items-center justify-center">
                                    <x-icon name="banknotes" class="w-5 h-5 text-emerald-700" />
                                </div>
                                <div class="flex-1">
                                    <div class="font-extrabold text-slate-900">Typical pay: keep more of what you earn</div>
                                    <div class="text-sm text-slate-600 mt-1">
                                        Many agency roles pay <span class="font-semibold text-slate-800">$15–$18/hr</span>.
                                        Caregivers working directly with families often earn
                                        <span class="font-semibold text-slate-800">$22–$30+/hr</span>
                                        depending on experience, schedule, and services.
                                    </div>
                                    <div class="text-xs text-slate-500 mt-2">
                                        You set your rate — families see it clearly before they reach out.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="#early-access">
                            <x-button color="emerald" lg icon="sparkles" position="left">
                                Get caregiver early access
                            </x-button>
                        </a>

                        <a href="#how">
                            <x-button color="indigo" lg outline icon="play" position="left">
                                How it works
                            </x-button>
                        </a>
                    </div>

                    <div class="mt-7 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm text-slate-600">
                        <div class="flex items-center gap-2">
                            <x-icon name="currency-dollar" class="w-5 h-5 text-emerald-600" />
                            Higher take-home pay
                        </div>
                        <div class="flex items-center gap-2">
                            <x-icon name="calendar-days" class="w-5 h-5 text-indigo-700" />
                            Flexible schedule
                        </div>
                        <div class="flex items-center gap-2">
                            <x-icon name="map-pin" class="w-5 h-5 text-blue-700" />
                            Clear job details
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
                                    <div class="text-lg font-extrabold text-slate-900">Get early access</div>
                                    <div class="text-sm text-slate-500">
                                        Be first in Raleigh. We’ll invite you when caregiver profiles open.
                                    </div>
                                </div>
                                <x-badge color="indigo" text="Beta" round light />
                            </div>
                        </x-slot:header>

                        {{-- Trust bump --}}
                        <div class="px-6 pt-0">
                            <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 p-3 text-xs text-slate-600">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <div class="flex items-center gap-1.5">
                                        <x-icon name="shield-check" class="w-4 h-4 text-emerald-700" />
                                        No spam
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <x-icon name="lock-closed" class="w-4 h-4 text-indigo-700" />
                                        Private info
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <x-icon name="map-pin" class="w-4 h-4 text-blue-700" />
                                        Raleigh-first
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form wire:submit.prevent="submit" class="space-y-4 px-6 pb-6 pt-4">
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">First name</div>
                                    <x-input wire:model.live="firstName" placeholder="Jane" />
                                    @error('firstName') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">Last name</div>
                                    <x-input wire:model.live="lastName" placeholder="Doe" />
                                    @error('lastName') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">Email</div>
                                    <x-input wire:model.live="email" type="email" placeholder="[email protected]" />
                                    @error('email') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">ZIP code</div>
                                    <x-input wire:model.live="zip" placeholder="27601" />
                                    @error('zip') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">Caregiving is…</div>
                                    <x-select.styled
                                        wire:model.live="caregivingIntent"
                                        :options="[
                                            ['label' => 'A few gigs here and there', 'value' => 'gig'],
                                            ['label' => 'My main occupation', 'value' => 'main'],
                                        ]"
                                    />
                                    @error('caregivingIntent') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">I currently work with…</div>
                                    <x-select.styled
                                        wire:model.live="workChannel"
                                        :options="[
                                            ['label' => 'An agency', 'value' => 'agency'],
                                            ['label' => 'HomeCare platform (direct with families)', 'value' => 'platform'],
                                        ]"
                                    />
                                    @error('workChannel') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            {{-- Optional section (collapsed feel without JS) --}}
                            <div class="rounded-2xl bg-white ring-1 ring-black/5 p-4">
                                <div class="flex items-center gap-2 mb-3">
                                    <x-icon name="plus-circle" class="w-5 h-5 text-slate-500" />
                                    <div class="text-sm font-semibold text-slate-900">Optional: add more details (helps matching)</div>
                                </div>

                                <div class="grid sm:grid-cols-2 gap-4">
                                    <div>
                                        <div class="text-xs font-semibold text-slate-600 mb-1">Availability</div>
                                        <x-select.styled
                                            wire:model.live="availability"
                                            :options="[
                                                ['label' => 'Full-time', 'value' => 'full_time'],
                                                ['label' => 'Part-time', 'value' => 'part_time'],
                                                ['label' => 'Weekends', 'value' => 'weekends'],
                                                ['label' => 'Overnights', 'value' => 'overnights'],
                                            ]"
                                        />
                                        @error('availability') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                    </div>

                                    <div>
                                        <div class="text-xs font-semibold text-slate-600 mb-1">Services you offer</div>
                                        <x-select.styled
                                            wire:model.live="services"
                                            multiple
                                            :options="[
                                                ['label' => 'Companion care', 'value' => 'companion'],
                                                ['label' => 'Personal care assistance', 'value' => 'personal'],
                                                ['label' => 'Transportation', 'value' => 'transport'],
                                                ['label' => 'Light housekeeping', 'value' => 'housekeeping'],
                                                ['label' => 'Respite care', 'value' => 'respite'],
                                                ['label' => 'Dementia support', 'value' => 'dementia'],
                                            ]"
                                        />
                                        @error('services') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Conversion copy --}}
                            <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 p-3 text-sm text-slate-700">
                                <div class="font-semibold text-slate-900">What happens next?</div>
                                <div class="text-xs text-slate-600 mt-1">
                                    We’ll email you when caregiver profiles open in Raleigh. Early caregivers get first access to requests.
                                </div>
                            </div>

                            <div class="flex items-center justify-between flex-wrap gap-3 pt-1">
                                <div class="text-xs text-slate-500">
                                    We’ll only email about early access and launch details.
                                </div>

                                <x-button color="emerald" type="submit" loading="submit" icon="paper-airplane" position="right">
                                    Request invite
                                </x-button>
                            </div>
                        </form>

                        <x-slot:footer>
                            <div class="grid sm:grid-cols-3 gap-3">
                                <div class="p-3 rounded-xl bg-slate-50 ring-1 ring-slate-200">
                                    <div class="text-sm font-semibold text-slate-900">Better pay</div>
                                    <div class="text-xs text-slate-600 mt-1">Clear rates, less markup.</div>
                                </div>
                                <div class="p-3 rounded-xl bg-slate-50 ring-1 ring-slate-200">
                                    <div class="text-sm font-semibold text-slate-900">More control</div>
                                    <div class="text-xs text-slate-600 mt-1">Choose schedule and clients.</div>
                                </div>
                                <div class="p-3 rounded-xl bg-slate-50 ring-1 ring-slate-200">
                                    <div class="text-sm font-semibold text-slate-900">Your reputation</div>
                                    <div class="text-xs text-slate-600 mt-1">Reviews that follow you.</div>
                                </div>
                            </div>
                        </x-slot:footer>
                    </x-card>
                </div>
            </div>
        </div>
    </section>

    {{-- EARNINGS --}}
    <section id="earnings" class="py-14">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-7">
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Earnings that make sense</h2>
                <p class="text-slate-600 mt-1">Caregiving is skilled work — you should be paid like it.</p>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="building-office-2" class="w-5 h-5 text-slate-700" />
                            <div class="font-bold">Traditional agency</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        Agencies handle staffing, but caregivers may take home around
                        <span class="font-semibold">$15–$18/hr</span> depending on role and schedule.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="sparkles" class="w-5 h-5 text-indigo-700" />
                            <div class="font-bold">Work with HomeCare</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        Work directly with local families and typically earn
                        <span class="font-semibold">$22–$30+/hr</span>.
                        You set your rate — and your schedule.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="star" class="w-5 h-5 text-emerald-700" />
                            <div class="font-bold">Build long-term value</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        Reviews and reliability badges help you stand out — and earn more over time.
                    </p>
                </x-card>
            </div>

            <div class="mt-8 rounded-2xl bg-white ring-1 ring-black/5 p-5 sm:p-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="text-lg font-extrabold text-slate-900">You’re in control</div>
                        <div class="text-sm text-slate-600 mt-1">
                            Choose hours, distance, and services — with clear expectations before you accept.
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <a href="#early-access">
                            <x-button color="emerald" icon="sparkles" position="left">Get invite</x-button>
                        </a>
                        <a href="#how">
                            <x-button color="indigo" outline icon="play" position="left">How it works</x-button>
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-3 text-xs text-slate-500">
                Notes: pay ranges vary based on experience, services, schedule, and local demand.
            </div>
        </div>
    </section>

    {{-- BENEFITS --}}
    <section id="benefits" class="py-14">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-7">
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Why caregivers choose HomeCare</h2>
                <p class="text-slate-600 mt-1">Everything you need to run your work like a pro — without the chaos.</p>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="hand-raised" class="w-5 h-5 text-emerald-700" />
                            <div class="font-bold">Be your own boss</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        Choose your clients and set boundaries that protect your time and energy.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="map-pin" class="w-5 h-5 text-blue-700" />
                            <div class="font-bold">Clear job details</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        Know location, schedule, and tasks up front so you can accept confidently.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="calendar-days" class="w-5 h-5 text-indigo-700" />
                            <div class="font-bold">Flexible schedule</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        Take a few gigs or make this your main work — you decide.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="star" class="w-5 h-5 text-emerald-700" />
                            <div class="font-bold">Your reputation follows you</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        Build reviews that help you win better requests and higher rates over time.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="chat-bubble-left-right" class="w-5 h-5 text-indigo-700" />
                            <div class="font-bold">Secure messaging</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        Keep communication in one place so details don’t get lost.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="shield-check" class="w-5 h-5 text-blue-700" />
                            <div class="font-bold">Trust-first platform</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        Identity checks, optional verification, and clear expectations for safer matches.
                    </p>
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
                    <p class="text-slate-600 mt-1">Simple steps to start getting requests.</p>
                </div>
                <x-badge color="slate" text="Raleigh-first rollout" round light />
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="sparkles" class="w-5 h-5 text-emerald-700" />
                            <div class="font-bold">Request an invite</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        Join the early access list. We’ll onboard Raleigh caregivers first.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="identification" class="w-5 h-5 text-indigo-700" />
                            <div class="font-bold">Create your profile</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        Add services, availability, and your rate so families can reach out.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="inbox" class="w-5 h-5 text-blue-700" />
                            <div class="font-bold">Receive requests</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        Review details and accept only the jobs you want.
                    </p>
                </x-card>
            </div>

            {{-- Inline CTA --}}
            <div class="mt-8 rounded-2xl bg-slate-50 ring-1 ring-slate-200 p-5 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div class="font-extrabold text-slate-900">Be in the first caregiver wave</div>
                        <div class="text-sm text-slate-600 mt-1">Early access caregivers get priority profile placement at launch.</div>
                    </div>
                    <a href="#early-access">
                        <x-button color="emerald" icon="sparkles" position="left">Get invite</x-button>
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- TRUST --}}
    <section id="trust" class="py-14">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-7">
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Trust & safety</h2>
                <p class="text-slate-600 mt-1">Built to protect caregivers and families with clarity.</p>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="user-circle" class="w-5 h-5 text-emerald-700" />
                            <div class="font-bold">Verification options</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">Identity checks and optional background verification (when available).</p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="lock-closed" class="w-5 h-5 text-indigo-700" />
                            <div class="font-bold">Secure communication</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">Keep everything in-platform with privacy controls.</p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="document-check" class="w-5 h-5 text-blue-700" />
                            <div class="font-bold">Clear expectations</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">Know tasks, location, and schedule before accepting.</p>
                </x-card>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="py-14 bg-white border-t border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-7">
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">FAQ</h2>
                <p class="text-slate-600 mt-1">Quick answers for caregivers.</p>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Is this only for full-time caregivers?</div></x-slot:header>
                    <p class="text-sm text-slate-600">
                        No — it works for a few gigs here and there or as your main occupation. You control what you accept.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Do I need to quit my agency to join?</div></x-slot:header>
                    <p class="text-sm text-slate-600">
                        Not at all. Many caregivers start by taking occasional requests through HomeCare while keeping other work.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Will I see job details before accepting?</div></x-slot:header>
                    <p class="text-sm text-slate-600">
                        Yes. We show key details upfront: location, schedule, and what support is needed.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Is this medical care?</div></x-slot:header>
                    <p class="text-sm text-slate-600">
                        We focus on non-medical home care services. Medical tasks require appropriate licensure and supervision.
                    </p>
                </x-card>
            </div>

            <div class="mt-12 rounded-2xl bg-gradient-to-r from-indigo-700 via-blue-700 to-emerald-600 text-white p-6 sm:p-8 shadow-[0_20px_60px_-35px_rgba(2,6,23,0.6)]">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
                    <div>
                        <div class="text-xl font-extrabold">Ready to earn more in Raleigh?</div>
                        <div class="text-sm text-white/85 mt-1">
                            Request an invite — we’ll onboard caregivers in waves.
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <a href="#early-access">
                            <x-button color="white">Request invite</x-button>
                        </a>
                        <a href="{{ url('/') }}">
                            <x-button color="white" light>For families</x-button>
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
                    <div>Caregiver marketplace • Raleigh, NC</div>
                </div>

                <div class="text-xs text-slate-500 max-w-xl">
                    Safety note: caregivers may work through agencies or independently via the HomeCare platform. This platform provides matching and communication tools.
                </div>
            </div>
        </div>
    </footer>

    {{-- Sticky mobile CTA --}}
    <div class="lg:hidden fixed bottom-0 inset-x-0 z-50 bg-white/95 backdrop-blur border-t border-slate-200 p-3">
        <div class="max-w-md mx-auto flex gap-2">
            <a href="#early-access" class="w-1/2">
                <x-button class="w-full" color="emerald" icon="sparkles" position="left">
                    Get invite
                </x-button>
            </a>
            <a href="{{ url('/') }}" class="w-1/2">
                <x-button class="w-full" color="indigo" outline icon="home" position="left">
                    Families
                </x-button>
            </a>
        </div>
    </div>
    <div class="lg:hidden h-20"></div>
</div>