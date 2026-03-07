<div class="min-h-screen bg-slate-50 text-slate-900">
    {{-- NAV --}}
    <header class="sticky top-0 z-40 bg-white/80 backdrop-blur-md border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-2xl bg-gradient-to-br from-indigo-700 via-blue-700 to-emerald-500 shadow-sm ring-1 ring-black/5"></div>
                <div class="leading-tight">
                    <div class="text-lg font-extrabold tracking-tight text-slate-900">HomeCare</div>
                    <div class="text-xs text-slate-500">Raleigh, NC home care marketplace</div>
                </div>
            </div>

            <nav class="hidden lg:flex items-center gap-6 text-sm text-slate-600">
                <a href="#how" class="hover:text-slate-900 transition">How it works</a>
                <a href="#services" class="hover:text-slate-900 transition">Services</a>
                <a href="#trust" class="hover:text-slate-900 transition">Trust</a>
                <a href="#faq" class="hover:text-slate-900 transition">FAQ</a>
            </nav>

            <div class="hidden md:flex items-center gap-2">
                <x-badge color="indigo" text="Local-first" round light />
                <x-badge color="emerald" text="Transparent rates" round light />
                <x-badge color="slate" text="Secure messaging" round light />
            </div>

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
        {{-- background --}}
        <div class="absolute inset-0 bg-gradient-to-b from-white via-white to-slate-50"></div>
        <div class="absolute -top-32 -right-28 h-[28rem] w-[28rem] rounded-full bg-indigo-200/50 blur-3xl"></div>
        <div class="absolute -bottom-36 -left-28 h-[30rem] w-[30rem] rounded-full bg-emerald-200/40 blur-3xl"></div>

        {{-- subtle grid pattern --}}
        <div class="absolute inset-0 opacity-[0.06]"
             style="background-image: radial-gradient(circle at 1px 1px, rgb(15 23 42) 1px, transparent 0); background-size: 28px 28px;">
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-14 pb-12 lg:pt-20 lg:pb-16">
            <div class="grid lg:grid-cols-2 gap-10 items-center">

                {{-- LEFT --}}
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-6">
                        <x-badge color="emerald" text="Launching soon in Raleigh" round light />
                        <x-badge color="indigo" text="Independent + Agency caregivers" round light />
                        <x-badge color="slate" text="Care. Not chaos." round light />
                    </div>

                    <h1 class="text-4xl sm:text-5xl font-black tracking-tight leading-[1.04] text-slate-900">
                        Trusted in-home care,
                        <span class="bg-gradient-to-r from-indigo-700 via-blue-700 to-emerald-600 bg-clip-text text-transparent">
                            without the runaround
                        </span>
                        in Raleigh, NC.
                    </h1>

                    <p class="mt-6 text-lg text-slate-600 max-w-xl">
                        Compare caregivers and agencies, see clear rates, message securely,
                        and find the right fit for your family.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <x-button color="indigo" lg icon="magnifying-glass" position="left">
                            I need care
                        </x-button>

                        <x-button color="emerald" lg outline icon="user-plus" position="left">
                            I provide care
                        </x-button>
                    </div>

                    <div class="mt-7 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm text-slate-600">
                        <div class="flex items-center gap-2">
                            <x-icon name="shield-check" class="w-5 h-5 text-emerald-600" />
                            Safety-first tools
                        </div>
                        <div class="flex items-center gap-2">
                            <x-icon name="currency-dollar" class="w-5 h-5 text-indigo-700" />
                            Transparent pricing
                        </div>
                        <div class="flex items-center gap-2">
                            <x-icon name="chat-bubble-left-right" class="w-5 h-5 text-blue-700" />
                            Secure messaging
                        </div>
                    </div>

                    <div class="mt-10 text-xs text-slate-500">
                        Serving <span class="font-semibold text-slate-700">Raleigh</span> • Cary • Apex • Wake Forest • Garner
                    </div>
                </div>

                {{-- RIGHT --}}
                <div class="space-y-5">
                    {{-- Hero image --}}
                    <div class="hidden lg:block">
                        <div class="relative rounded-2xl overflow-hidden bg-white ring-1 ring-black/5 shadow-[0_10px_30px_-18px_rgba(2,6,23,0.35)]">
                            <img
                                class="w-full h-[260px] object-cover"
                                src="https://images.unsplash.com/photo-1584515933487-779824d29309?auto=format&fit=crop&w=1600&q=80"
                                alt="Caregiver helping a senior at home"
                            />
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/40 via-transparent to-transparent"></div>

                            <div class="absolute bottom-3 left-3 right-3 flex items-center justify-between text-white">
                                <div class="text-sm font-semibold">Raleigh-first launch</div>
                                <x-badge color="slate" text="Wake County" round />
                            </div>
                        </div>
                    </div>

                    {{-- Early access card --}}
                    <x-card class="shadow-[0_10px_30px_-18px_rgba(2,6,23,0.35)] ring-1 ring-black/5">
                        <x-slot:header>
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-lg font-extrabold text-slate-900">Get early access</div>
                                    <div class="text-sm text-slate-500">
                                        Tell us what you need — we’ll notify you when Raleigh opens.
                                    </div>
                                </div>
                                <x-badge color="indigo" text="Beta" round light />
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
                                            ['label' => 'Personal care', 'value' => 'personal'],
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
                                    <div class="text-xs font-semibold text-slate-600 mb-1">When</div>
                                    <x-select.styled
                                        wire:model.live="when"
                                        :options="[
                                            ['label' => 'ASAP', 'value' => 'asap'],
                                            ['label' => 'Today / Tomorrow', 'value' => 'today'],
                                            ['label' => 'This week', 'value' => 'this_week'],
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

                            <div class="flex items-center justify-between flex-wrap gap-3 pt-1">
                                <div class="text-xs text-slate-500">
                                    We’ll only email about launch & early access.
                                </div>

                                <x-button color="indigo" type="submit" loading="submit" icon="paper-airplane" position="right">
                                    Notify me
                                </x-button>
                            </div>
                        </form>

                        <x-slot:footer>
                            <div class="grid sm:grid-cols-3 gap-3">
                                <div class="p-3 rounded-xl bg-slate-50 ring-1 ring-slate-200">
                                    <div class="text-sm font-semibold text-slate-900">Local-only launch</div>
                                    <div class="text-xs text-slate-600 mt-1">Raleigh + Wake County first.</div>
                                </div>
                                <div class="p-3 rounded-xl bg-slate-50 ring-1 ring-slate-200">
                                    <div class="text-sm font-semibold text-slate-900">Clear rates</div>
                                    <div class="text-xs text-slate-600 mt-1">No phone-tag for pricing.</div>
                                </div>
                                <div class="p-3 rounded-xl bg-slate-50 ring-1 ring-slate-200">
                                    <div class="text-sm font-semibold text-slate-900">Your choice</div>
                                    <div class="text-xs text-slate-600 mt-1">Independent or agency care.</div>
                                </div>
                            </div>
                        </x-slot:footer>
                    </x-card>
                </div>

            </div>
        </div>
    </section>

    {{-- HOW IT WORKS --}}
    <section id="how" class="py-14">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between gap-6 mb-7">
                <div>
                    <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">How it works</h2>
                    <p class="text-slate-600 mt-1">Simple steps for families and caregivers.</p>
                </div>
                <x-badge color="slate" text="Raleigh-first rollout" round light />
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="magnifying-glass" class="w-5 h-5 text-indigo-700" />
                            <div class="font-bold">Search</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">Browse caregivers and agencies by service, location, and availability.</p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="adjustments-horizontal" class="w-5 h-5 text-emerald-700" />
                            <div class="font-bold">Compare</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">See clear rates, experience, and what each caregiver offers.</p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="chat-bubble-left-right" class="w-5 h-5 text-blue-700" />
                            <div class="font-bold">Connect</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">Message securely and move forward when you’re ready.</p>
                </x-card>
            </div>
        </div>
    </section>

    {{-- SERVICES --}}
    <section id="services" class="py-14 bg-white border-y border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-7">
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Care services available</h2>
                <p class="text-slate-600 mt-1">Non-medical home care services families commonly need.</p>
            </div>

            @php
                $services = [
                    ['icon' => 'heart', 'title' => 'Companion care', 'desc' => 'Social support, errands, and daily companionship.'],
                    ['icon' => 'hand-raised', 'title' => 'Personal care assistance', 'desc' => 'Help with bathing, dressing, and mobility support.'],
                    ['icon' => 'home', 'title' => 'Light housekeeping', 'desc' => 'Tidying, laundry, and keeping the home safe.'],
                    ['icon' => 'truck', 'title' => 'Transportation', 'desc' => 'Rides to appointments, shopping, and outings.'],
                    ['icon' => 'clock', 'title' => 'Respite care', 'desc' => 'Short-term relief for family caregivers.'],
                    ['icon' => 'sparkles', 'title' => 'Post-hospital support', 'desc' => 'Non-medical help during recovery at home.'],
                ];
            @endphp

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($services as $s)
                    <x-card class="shadow-sm ring-1 ring-black/5 hover:shadow-md transition">
                        <x-slot:header>
                            <div class="flex items-center gap-2">
                                <x-icon :name="$s['icon']" class="w-5 h-5 text-indigo-700" />
                                <div class="font-bold">{{ $s['title'] }}</div>
                            </div>
                        </x-slot:header>
                        <p class="text-sm text-slate-600">{{ $s['desc'] }}</p>
                    </x-card>
                @endforeach
            </div>

            <div class="mt-8 text-xs text-slate-500">
                Note: medical tasks require appropriate licensure and are not represented as services here.
            </div>
        </div>
    </section>

    {{-- TRUST --}}
    <section id="trust" class="py-14">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-7">
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Trust & safety</h2>
                <p class="text-slate-600 mt-1">Built to help families and caregivers connect responsibly.</p>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="user-circle" class="w-5 h-5 text-emerald-700" />
                            <div class="font-bold">Verification tools</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">Profile completion, identity checks, and optional background checks.</p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="lock-closed" class="w-5 h-5 text-indigo-700" />
                            <div class="font-bold">Secure messaging</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">Keep communication inside the platform with privacy controls.</p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center gap-2">
                            <x-icon name="star" class="w-5 h-5 text-blue-700" />
                            <div class="font-bold">Reviews & accountability</div>
                        </div>
                    </x-slot:header>
                    <p class="text-sm text-slate-600">Build trust over time through clear expectations and feedback.</p>
                </x-card>
            </div>

            <div class="mt-8 grid lg:grid-cols-3 gap-6">
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold text-slate-900">Families</div></x-slot:header>
                    <ul class="space-y-3 text-sm text-slate-600">
                        <li class="flex gap-2"><span class="text-emerald-600">✓</span> Browse verified profiles</li>
                        <li class="flex gap-2"><span class="text-emerald-600">✓</span> Compare rates and availability</li>
                        <li class="flex gap-2"><span class="text-emerald-600">✓</span> Message securely</li>
                        <li class="flex gap-2"><span class="text-emerald-600">✓</span> Keep everything organized</li>
                    </ul>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold text-slate-900">Caregivers</div></x-slot:header>
                    <ul class="space-y-3 text-sm text-slate-600">
                        <li class="flex gap-2"><span class="text-emerald-600">✓</span> Get discovered locally</li>
                        <li class="flex gap-2"><span class="text-emerald-600">✓</span> Set your rate + schedule</li>
                        <li class="flex gap-2"><span class="text-emerald-600">✓</span> Manage requests in one place</li>
                        <li class="flex gap-2"><span class="text-emerald-600">✓</span> Build trust with reviews</li>
                    </ul>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold text-slate-900">Agencies</div></x-slot:header>
                    <ul class="space-y-3 text-sm text-slate-600">
                        <li class="flex gap-2"><span class="text-emerald-600">✓</span> Showcase services & coverage</li>
                        <li class="flex gap-2"><span class="text-emerald-600">✓</span> Capture more inbound demand</li>
                        <li class="flex gap-2"><span class="text-emerald-600">✓</span> Centralize leads + follow-up</li>
                        <li class="flex gap-2"><span class="text-emerald-600">✓</span> Transparent service pricing</li>
                    </ul>
                </x-card>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="py-14 bg-white border-t border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-7">
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">FAQ</h2>
                <p class="text-slate-600 mt-1">Quick answers for families and caregivers.</p>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Is this only Raleigh?</div></x-slot:header>
                    <p class="text-sm text-slate-600">
                        We’re launching Raleigh + Wake County first to build density and trust.
                        Expansion comes next once matching quality is strong.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Can independent caregivers join?</div></x-slot:header>
                    <p class="text-sm text-slate-600">
                        Yes. Independent caregivers can create a profile and connect with families,
                        and agencies can list services and coverage too.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">How do background checks work?</div></x-slot:header>
                    <p class="text-sm text-slate-600">
                        Background checks can be offered as an optional verification step.
                        Families can filter based on verification level.
                    </p>
                </x-card>

                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header><div class="font-bold">Do you provide medical care?</div></x-slot:header>
                    <p class="text-sm text-slate-600">
                        We focus on non-medical home care services. Medical tasks require appropriate licensure and supervision.
                    </p>
                </x-card>
            </div>

            <div class="mt-12 rounded-2xl bg-gradient-to-r from-indigo-700 via-blue-700 to-emerald-600 text-white p-6 sm:p-8 shadow-[0_20px_60px_-35px_rgba(2,6,23,0.6)]">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
                    <div>
                        <div class="text-xl font-extrabold">Launching soon in Raleigh</div>
                        <div class="text-sm text-white/85 mt-1">
                            Join early access and get notified when we open.
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <a href="#how">
                            <x-button color="white" light>How it works</x-button>
                        </a>
                        <x-button color="white">Contact</x-button>
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
                    <div>Raleigh, NC home care marketplace</div>
                </div>

                <div class="text-xs text-slate-500 max-w-xl">
                    Safety note: caregivers operate independently or through licensed agencies. This platform provides matching and communication tools.
                </div>
            </div>
        </div>
    </footer>

    {{-- Sticky mobile CTA --}}
    <div class="md:hidden fixed bottom-0 inset-x-0 z-50 bg-white/95 backdrop-blur border-t border-slate-200 p-3">
        <div class="max-w-md mx-auto flex gap-2">
            <x-button class="w-1/2" color="indigo" icon="magnifying-glass" position="left">
                Need care
            </x-button>
            <x-button class="w-1/2" color="emerald" outline icon="user-plus" position="left">
                Provide care
            </x-button>
        </div>
    </div>
    <div class="md:hidden h-20"></div>
</div>