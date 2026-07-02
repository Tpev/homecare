<div class="min-h-screen bg-[#F8F4ED] px-4 py-6 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">SDR Workspace</p>
                <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-950">Provider calling queue</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">
                    Claim one practice at a time, call through Zoom Phone, then log the result so the referral CRM stays clean.
                </p>
            </div>

            <button
                type="button"
                wire:click="claimNextLead"
                class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-[#23483F] px-6 py-3 text-base font-bold text-white shadow-sm hover:bg-[#1B3D35]"
            >
                Claim next lead
            </button>
        </header>

        <section class="grid gap-3 sm:grid-cols-4">
            <div class="rounded-2xl border border-[#E3D6C5] bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Available</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $availableCount }}</p>
            </div>
            <div class="rounded-2xl border border-[#E3D6C5] bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Your calls today</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $callStats['today'] }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">Resources requested</p>
                <p class="mt-2 text-3xl font-bold text-emerald-800">{{ $callStats['resource_requested'] }}</p>
            </div>
            <div class="rounded-2xl border border-sky-100 bg-sky-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">Follow-up talks</p>
                <p class="mt-2 text-3xl font-bold text-sky-800">{{ $callStats['meeting_requested'] }}</p>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
            <main class="space-y-6">
                @if ($activeLead)
                    <section class="rounded-3xl border border-[#E3D6C5] bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Current lead</p>
                                <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-950">
                                    {{ $activeLead->company ?: $activeLead->name ?: 'Referral source' }}
                                </h2>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach (\App\Support\SdrOutreach::leadTags($activeLead) as $tag)
                                        <span class="rounded-full border border-[#E3D6C5] bg-[#FFFBF4] px-3 py-1 text-xs font-semibold text-[#23483F]">{{ $tag }}</span>
                                    @endforeach
                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">{{ $activeLead->stageLabel() }}</span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-2 sm:flex-row lg:flex-col">
                                @if ($zoomHref)
                                    <a
                                        href="{{ $zoomHref }}"
                                        data-phone-fallback="{{ $telHref }}"
                                        class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-emerald-700 px-5 py-3 text-base font-bold text-white shadow-sm hover:bg-emerald-800"
                                    >
                                        Open in Zoom Phone
                                    </a>
                                @endif
                                @if ($telHref)
                                    <a
                                        href="{{ $telHref }}"
                                        class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                    >
                                        Phone fallback
                                    </a>
                                @endif
                                <button
                                    type="button"
                                    wire:click="releaseLead"
                                    class="inline-flex min-h-12 items-center justify-center rounded-2xl border border-rose-200 bg-white px-5 py-3 text-sm font-semibold text-rose-700 hover:bg-rose-50"
                                >
                                    Release
                                </button>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Contact</p>
                                <p class="mt-2 font-bold text-slate-950">{{ $activeLead->name ?: 'Ask for resource coordinator' }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $activeLead->contact_role ?: 'Role unknown' }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Phone</p>
                                <p class="mt-2 font-bold text-slate-950">{{ $activeLead->phone ?: 'No phone' }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $activeLead->email ?: 'No email yet' }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Area</p>
                                <p class="mt-2 font-bold text-slate-950">{{ $activeLead->location ?: 'Unknown' }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $activeLead->zip ?: 'No ZIP' }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Next follow-up</p>
                                <p class="mt-2 font-bold {{ $activeLead->next_follow_up_at?->isPast() ? 'text-rose-700' : 'text-slate-950' }}">
                                    {{ $activeLead->next_follow_up_at?->format('M j, g:i A') ?: 'None set' }}
                                </p>
                                <p class="mt-1 text-sm text-slate-600">{{ $activeLead->last_contacted_at ? 'Last call '.$activeLead->last_contacted_at->diffForHumans() : 'Not called yet' }}</p>
                            </div>
                        </div>
                    </section>

                    <form wire:submit.prevent="logOutcome" class="rounded-3xl border border-[#E3D6C5] bg-white p-5 shadow-sm sm:p-6">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">After the call</p>
                            <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-950">What happened?</h2>
                            <p class="mt-2 text-sm text-slate-600">Pick one outcome. Notes are optional, but helpful if someone follows up later.</p>
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($outcomeOptions as $value => $label)
                                <label class="block">
                                    <input type="radio" wire:model.live="outcome" value="{{ $value }}" class="peer sr-only">
                                    <span class="flex min-h-16 cursor-pointer items-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-800">
                                        {{ $label }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('outcome') <span class="mt-2 block text-sm text-rose-600">{{ $message }}</span> @enderror

                        <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_260px]">
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Optional note</span>
                                <textarea
                                    wire:model.blur="note"
                                    rows="4"
                                    placeholder="Example: Steve asked us to send the resource to steve@example.com. Follow up next week."
                                    class="mt-1 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                                ></textarea>
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Follow-up date</span>
                                <input
                                    type="datetime-local"
                                    wire:model.change="followUpAt"
                                    class="mt-1 min-h-12 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                                >
                                <span class="mt-2 block text-xs leading-5 text-slate-500">Leave blank to use the default timing for this outcome.</span>
                            </label>
                        </div>

                        <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                            <button type="submit" class="inline-flex min-h-12 items-center justify-center rounded-2xl bg-[#23483F] px-6 py-3 text-base font-bold text-white shadow-sm hover:bg-[#1B3D35]">
                                Save outcome
                            </button>
                        </div>
                    </form>
                @else
                    <section class="rounded-3xl border border-[#E3D6C5] bg-white px-5 py-16 text-center shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Ready</p>
                        <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-950">Claim a lead to start calling.</h2>
                        <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-slate-600">
                            You will receive one unclaimed referral source from the imported call list. After the call, save the result and claim the next one.
                        </p>
                        <button
                            type="button"
                            wire:click="claimNextLead"
                            class="mt-6 inline-flex min-h-12 items-center justify-center rounded-2xl bg-[#23483F] px-6 py-3 text-base font-bold text-white shadow-sm hover:bg-[#1B3D35]"
                        >
                            Claim next lead
                        </button>
                    </section>
                @endif

                <section class="rounded-3xl border border-[#E3D6C5] bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-950">Your recent calls</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($recentCalls as $activity)
                            <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="font-semibold text-slate-950">{{ $activity->lead?->company ?: $activity->lead?->name ?: 'Unknown source' }}</p>
                                    <p class="text-xs text-slate-500">{{ $activity->occurred_at?->format('M j, g:i A') }}</p>
                                </div>
                                <p class="mt-1 text-sm font-semibold text-emerald-700">{{ data_get($activity->metadata, 'sdr_outcome_label') }}</p>
                                @if ($activity->body)
                                    <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $activity->body }}</p>
                                @endif
                            </article>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">
                                Your saved call outcomes will appear here.
                            </div>
                        @endforelse
                    </div>
                </section>
            </main>

            <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
                <section class="rounded-3xl border border-[#E3D6C5] bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Talk track</p>
                    <h2 class="mt-1 text-xl font-extrabold tracking-tight text-slate-950">Open simply</h2>
                    <div class="mt-4 space-y-4 text-sm leading-6 text-slate-700">
                        <p>
                            Hi, this is {{ auth()->user()?->name ?: 'Julie' }} calling from LoLo Care. We are a new home care service based in Raleigh.
                        </p>
                        <p>
                            We help older adults and families arrange non-medical support at home, like companionship, errands, rides, meal prep, respite, light household help, and check-ins.
                        </p>
                        <p class="font-semibold text-slate-950">
                            Who would be the right person to speak with about making this available as an option when families ask about help at home?
                        </p>
                    </div>
                </section>

                <section class="rounded-3xl border border-[#E3D6C5] bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-950">If they ask what this is</h2>
                    <ul class="mt-3 space-y-3 text-sm leading-6 text-slate-700">
                        <li>We are not asking for patient names or private information.</li>
                        <li>No referral fee, gift, commission, or obligation.</li>
                        <li>The goal is a simple one-page resource your team can keep on hand.</li>
                        <li>Families choose if they want to contact us. Your team does not need to manage the process.</li>
                    </ul>
                </section>

                <section class="rounded-3xl border border-[#E3D6C5] bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-950">Objection help</h2>
                    <div class="mt-3 space-y-4 text-sm leading-6 text-slate-700">
                        <div>
                            <p class="font-semibold text-slate-950">“We already have home health.”</p>
                            <p>That makes sense. We are different: this is practical, non-medical help for families who do not need clinical care.</p>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-950">“Send something.”</p>
                            <p>Absolutely. What is the best email for the one-page resource, and should I address it to you?</p>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-950">“Not interested.”</p>
                            <p>No problem. I will make a note so we do not keep reaching out. Thank you for your time.</p>
                        </div>
                    </div>
                </section>
            </aside>
        </section>
    </div>
</div>
