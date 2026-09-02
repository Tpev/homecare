<div class="min-h-screen bg-[#F7F3EC] px-4 py-5 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-[1600px] space-y-5">
        <header class="flex flex-col gap-4 rounded-[1.75rem] border border-[#D9CEC0] bg-[#23483F] px-5 py-5 text-white shadow-lg sm:px-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-white/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-[#F3D4C9]">Family acquisition</span>
                    <span class="text-xs font-semibold text-white/70">{{ now()->format('l, F j') }}</span>
                </div>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-white">Family calling console</h1>
                <p class="mt-1 text-sm text-white/70">Everything the family shared, every previous attempt, and the next best action in one place.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if(auth()->user()?->isAdministrator())
                    <a href="{{ route('admin.family-acquisition.leads') }}" wire:navigate class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/20 px-4 py-2 text-sm font-bold text-white hover:bg-white/10">Open family CRM</a>
                @endif
                <button type="button" wire:click="claimNextLead" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#C96B55] px-5 py-2 text-sm font-bold text-white shadow-sm hover:bg-[#B85C49]">
                    Claim next family
                </button>
            </div>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-[#E3D6C5] bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Unassigned & due</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $availableCount }}</p>
            </div>
            <div class="rounded-2xl border border-[#E3D6C5] bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Your due calls</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $dueOwnedCount }}</p>
            </div>
            <div class="rounded-2xl border border-[#E3D6C5] bg-white p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Your calls today</p>
                <p class="mt-2 text-3xl font-bold text-slate-950">{{ $callStats['today'] }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-emerald-700">Connected today</p>
                <p class="mt-2 text-3xl font-bold text-emerald-900">{{ $callStats['connected'] }}</p>
            </div>
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-sky-700">Qualified today</p>
                <p class="mt-2 text-3xl font-bold text-sky-900">{{ $callStats['qualified'] }}</p>
            </div>
        </section>

        @if($activeLead)
            @php
                $form = data_get($activeLead->data, 'form_answers', []);
                $meta = data_get($activeLead->data, 'meta', []);
                $lastCall = $activeLead->activities->first(fn($activity) => $activity->type === \App\Models\LeadActivity::TYPE_CALL);
                $attemptNumber = min(7, ((int) $activeLead->unanswered_attempt_count) + 1);
            @endphp

            <section class="grid items-start gap-5 xl:grid-cols-[330px_minmax(460px,1fr)_390px]">
                <aside class="space-y-4 xl:sticky xl:top-24">
                    <article class="overflow-hidden rounded-[1.6rem] border border-[#D9CEC0] bg-white shadow-sm">
                        <div class="border-b border-[#E7DED2] bg-[#FFFBF4] p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#A55343]">Current family</p>
                                    <h2 class="mt-1 text-2xl font-bold text-[#173F35]">{{ $activeLead->name }}</h2>
                                </div>
                                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $activeLead->priority === 'urgent' ? 'bg-rose-100 text-rose-800' : ($activeLead->priority === 'high' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">
                                    {{ ucfirst($activeLead->priority) }}
                                </span>
                            </div>
                            <div class="mt-4 flex items-center justify-between rounded-xl bg-[#23483F] px-4 py-3 text-white">
                                <span class="text-sm font-semibold">Reach attempt {{ $attemptNumber }} of 7</span>
                                <span class="text-xs text-white/70">{{ $activeLead->call_attempt_count }} total call{{ $activeLead->call_attempt_count === 1 ? '' : 's' }}</span>
                            </div>
                        </div>

                        <dl class="divide-y divide-[#EEE6DC] px-5">
                            <div class="py-3">
                                <dt class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Phone</dt>
                                <dd class="mt-1 text-base font-bold text-slate-950">{{ $activeLead->phone }}</dd>
                                <dd class="mt-0.5 text-xs text-slate-500">{{ $activeLead->email ?: 'No email provided' }}</dd>
                            </div>
                            <div class="grid grid-cols-2 gap-3 py-3">
                                <div>
                                    <dt class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Location</dt>
                                    <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $activeLead->location ?: 'Unknown' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Lead age</dt>
                                    <dd class="mt-1 text-sm font-semibold {{ $activeLead->submitted_at?->diffInMinutes(now()) > 60 && !$activeLead->first_call_at ? 'text-rose-700' : 'text-slate-900' }}">
                                        {{ $activeLead->submitted_at?->diffForHumans(short: true) ?: $activeLead->created_at->diffForHumans(short: true) }}
                                    </dd>
                                </div>
                            </div>
                            <div class="py-3">
                                <dt class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Preferred call time</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ data_get($form, 'preferred_call_time', 'Not specified') }}</dd>
                            </div>
                            <div class="py-3">
                                <dt class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Source</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $activeLead->source === 'meta_lead_ads' ? ucfirst((string) data_get($meta, 'platform', 'Meta')).' lead form' : 'Manual CRM entry' }}</dd>
                                <dd class="mt-1 text-xs leading-5 text-slate-500">{{ data_get($meta, 'campaign_name', $activeLead->source_detail) }}</dd>
                            </div>
                        </dl>
                    </article>

                    @if($lastCall)
                        <article class="rounded-[1.4rem] border border-amber-200 bg-amber-50 p-4 shadow-sm">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-amber-800">Last attempt</p>
                                <p class="text-xs text-amber-800/70">{{ $lastCall->occurred_at?->format('M j, g:i A') }}</p>
                            </div>
                            <p class="mt-2 text-sm font-bold text-amber-950">{{ data_get($lastCall->metadata, 'family_outcome_label', $lastCall->summary) }}</p>
                            <p class="mt-2 text-sm leading-6 text-amber-950/80">{{ $lastCall->body ?: 'No additional note was recorded.' }}</p>
                            <p class="mt-2 text-xs font-semibold text-amber-800">{{ $lastCall->actor?->name ?: 'System' }}</p>
                        </article>
                    @endif
                </aside>

                <main class="min-w-0 space-y-4">
                    <article class="rounded-[1.6rem] border border-[#D9CEC0] bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-700">Meta form answers</p>
                                <h2 class="mt-1 text-2xl font-bold text-slate-950">What the family told us</h2>
                            </div>
                            @if($activeLead->submitted_at)
                                <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">Submitted {{ $activeLead->submitted_at->format('M j, g:i A') }}</span>
                            @endif
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            @foreach([
                                'Who needs care?' => data_get($form, 'care_for', 'Not answered'),
                                'Relationship' => data_get($form, 'relationship', 'Not answered'),
                                'How soon?' => data_get($form, 'urgency', 'Not answered'),
                                'Likely schedule' => data_get($form, 'schedule', 'Not answered'),
                                'Funding' => data_get($form, 'funding', 'Not answered'),
                            ] as $label => $value)
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">{{ $label }}</p>
                                    <p class="mt-1 text-sm font-bold text-slate-950">{{ $value }}</p>
                                </div>
                            @endforeach
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:col-span-2">
                                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Care needs</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @forelse((array) data_get($form, 'care_needs', []) as $need)
                                        <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800">{{ $need }}</span>
                                    @empty
                                        <span class="text-sm text-slate-500">Not answered</span>
                                    @endforelse
                                </div>
                            </div>
                            <div class="rounded-2xl border border-[#E8C8BE] bg-[#FFF4EF] p-4 sm:col-span-2">
                                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#A55343]">In their own words</p>
                                <p class="mt-2 text-sm leading-6 text-slate-800">“{{ data_get($form, 'additional_details', 'No additional details supplied.') }}”</p>
                            </div>
                        </div>
                    </article>

                    <article x-data="{ section: 'opening' }" class="rounded-[1.6rem] border border-[#D9CEC0] bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-700">Family conversation guide</p>
                                <h2 class="mt-1 text-2xl font-bold text-slate-950">A warm, useful call</h2>
                            </div>
                            <div class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 text-xs font-bold">
                                @foreach(['opening' => 'Opening', 'discover' => 'Discover', 'explain' => 'Explain', 'close' => 'Next step'] as $key => $label)
                                    <button type="button" @click="section = '{{ $key }}'" :class="section === '{{ $key }}' ? 'bg-white text-[#23483F] shadow-sm' : 'text-slate-500'" class="rounded-lg px-3 py-2">{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-5 rounded-2xl border border-emerald-100 bg-emerald-50/70 p-5 text-sm leading-7 text-slate-800">
                            <div x-show="section === 'opening'">
                                <p>Hi, may I speak with <strong>{{ $activeLead->name }}</strong>?</p>
                                <p class="mt-3">Hi {{ str($activeLead->name)->before(' ') }}, this is <strong>{{ auth()->user()?->name }}</strong> from LoLo Care. You recently asked us to contact you about support for <strong>{{ data_get($form, 'care_for', 'a family member') }}</strong>. Is now still a good time for a quick conversation?</p>
                                <p class="mt-4 rounded-xl bg-white px-4 py-3 font-bold text-[#23483F]">Pause and let them set the pace.</p>
                            </div>
                            <div x-cloak x-show="section === 'discover'" class="space-y-3">
                                <p class="font-bold text-slate-950">Use their form answers; don’t make them repeat everything.</p>
                                <p>• I saw you mentioned {{ implode(', ', (array) data_get($form, 'care_needs', ['some help at home'])) }}. What feels most important right now?</p>
                                <p>• What is a normal day like for {{ data_get($form, 'care_for', 'your family member') }}?</p>
                                <p>• Is there anything affecting safety, mobility, memory, or being alone?</p>
                                <p>• If we found the right person, when would you ideally want help to begin?</p>
                                <p>• Who else should be involved in deciding on care?</p>
                            </div>
                            <div x-cloak x-show="section === 'explain'" class="space-y-3">
                                <p>LoLo helps families arrange flexible, non-medical support at home—things like companionship, meals, rides, errands, respite, light household help, and reliable check-ins.</p>
                                <p>We start by understanding the person and the routine that would make life easier. Then we help find a caregiver who fits those needs and the family’s schedule.</p>
                                <p class="rounded-xl bg-white px-4 py-3 font-bold text-[#23483F]">Be specific about what LoLo can help with. Never imply clinical care.</p>
                            </div>
                            <div x-cloak x-show="section === 'close'" class="space-y-3">
                                <p class="font-bold text-slate-950">If it sounds like a fit:</p>
                                <p>The best next step is a care conversation where we can confirm the routine, timing, and what a good caregiver match looks like. Would you like me to arrange that now?</p>
                                <p class="font-bold text-slate-950">If they need time:</p>
                                <p>Of course. When would be genuinely useful for us to check back in?</p>
                                <p class="font-bold text-slate-950">Before ending:</p>
                                <p>Confirm the next step, date, time, and best number. Then record it in the outcome panel.</p>
                            </div>
                        </div>

                        <details class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <summary class="cursor-pointer text-sm font-bold text-slate-900">Voicemail and common questions</summary>
                            <div class="mt-4 space-y-4 text-sm leading-6 text-slate-700">
                                <div><strong>Voicemail:</strong> Hi {{ str($activeLead->name)->before(' ') }}, this is {{ auth()->user()?->name }} from LoLo Care, following up on your request for help at home. You can call us back, and I’ll also try you again on the next business day.</div>
                                <div><strong>“How much does it cost?”</strong> Pricing depends on the support and schedule. Let me understand what would help first so I can give you an accurate next step.</div>
                                <div><strong>“Is this home health?”</strong> LoLo is for flexible non-medical help at home. We do not replace nursing or clinical home health.</div>
                            </div>
                        </details>
                    </article>

                    <article class="rounded-[1.6rem] border border-[#D9CEC0] bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Complete history</p>
                                <h2 class="mt-1 text-xl font-bold text-slate-950">Lead timeline</h2>
                            </div>
                            <span class="text-xs text-slate-500">{{ $activeLead->activities->count() }} recent events</span>
                        </div>
                        <div class="mt-5 space-y-0">
                            @foreach($activeLead->activities as $activity)
                                <div class="relative grid grid-cols-[24px_minmax(0,1fr)] gap-3 pb-5 last:pb-0">
                                    <div class="relative flex justify-center">
                                        <span class="relative z-10 mt-1 h-3 w-3 rounded-full {{ $activity->type === 'call' ? 'bg-[#C96B55]' : ($activity->type === 'conversion' ? 'bg-emerald-600' : 'bg-slate-400') }}"></span>
                                        @if(!$loop->last)<span class="absolute bottom-0 top-3 w-px bg-slate-200"></span>@endif
                                    </div>
                                    <div>
                                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                            <p class="text-sm font-bold text-slate-900">{{ $activity->summary }}</p>
                                            <p class="text-xs text-slate-500">{{ $activity->occurred_at?->format('M j, Y · g:i A') }}</p>
                                        </div>
                                        @if($activity->body)<p class="mt-1 text-sm leading-6 text-slate-600">{{ $activity->body }}</p>@endif
                                        <p class="mt-1 text-xs font-semibold text-slate-400">{{ $activity->actor?->name ?: 'System / Meta' }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </article>
                </main>

                <aside class="space-y-4 xl:sticky xl:top-24">
                    <article class="rounded-[1.6rem] border border-[#D9CEC0] bg-white p-5 shadow-sm">
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-700">Call action</p>
                        <h2 class="mt-1 text-2xl font-bold text-slate-950">{{ $activeLead->phone }}</h2>
                        <p class="mt-1 text-sm text-slate-500">Pressing call starts the first-response clock.</p>

                        @if($telHref)
                            <a href="{{ $telHref }}" wire:click="startCall" class="mt-4 flex min-h-14 w-full items-center justify-center gap-2 rounded-2xl bg-emerald-700 px-5 py-3 text-base font-bold text-white shadow-sm hover:bg-emerald-800">
                                <span aria-hidden="true">☎</span> {{ $callStarted ? 'Call in progress' : 'Start phone call' }}
                            </a>
                        @endif

                        @if($callStarted)
                            <div class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-center text-xs font-bold text-emerald-800">Call start recorded. Choose the outcome below.</div>
                        @endif

                        <button type="button" wire:click="releaseLead" class="mt-2 flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50">Release back to queue</button>
                    </article>

                    <article class="rounded-[1.6rem] border border-[#D9CEC0] bg-white p-5 shadow-sm">
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#A55343]">After the call</p>
                        <h2 class="mt-1 text-2xl font-bold text-slate-950">Record the outcome</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">No answer and voicemail return in a different call window on the next business day. Connected conversations and requested callbacks reset the seven-attempt sequence.</p>

                        <label class="mt-5 block">
                            <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-600">Call note</span>
                            <textarea wire:model="note" rows="4" placeholder="What did the family say? What matters for the next call?" class="mt-2 w-full rounded-2xl border-slate-200 px-4 py-3 text-sm focus:border-emerald-600 focus:ring-emerald-100"></textarea>
                        </label>

                        <label class="mt-4 block">
                            <span class="text-xs font-bold uppercase tracking-[0.14em] text-slate-600">Callback / follow-up time</span>
                            <input type="datetime-local" wire:model="followUpAt" class="mt-2 min-h-12 w-full rounded-2xl border-slate-200 px-4 py-3 text-sm focus:border-emerald-600 focus:ring-emerald-100">
                            @error('followUpAt')<span class="mt-1 block text-xs font-semibold text-rose-600">{{ $message }}</span>@enderror
                        </label>

                        <div class="mt-5 grid gap-2 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                            @foreach($outcomeOptions as $value => $label)
                                @php
                                    $style = match($value) {
                                        'connected_qualified', 'assessment_booked' => 'border-emerald-200 bg-emerald-50 text-emerald-900 hover:border-emerald-500',
                                        'do_not_contact', 'wrong_number', 'not_eligible' => 'border-rose-200 bg-rose-50 text-rose-900 hover:border-rose-500',
                                        'callback_requested', 'not_ready' => 'border-sky-200 bg-sky-50 text-sky-900 hover:border-sky-500',
                                        default => 'border-slate-200 bg-white text-slate-700 hover:border-[#C96B55] hover:bg-[#FFF7F2]',
                                    };
                                @endphp
                                <button type="button" wire:key="family-outcome-{{ $value }}" wire:click="logOutcome('{{ $value }}')" wire:loading.attr="disabled" wire:target="logOutcome" class="min-h-14 rounded-xl border px-3 py-3 text-left text-sm font-bold transition disabled:cursor-wait disabled:opacity-60 {{ $style }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                        @error('outcome')<span class="mt-2 block text-xs font-semibold text-rose-600">{{ $message }}</span>@enderror
                    </article>
                </aside>
            </section>
        @else
            <section class="rounded-[2rem] border border-[#D9CEC0] bg-white px-6 py-20 text-center shadow-sm">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 text-3xl text-emerald-800">✓</div>
                <p class="mt-5 text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-700">Ready for the next conversation</p>
                <h2 class="mt-2 text-3xl font-bold text-slate-950">Claim a family lead to begin.</h2>
                <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-slate-600">The queue prioritises urgent, overdue, and newly submitted leads. Follow-ups return automatically on their due date.</p>
                <button type="button" wire:click="claimNextLead" class="mt-6 inline-flex min-h-12 items-center justify-center rounded-2xl bg-[#23483F] px-6 py-3 text-base font-bold text-white hover:bg-[#173F35]">Claim next family</button>
            </section>
        @endif

        <section class="rounded-[1.6rem] border border-[#D9CEC0] bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-slate-950">Your recent family calls</h2>
                <span class="text-xs text-slate-500">Today and recent history</span>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                @forelse($recentCalls as $activity)
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="font-bold text-slate-950">{{ $activity->lead?->name ?: 'Unknown family' }}</p>
                        <p class="mt-1 text-xs font-bold text-emerald-700">{{ data_get($activity->metadata, 'family_outcome_label') }}</p>
                        <p class="mt-2 line-clamp-2 text-xs leading-5 text-slate-500">{{ $activity->body ?: 'No note recorded.' }}</p>
                    </article>
                @empty
                    <p class="col-span-full rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500">Saved family calls will appear here.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
