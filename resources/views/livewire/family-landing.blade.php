<div class="min-h-screen bg-slate-50 text-slate-900">
    {{-- NAV --}}
    <header class="sticky top-0 z-40 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-4">
            <a href="{{ url('/') }}" class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-2xl bg-gradient-to-br from-blue-700 via-indigo-700 to-emerald-500 shadow-sm ring-1 ring-black/5"></div>
                <div class="leading-tight">
                    <div class="text-lg font-extrabold tracking-tight text-slate-900">HomeCare</div>
                    <div class="text-xs text-slate-500">Families first • Raleigh, NC</div>
                </div>
            </a>

            <nav class="hidden lg:flex items-center gap-6 text-sm text-slate-600">
                <a href="#why" class="hover:text-slate-900 transition">Why HomeCare</a>
                <a href="#how" class="hover:text-slate-900 transition">How it works</a>
                <a href="#trust" class="hover:text-slate-900 transition">Trust & Safety</a>
                <a href="#faq" class="hover:text-slate-900 transition">FAQ</a>
            </nav>

            <div class="flex items-center gap-2">
                <a href="{{ route('login') }}">
                    <x-button color="slate" light sm>Sign in</x-button>
                </a>
                <a href="{{ route('register') }}">
                    <x-button color="blue" sm>Join</x-button>
                </a>
            </div>
        </div>
    </header>

    {{-- HERO --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-white via-white to-slate-50"></div>
        <div class="absolute -top-36 -right-28 h-[30rem] w-[30rem] rounded-full bg-blue-200/45 blur-3xl"></div>
        <div class="absolute -bottom-40 -left-28 h-[32rem] w-[32rem] rounded-full bg-emerald-200/35 blur-3xl"></div>
        <div class="absolute inset-0 opacity-[0.06]"
             style="background-image: radial-gradient(circle at 1px 1px, rgb(15 23 42) 1px, transparent 0); background-size: 28px 28px;">
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-14 pb-12 lg:pt-20 lg:pb-16">
            <div class="grid lg:grid-cols-2 gap-10 items-center">
                {{-- LEFT --}}
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-6">
                        <x-badge color="blue" text="Launching in Raleigh" round light />
                        <x-badge color="emerald" text="Neutral third-party" round light />
                        <x-badge color="slate" text="Clear rates • secure messaging" round light />
                    </div>

                    <h1 class="text-4xl sm:text-5xl font-black tracking-tight leading-[1.04] text-slate-900">
                        Find in-home care you can trust —
                        <span class="bg-gradient-to-r from-blue-700 via-indigo-700 to-emerald-600 bg-clip-text text-transparent">
                            without the uncertainty.
                        </span>
                    </h1>

                    <p class="mt-6 text-lg text-slate-600 max-w-xl">
                        The home care industry can feel opaque: unclear rates, vague availability, and you’re asked to “just trust.”
                        HomeCare acts as a <span class="font-semibold text-slate-700">neutral third party</span> to bring clarity,
                        safer matching, and accountability — starting in Raleigh, NC.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="#early-access">
                            <x-button color="blue" lg icon="magnifying-glass" position="left">
                                Get family early access
                            </x-button>
                        </a>

                        <a href="#trust">
                            <x-button color="emerald" lg outline icon="shield-check" position="left">
                                See trust & safety
                            </x-button>
                        </a>
                    </div>

                    <div class="mt-7 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm text-slate-600">
                        <div class="flex items-center gap-2">
                            <x-icon name="eye" class="w-5 h-5 text-blue-700" />
                            Less opaque info
                        </div>
                        <div class="flex items-center gap-2">
                            <x-icon name="shield-check" class="w-5 h-5 text-emerald-600" />
                            Safety-first tools
                        </div>
                        <div class="flex items-center gap-2">
                            <x-icon name="chat-bubble-left-right" class="w-5 h-5 text-indigo-700" />
                            Secure messaging
                        </div>
                    </div>

                    <div class="mt-10 text-xs text-slate-500">
                        Serving <span class="font-semibold text-slate-700">Raleigh</span> • Cary • Apex • Wake Forest • Garner
                    </div>
                </div>

                {{-- RIGHT --}}
                <div class="space-y-5" id="early-access">
                    <x-card class="shadow-[0_10px_30px_-18px_rgba(2,6,23,0.35)] ring-1 ring-black/5">
                        <x-slot:header>
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-lg font-extrabold text-slate-900">Family early access</div>
                                    <div class="text-sm text-slate-500">
                                        Tell us what you need — we’ll notify you when Raleigh opens.
                                    </div>
                                </div>
                                <x-badge color="blue" text="Beta" round light />
                            </div>
                        </x-slot:header>

                        <form wire:submit.prevent="submit" class="space-y-4">
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">Location</div>
                                    <x-input wire:model.live="location" placeholder="Raleigh, NC" />
                                    @error('location') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">Care type</div>
                                    <x-select.styled
                                        wire:model.live="careType"
                                        :options="[
                                            ['label' => 'Companion care', 'value' => 'companion'],
                                            ['label' => 'Personal care assistance', 'value' => 'personal'],
                                            ['label' => 'Dementia support', 'value' => 'dementia'],
                                            ['label' => 'Respite care', 'value' => 'respite'],
                                            ['label' => 'Post-hospital support', 'value' => 'post_hospital'],
                                        ]"
                                    />
                                    @error('careType') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">Who is this for?</div>
                                    <x-select.styled
                                        wire:model.live="whoFor"
                                        :options="[
                                            ['label' => 'Parent', 'value' => 'parent'],
                                            ['label' => 'Spouse/partner', 'value' => 'spouse'],
                                            ['label' => 'Myself', 'value' => 'self'],
                                            ['label' => 'Other', 'value' => 'other'],
                                        ]"
                                    />
                                    @error('whoFor') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">Schedule</div>
                                    <x-select.styled
                                        wire:model.live="schedule"
                                        :options="[
                                            ['label' => 'Part-time (few hours)', 'value' => 'part_time'],
                                            ['label' => 'Full-time', 'value' => 'full_time'],
                                            ['label' => 'Overnights', 'value' => 'overnight'],
                                            ['label' => 'Weekends', 'value' => 'weekends'],
                                        ]"
                                    />
                                    @error('schedule') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">When do you need care?</div>
                                    <x-select.styled
                                        wire:model.live="when"
                                        :options="[
                                            ['label' => 'ASAP', 'value' => 'asap'],
                                            ['label' => 'Today / Tomorrow', 'value' => 'today'],
                                            ['label' => 'This week', 'value' => 'this_week'],
                                            ['label' => 'This month', 'value' => 'this_month'],
                                        ]"
                                    />
                                    @error('when') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">Email</div>
                                    <x-input wire:model.live="email" type="email" placeholder="[email protected]" />
                                    @error('email') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">Phone (optional)</div>
                                    <x-input wire:model.live="phone" placeholder="(919) 555-0123" />
                                    @error('phone') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>

                                <div>
                                    <div class="text-xs font-semibold text-slate-600 mb-1">Top priorities</div>
                                    <x-select.styled
                                        wire:model.live="priorities"
                                        multiple
                                        :options="[
                                            ['label' => 'Safety & verification', 'value' => 'safety'],
                                            ['label' => 'Experience level', 'value' => 'experience'],
                                            ['label' => 'Dementia experience', 'value' => 'dementia'],
                                            ['label' => 'Language match', 'value' => 'language'],
                                            ['label' => 'Clear pricing', 'value' => 'price'],
                                            ['label' => 'Same-day availability', 'value' => 'same_day'],
                                        ]"
                                    />
                                    @error('priorities') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div>
                                <div class="text-xs font-semibold text-slate-600 mb-1">Notes (optional)</div>
                                <x-textarea wire:model.live="notes" rows="3" placeholder="Anything important (mobility, schedule constraints, preferences)..." />
                                @error('notes') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="flex items-center justify-between flex-wrap gap-3 pt-1">
                                <div class="text-xs text-slate-500">
                                    We’ll only email about launch & early access.
                                </div>

                                <x-button color="blue" type="submit" loading="submit" icon="paper-airplane" position="right">
                                    Notify me
                                </x-button>
                            </div>
                        </form>

                        <x-slot:footer>
                            <div class="grid sm:grid-cols-3 gap-3">
                                <div class="p-3 rounded-xl bg-slate-50 ring-1 ring-slate-200">
                                    <div class="text-sm font-semibold text-slate-900">Clear expectations</div>
                                    <div class="text-xs text-slate-600 mt-1">Know what’s offered before you commit.</div>
                                </div>
                                <div class="p-3 rounded-xl bg-slate-50 ring-1 ring-slate-200">
                                    <div class="text-sm font-semibold text-slate-900">Neutral platform</div>
                                    <div class="text-xs text-slate-600 mt-1">We’re not a sales call center.</div>
                                </div>
                                <div class="p-3 rounded-xl bg-slate-50 ring-1 ring-slate-200">
                                    <div class="text-sm font-semibold text-slate-900">Safer matching</div>
                                    <div class="text-xs text-slate-600 mt-1">Tools that encourage accountability.</div>
                                </div>
                            </div>
                        </x-slot:footer>
                    </x-card>
                </div>
            </div>
        </div>
    </section>

    {{-- WHY --}}
    <section id="why" class="py-14">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-7">
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Why HomeCare</h2>
                <p class="text-slate-600 mt-1">Because “trust me” shouldn’t be the whole process.</p>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="eye-slash" class="w-5 h-5 text-blue-700" />
                            <div class="font-bold">The industry is opaque</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        Families often can’t compare clearly: rates, availability, scope of care, and expectations are scattered.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="hand-raised" class="w-5 h-5 text-emerald-700" />
                            <div class="font-bold">You’re asked to “just trust”</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        When it’s your loved one, uncertainty is stressful. We push the process toward clarity and accountability.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="scale" class="w-5 h-5 text-indigo-700" />
                            <div class="font-bold">Neutral third party</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">
                        HomeCare is a marketplace and communication layer — not a biased broker. We help both sides do better.
                    </p>
                </x-card>
            </div>
        </div>
    </section>

    {{-- HOW --}}
    <section id="how" class="py-14 bg-white border-y border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between gap-6 mb-7">
                <div>
                    <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">How it works</h2>
                    <p class="text-slate-600 mt-1">Simple steps. Less chaos.</p>
                </div>
                <x-badge color="slate" text="Raleigh-first rollout" round light />
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="magnifying-glass" class="w-5 h-5 text-blue-700" />
                            <div class="font-bold">Search</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">Browse caregivers and agencies by service, location, and availability.</p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="adjustments-horizontal" class="w-5 h-5 text-indigo-700" />
                            <div class="font-bold">Compare</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">See clearer rates, experience, and what each option includes.</p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="chat-bubble-left-right" class="w-5 h-5 text-emerald-700" />
                            <div class="font-bold">Connect</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">Message securely and move forward when you’re ready.</p>
                </x-card>
            </div>
        </div>
    </section>

    {{-- TRUST --}}
    <section id="trust" class="py-14">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-7">
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Trust & safety</h2>
                <p class="text-slate-600 mt-1">Tools that help families feel confident — and caregivers stay accountable.</p>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="user-circle" class="w-5 h-5 text-emerald-700" />
                            <div class="font-bold">Verification tools</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">Profile completeness, identity checks, and optional background checks.</p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="lock-closed" class="w-5 h-5 text-blue-700" />
                            <div class="font-bold">Secure messaging</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">Keep communication inside the platform with privacy controls.</p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="star" class="w-5 h-5 text-indigo-700" />
                            <div class="font-bold">Reviews & accountability</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">Build trust through clear expectations and transparent feedback.</p>
                </x-card>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="py-14 bg-white border-t border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-7">
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">FAQ</h2>
                <p class="text-slate-600 mt-1">Quick answers for families.</p>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Is this only Raleigh?</div></x-slot:header>
                    <p class="text-sm text-slate-600">
                        We’re launching Raleigh + Wake County first to build density and quality. Expansion comes next.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Is HomeCare an agency?</div></x-slot:header>
                    <p class="text-sm text-slate-600">
                        No — we’re a marketplace and communication layer. We aim to be a neutral third party that improves clarity and accountability.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Do you provide medical care?</div></x-slot:header>
                    <p class="text-sm text-slate-600">
                        We focus on non-medical home care services. Medical tasks require appropriate licensure and supervision.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Can we hire an independent caregiver?</div></x-slot:header>
                    <p class="text-sm text-slate-600">
                        Yes — families can connect with independents or licensed agencies, depending on preference and needs.
                    </p>
                </x-card>
            </div>

            <div class="mt-12 rounded-2xl bg-gradient-to-r from-blue-700 via-indigo-700 to-emerald-600 text-white p-6 sm:p-8 shadow-[0_20px_60px_-35px_rgba(2,6,23,0.6)]">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
                    <div>
                        <div class="text-xl font-extrabold">Ready for a clearer way to find care?</div>
                        <div class="text-sm text-white/85 mt-1">
                            Join the family early access list for Raleigh.
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <a href="#early-access">
                            <x-button color="white">Get early access</x-button>
                        </a>
                        <a href="{{ route('agencies.landing') }}">
                            <x-button color="white" light>For agencies</x-button>
                        </a>
                    </div>
                </div>
            </div>

            <div class="mt-8 text-sm text-slate-500">
                Serving: <span class="font-semibold text-slate-700">Raleigh</span> • Cary • Apex • Wake Forest • Garner
            </div>
        </div>
    </section>

    {{-- FOOTER --}}
    <footer class="bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 border-t border-slate-200">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div class="text-sm text-slate-600">
                    <div class="font-semibold text-slate-900">HomeCare</div>
                    <div>Families first • Raleigh, NC</div>
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
                <x-button class="w-full" color="blue" icon="magnifying-glass" position="left">
                    Early access
                </x-button>
            </a>
            <a href="{{ route('caregivers.landing') }}" class="w-1/2">
                <x-button class="w-full" color="emerald" outline icon="user-plus" position="left">
                    For caregivers
                </x-button>
            </a>
        </div>
    </div>
    <div class="lg:hidden h-20"></div>
</div>