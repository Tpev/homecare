<div class="min-h-screen bg-slate-50">
    {{-- Top bar --}}
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/80 backdrop-blur">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-2xl bg-gradient-to-br from-emerald-600 via-teal-500 to-blue-600 shadow-sm ring-1 ring-black/5"></div>
                <div class="leading-tight">
                    <div class="text-lg font-extrabold text-slate-900">HomeCare</div>
                    <div class="text-xs text-slate-500">Caregiver Dashboard (mock)</div>
                </div>
            </div>

            <div class="hidden md:flex items-center gap-2">
                @if($availability === 'available')
                    <x-badge color="emerald" text="Visible to families" round light />
                @else
                    <x-badge color="slate" text="Paused" round light />
                @endif
                <x-badge color="blue" text="Raleigh, NC" round light />
            </div>

            <div class="flex items-center gap-2">
                <x-button color="{{ $availability === 'available' ? 'slate' : 'emerald' }}"
                          light
                          sm
                          icon="{{ $availability === 'available' ? 'pause' : 'play' }}"
                          position="left"
                          wire:click="toggleAvailability">
                    {{ $availability === 'available' ? 'Pause' : 'Go live' }}
                </x-button>

                <x-button color="blue" sm icon="bell" position="left">
                    Alerts
                </x-button>

                <x-button color="slate" sm light icon="user-circle" position="left">
                    Profile
                </x-button>
            </div>
        </div>
    </header>

    {{-- Page --}}
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Headline --}}
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
            <div>
                <div class="text-sm text-slate-500">Good morning,</div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">
                    Here’s your week at a glance
                </h1>
                <p class="mt-2 text-slate-600 max-w-2xl">
                    This is a visual mockup of the caregiver experience: requests, schedule, earnings, messages, and profile reputation.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <x-button color="emerald" icon="plus" position="left">
                    Add availability
                </x-button>
                <x-button color="blue" outline icon="sparkles" position="left">
                    Boost profile
                </x-button>
                <x-button color="slate" light icon="document-text" position="left">
                    Documents
                </x-button>
            </div>
        </div>

        {{-- Stats row --}}
        <section class="mt-7 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-card class="shadow-sm ring-1 ring-black/5">
                <x-slot:header>
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-semibold text-slate-500">This week</div>
                        <x-icon name="calendar-days" class="w-5 h-5 text-emerald-600" />
                    </div>
                </x-slot:header>
                <div class="text-2xl font-extrabold text-slate-900">12 hrs</div>
                <div class="text-xs text-slate-500 mt-1">2 shifts • 1 pending request</div>
            </x-card>

            <x-card class="shadow-sm ring-1 ring-black/5">
                <x-slot:header>
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-semibold text-slate-500">Earnings (est.)</div>
                        <x-icon name="currency-dollar" class="w-5 h-5 text-blue-600" />
                    </div>
                </x-slot:header>
                <div class="text-2xl font-extrabold text-slate-900">$312</div>
                <div class="text-xs text-slate-500 mt-1">$26/hr avg • Raleigh area</div>
            </x-card>

            <x-card class="shadow-sm ring-1 ring-black/5">
                <x-slot:header>
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-semibold text-slate-500">Reputation</div>
                        <x-icon name="star" class="w-5 h-5 text-amber-500" />
                    </div>
                </x-slot:header>
                <div class="flex items-end gap-2">
                    <div class="text-2xl font-extrabold text-slate-900">4.9</div>
                    <div class="text-sm text-slate-500 mb-1">/ 5</div>
                </div>
                <div class="text-xs text-slate-500 mt-1">37 reviews • top 10%</div>
            </x-card>

            <x-card class="shadow-sm ring-1 ring-black/5">
                <x-slot:header>
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-semibold text-slate-500">Profile strength</div>
                        <x-icon name="shield-check" class="w-5 h-5 text-emerald-600" />
                    </div>
                </x-slot:header>
                <div class="text-2xl font-extrabold text-slate-900">82%</div>
                <div class="text-xs text-slate-500 mt-1">Add ID + references to reach 100%</div>
            </x-card>
        </section>

        {{-- Main grid --}}
        <section class="mt-6 grid lg:grid-cols-12 gap-6">
            {{-- Left column --}}
            <div class="lg:col-span-8 space-y-6">
                {{-- Requests --}}
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <div class="text-lg font-extrabold text-slate-900">New care requests</div>
                                <div class="text-sm text-slate-500">Families requesting care near you</div>
                            </div>
                            <x-button color="blue" light sm icon="adjustments-horizontal" position="left">
                                Filters
                            </x-button>
                        </div>
                    </x-slot:header>

                    <div class="space-y-3">
                        {{-- Request card --}}
                        <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div class="h-10 w-10 rounded-2xl bg-emerald-100 flex items-center justify-center">
                                    <x-icon name="heart" class="w-5 h-5 text-emerald-700" />
                                </div>
                                <div>
                                    <div class="font-bold text-slate-900">Companion care • 3 days/week</div>
                                    <div class="text-sm text-slate-600">Cary • 6.2 miles • Starts: ASAP</div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <x-badge color="slate" text="12 hrs/week" round light />
                                        <x-badge color="emerald" text="Great fit" round light />
                                        <x-badge color="blue" text="$24–$28/hr" round light />
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <x-button color="slate" light sm icon="chat-bubble-left-right" position="left">
                                    Message
                                </x-button>
                                <x-button color="emerald" sm icon="check" position="left">
                                    Accept
                                </x-button>
                            </div>
                        </div>

                        <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div class="h-10 w-10 rounded-2xl bg-blue-100 flex items-center justify-center">
                                    <x-icon name="hand-raised" class="w-5 h-5 text-blue-700" />
                                </div>
                                <div>
                                    <div class="font-bold text-slate-900">Personal care • Morning routine</div>
                                    <div class="text-sm text-slate-600">Raleigh • 2.1 miles • Starts: This week</div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <x-badge color="slate" text="Mon–Fri" round light />
                                        <x-badge color="amber" text="Needs experience" round light />
                                        <x-badge color="blue" text="$28–$32/hr" round light />
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <x-button color="slate" light sm icon="chat-bubble-left-right" position="left">
                                    Message
                                </x-button>
                                <x-button color="blue" sm icon="eye" position="left">
                                    View
                                </x-button>
                            </div>
                        </div>
                    </div>

                    <x-slot:footer>
                        <div class="flex items-center justify-between gap-4">
                            <div class="text-xs text-slate-500">
                                Tip: respond fast to rank higher in search.
                            </div>
                            <x-button color="blue" light sm icon="magnifying-glass" position="left">
                                Browse more
                            </x-button>
                        </div>
                    </x-slot:footer>
                </x-card>

                {{-- Schedule --}}
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <div class="text-lg font-extrabold text-slate-900">Upcoming schedule</div>
                                <div class="text-sm text-slate-500">What’s next on your calendar</div>
                            </div>
                            <x-badge color="emerald" text="2 confirmed" round light />
                        </div>
                    </x-slot:header>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="rounded-2xl bg-slate-50 ring-1 ring-slate-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-bold text-slate-900">Tue • 9:00–12:00</div>
                                    <div class="text-sm text-slate-600">Companion care • Cary</div>
                                </div>
                                <x-badge color="emerald" text="Confirmed" round light />
                            </div>
                            <div class="mt-3 text-xs text-slate-500">
                                Address shared after acceptance • Notes included
                            </div>
                            <div class="mt-3 flex gap-2">
                                <x-button color="slate" light sm icon="map-pin" position="left">Route</x-button>
                                <x-button color="slate" light sm icon="chat-bubble-left-right" position="left">Chat</x-button>
                            </div>
                        </div>

                        <div class="rounded-2xl bg-slate-50 ring-1 ring-slate-200 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-bold text-slate-900">Thu • 8:00–11:00</div>
                                    <div class="text-sm text-slate-600">Personal care • Raleigh</div>
                                </div>
                                <x-badge color="emerald" text="Confirmed" round light />
                            </div>
                            <div class="mt-3 text-xs text-slate-500">
                                Care plan attached • Emergency contact on file
                            </div>
                            <div class="mt-3 flex gap-2">
                                <x-button color="slate" light sm icon="document-text" position="left">Care plan</x-button>
                                <x-button color="slate" light sm icon="chat-bubble-left-right" position="left">Chat</x-button>
                            </div>
                        </div>
                    </div>
                </x-card>
            </div>

            {{-- Right column --}}
            <div class="lg:col-span-4 space-y-6">
                {{-- Visibility / settings --}}
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-extrabold text-slate-900">Visibility settings</div>
                                <div class="text-sm text-slate-500">Control how families see you</div>
                            </div>
                            <x-icon name="cog-6-tooth" class="w-5 h-5 text-slate-400" />
                        </div>
                    </x-slot:header>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-slate-700">Status</div>
                                <div class="text-xs text-slate-500">Discoverable in search</div>
                            </div>
                            @if($availability === 'available')
                                <x-badge color="emerald" text="Live" round light />
                            @else
                                <x-badge color="slate" text="Paused" round light />
                            @endif
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <div class="text-xs font-semibold text-slate-600 mb-1">Radius</div>
                                <x-select.styled wire:model.live="radius" :options="[
                                    ['label' => '5 miles', 'value' => '5'],
                                    ['label' => '10 miles', 'value' => '10'],
                                    ['label' => '15 miles', 'value' => '15'],
                                    ['label' => '25 miles', 'value' => '25'],
                                ]" />
                            </div>

                            <div>
                                <div class="text-xs font-semibold text-slate-600 mb-1">Accepting new</div>
                                <div class="flex items-center justify-between rounded-xl bg-slate-50 ring-1 ring-slate-200 px-3 py-2">
                                    <div class="text-sm text-slate-700">{{ $acceptingNewClients ? 'Yes' : 'No' }}</div>
                                    <input type="checkbox" class="rounded border-slate-300"
                                           wire:model.live="acceptingNewClients">
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="text-xs font-semibold text-slate-600 mb-1">Service types</div>
                            <x-select.styled
                                wire:model.live="serviceTypes"
                                multiple
                                :options="[
                                    ['label' => 'Companion care', 'value' => 'companion'],
                                    ['label' => 'Personal care', 'value' => 'personal'],
                                    ['label' => 'Dementia support', 'value' => 'dementia'],
                                    ['label' => 'Respite', 'value' => 'respite'],
                                ]"
                            />
                        </div>

                        <x-button color="{{ $availability === 'available' ? 'slate' : 'emerald' }}"
                                  light
                                  icon="{{ $availability === 'available' ? 'pause' : 'play' }}"
                                  position="left"
                                  class="w-full"
                                  wire:click="toggleAvailability">
                            {{ $availability === 'available' ? 'Pause visibility' : 'Go live (visible to families)' }}
                        </x-button>
                    </div>
                </x-card>

                {{-- Messages --}}
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <div class="text-lg font-extrabold text-slate-900">Messages</div>
                                <div class="text-sm text-slate-500">Secure chat inside HomeCare</div>
                            </div>
                            <x-badge color="blue" text="2 new" round light />
                        </div>
                    </x-slot:header>

                    <div class="space-y-3">
                        <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 p-3">
                            <div class="flex items-center justify-between">
                                <div class="font-semibold text-slate-800 text-sm">Family in Cary</div>
                                <div class="text-xs text-slate-500">5m</div>
                            </div>
                            <div class="text-sm text-slate-600 mt-1">“Are you available Tue/Thu mornings?”</div>
                        </div>

                        <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 p-3">
                            <div class="flex items-center justify-between">
                                <div class="font-semibold text-slate-800 text-sm">Agency coverage</div>
                                <div class="text-xs text-slate-500">1h</div>
                            </div>
                            <div class="text-sm text-slate-600 mt-1">“We have a weekend shift open in Apex.”</div>
                        </div>
                    </div>

                    <x-slot:footer>
                        <x-button color="blue" light class="w-full" icon="chat-bubble-left-right" position="left">
                            Open inbox
                        </x-button>
                    </x-slot:footer>
                </x-card>

                {{-- Reputation --}}
                <x-card class="shadow-sm ring-1 ring-black/5">
                    <x-slot:header>
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-extrabold text-slate-900">Reputation & growth</div>
                                <div class="text-sm text-slate-500">Earn more by building trust</div>
                            </div>
                            <x-icon name="sparkles" class="w-5 h-5 text-amber-500" />
                        </div>
                    </x-slot:header>

                    <div class="space-y-3">
                        <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 p-3 flex items-start gap-3">
                            <div class="h-9 w-9 rounded-xl bg-emerald-100 flex items-center justify-center">
                                <x-icon name="shield-check" class="w-5 h-5 text-emerald-700" />
                            </div>
                            <div class="grow">
                                <div class="text-sm font-semibold text-slate-800">Add verification</div>
                                <div class="text-xs text-slate-500 mt-1">ID + references increase match rate.</div>
                            </div>
                            <x-button color="emerald" light sm>Do it</x-button>
                        </div>

                        <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 p-3 flex items-start gap-3">
                            <div class="h-9 w-9 rounded-xl bg-blue-100 flex items-center justify-center">
                                <x-icon name="star" class="w-5 h-5 text-blue-700" />
                            </div>
                            <div class="grow">
                                <div class="text-sm font-semibold text-slate-800">Request reviews</div>
                                <div class="text-xs text-slate-500 mt-1">Reviews unlock premium placements.</div>
                            </div>
                            <x-button color="blue" light sm>Invite</x-button>
                        </div>
                    </div>
                </x-card>
            </div>
        </section>

        {{-- Bottom note --}}
        <div class="mt-10 text-xs text-slate-500">
            Mockup only: buttons/actions are visual. Next step is wiring these sections to real models (requests, shifts, messages, reviews).
        </div>
    </main>
</div>