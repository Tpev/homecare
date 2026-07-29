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

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
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
            <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">Material drop-offs</p>
                <p class="mt-2 text-3xl font-bold text-amber-800">{{ $callStats['material_drop_agreed'] }}</p>
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

                    <section class="rounded-3xl border border-[#E3D6C5] bg-white p-5 shadow-sm sm:p-6">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">After the call</p>
                            <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-950">What happened?</h2>
                            <p class="mt-2 text-sm text-slate-600">Add any optional details first, then click an outcome to save it immediately to your dashboard and the CRM.</p>
                        </div>

                        <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_260px]">
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">Optional note</span>
                                <textarea
                                    wire:model="note"
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

                        <div class="mt-5">
                            <p class="text-sm font-bold text-slate-950">Click the outcome to save</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($outcomeOptions as $value => $label)
                                    <button
                                        type="button"
                                        wire:click="logOutcome('{{ $value }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="logOutcome"
                                        class="flex min-h-16 items-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left text-sm font-semibold text-slate-700 transition hover:border-emerald-600 hover:bg-emerald-50 hover:text-emerald-800 disabled:cursor-wait disabled:opacity-60"
                                    >
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        @error('outcome') <span class="mt-2 block text-sm text-rose-600">{{ $message }}</span> @enderror
                    </section>
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

            <aside class="space-y-4 xl:sticky xl:top-24 xl:max-h-[calc(100vh-7rem)] xl:self-start xl:overflow-y-auto xl:pr-1">
                <section class="rounded-3xl border border-[#E3D6C5] bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Goal</p>
                    <p class="mt-3 text-sm font-semibold leading-6 text-slate-950">
                        Validate that the office sees seniors/families who need support, identify the right contact, and secure permission for email or local drop-off.
                    </p>
                </section>

                <section class="rounded-3xl border border-[#E3D6C5] bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">SDR script</p>
                    <h2 class="mt-1 text-xl font-extrabold tracking-tight text-slate-950">Opening</h2>
                    <div class="mt-4 space-y-4 text-sm leading-6 text-slate-700">
                        <p>
                            Hi, this is {{ auth()->user()?->name ?: '[Name]' }} calling from Lolo Care. We're a local Raleigh-area service that helps older adults and families find helpful senior resources and flexible non-medical support at home.
                        </p>
                        <p>
                            We're reaching out to local practices and care teams because many families ask about help for aging parents, transportation, errands, companionship, respite, or support after a health change.
                        </p>
                        <p class="font-semibold text-slate-950">
                            Is that something your team sees with patients or families?
                        </p>
                        <p class="rounded-2xl bg-amber-50 px-4 py-3 text-center font-bold uppercase tracking-[0.14em] text-amber-800">Pause.</p>
                    </div>
                </section>

                <section class="rounded-3xl border border-[#E3D6C5] bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-950">If yes</h2>
                    <div class="mt-3 space-y-4 text-sm leading-6 text-slate-700">
                        <p>
                            That makes sense. We have helpful resources for seniors and families in Raleigh, and we'd like to share them with the right person in your office so your team has them available when families ask.
                        </p>
                        <p class="font-semibold text-slate-950">Who would be the best person to speak with about that?</p>
                    </div>
                </section>

                <section class="rounded-3xl border border-[#E3D6C5] bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-950">If they give a name</h2>
                    <div class="mt-3 space-y-4 text-sm leading-6 text-slate-700">
                        <p>Thank you. What is the best email for them?</p>
                        <p>Would it also be okay if someone from our local team stopped by briefly with a one-page overview and senior resource information your team can keep on hand?</p>
                    </div>
                </section>

                <section class="rounded-3xl border border-[#E3D6C5] bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-950">If they ask what Lolo is</h2>
                    <div class="mt-3 space-y-4 text-sm leading-6 text-slate-700">
                        <p>
                            Lolo Care helps older adults and families arrange flexible, non-medical support at home. That can include rides, errands, companionship, respite, meal support, light household help, and check-ins.
                        </p>
                        <p>
                            We also share helpful resources for seniors and families in Raleigh, so provider teams have something simple to point families to when they need extra help at home.
                        </p>
                    </div>
                </section>

                <section class="rounded-3xl border border-[#E3D6C5] bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-950">If they ask whether you are looking for referrals</h2>
                    <div class="mt-3 space-y-4 text-sm leading-6 text-slate-700">
                        <p>
                            We're mainly trying to share a helpful local resource and identify the right person in the practice. We're not asking for patient information on this call.
                        </p>
                        <p>
                            If families ask your team about non-medical help at home, we'd like Lolo to be something your team can mention as an option.
                        </p>
                    </div>
                </section>

                <section class="rounded-3xl border border-[#E3D6C5] bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-950">If they already have home health</h2>
                    <div class="mt-3 space-y-4 text-sm leading-6 text-slate-700">
                        <p>
                            That makes sense. Lolo is different from home health. Home health is clinical care, while Lolo helps with flexible, non-medical support like rides, errands, companionship, respite, meals, household help, and check-ins.
                        </p>
                        <p>
                            We also do not have hourly minimums, so families can book only the support they actually need. We're typically about 30% less expensive than traditional home care options, while paying caregivers up to 2x more than many standard caregiving roles. That helps us attract more consistent, dedicated caregivers and provide families with more reliable support at home.
                        </p>
                        <p class="font-semibold text-slate-950">
                            We're usually helpful when someone does not need clinical care but still needs extra day-to-day support.
                        </p>
                    </div>
                </section>
            </aside>
        </section>
    </div>
</div>
