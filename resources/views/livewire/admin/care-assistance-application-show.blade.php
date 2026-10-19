<div class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
    @php
        $badgeClasses = match ($application->status) {
            'new' => 'bg-emerald-100 text-emerald-900',
            'under_review' => 'bg-sky-100 text-sky-900',
            'needs_information' => 'bg-amber-100 text-amber-900',
            'approved' => 'bg-teal-100 text-teal-900',
            'declined' => 'bg-rose-50 text-rose-800',
            default => 'bg-slate-100 text-slate-700',
        };
    @endphp
    <div class="mx-auto max-w-6xl space-y-6">
        <a href="{{ route('admin.care-assistance.index') }}" wire:navigate class="inline-flex min-h-10 items-center gap-2 text-sm font-semibold text-slate-600 hover:text-emerald-800"><span aria-hidden="true">&larr;</span> All care assistance applications</a>

        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Care assistance · #{{ $application->id }}</p>
                <h1 class="mt-2 break-words text-3xl font-bold tracking-tight text-slate-950">{{ $application->name }}</h1>
                <p class="mt-2 text-sm text-slate-500">Submitted {{ $application->created_at?->format('M j, Y \a\t g:i A T') }} · {{ $application->created_at?->diffForHumans() }}</p>
            </div>
            <span class="inline-flex w-fit items-center rounded-full px-4 py-2 text-sm font-semibold {{ $badgeClasses }}">{{ $statusLabels[$application->status] ?? $application->status }}</span>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm leading-6 text-emerald-900">{{ session('status') }}</div>
        @endif

        @error('review')
            <div role="alert" class="rounded-2xl border border-amber-300 bg-amber-50 p-5 text-sm leading-6 text-amber-950">
                <p class="font-semibold">A newer review is available</p>
                <p class="mt-1">{{ $message }}</p>
                <button type="button" wire:click="reloadApplication" wire:confirm="Load the latest saved version? Any unsaved notes currently in the editor will be replaced." class="mt-3 min-h-10 rounded-xl border border-amber-300 bg-white px-4 font-semibold hover:bg-amber-100">Load latest version</button>
            </div>
        @enderror
        @error('status') <p role="alert" class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800">{{ $message }}</p> @enderror

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="space-y-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="contact-heading">
                    <h2 id="contact-heading" class="text-lg font-bold text-slate-950">About the applicant</h2>
                    <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Email address</dt>
                            <dd class="mt-2 break-all text-sm font-medium text-slate-800"><a href="mailto:{{ $application->email }}" class="underline decoration-slate-300 underline-offset-4 hover:text-emerald-800">{{ $application->email }}</a></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Phone number</dt>
                            <dd class="mt-2 text-sm font-medium text-slate-800"><a href="tel:{{ preg_replace('/[^+0-9]/', '', $application->phone) }}" class="underline decoration-slate-300 underline-offset-4 hover:text-emerald-800">{{ $application->phone }}</a></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Applying for</dt>
                            <dd class="mt-2 text-sm text-slate-800">{{ config('care_assistance.relationship_options', [])[$application->relationship] ?? $application->relationship }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Care location</dt>
                            <dd class="mt-2 text-sm text-slate-800">{{ $application->city }} · {{ $application->zip }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="needs-heading">
                    <h2 id="needs-heading" class="text-lg font-bold text-slate-950">The support they need</h2>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @forelse ($application->care_needs ?? [] as $need)
                            <span class="rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-900">{{ config('care_assistance.care_need_options', [])[$need] ?? $need }}</span>
                        @empty
                            <p class="text-sm text-slate-500">No care preferences provided.</p>
                        @endforelse
                    </div>
                    <dl class="mt-5 grid gap-5 border-t border-slate-100 pt-5 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Estimated weekly care</dt>
                            <dd class="mt-2 text-sm font-medium text-slate-800">{{ config('care_assistance.weekly_hour_options', [])[$application->weekly_hours] ?? $application->weekly_hours }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-500">Preferred start</dt>
                            <dd class="mt-2 text-sm font-medium text-slate-800">{{ config('care_assistance.start_timeframe_options', [])[$application->start_timeframe] ?? $application->start_timeframe }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-2xl border border-[#DED6CA] bg-[#FFFDFA] p-6 shadow-sm" aria-labelledby="story-heading">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-800">In their own words</p>
                    <h2 id="story-heading" class="mt-2 text-lg font-bold text-slate-950">What makes paying for care difficult</h2>
                    <div class="mt-5 whitespace-pre-wrap break-words border-l-2 border-emerald-200 pl-5 text-sm leading-7 text-slate-700">{{ $application->hardship_details }}</div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="notes-heading">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="notes-heading" class="text-lg font-bold text-slate-950">Private review notes</h2>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">Administrators only</span>
                    </div>
                    <p id="notes-help" class="mt-2 text-sm leading-6 text-slate-500">Record review context, follow-up details, and the reason for your decision. These notes are never shown to the applicant.</p>
                    <form wire:submit="saveNotes" class="mt-4">
                        <label for="assistance-internal-notes" class="sr-only">Private review notes</label>
                        <textarea id="assistance-internal-notes" wire:model="internalNotes" rows="7" maxlength="5000" aria-describedby="notes-help notes-limit{{ $errors->has('internalNotes') ? ' notes-error' : '' }}" @error('internalNotes') aria-invalid="true" @enderror placeholder="Add context for the next person reviewing this application…" class="w-full rounded-xl border-slate-300 text-sm leading-6 focus:border-emerald-600 focus:ring-emerald-600"></textarea>
                        @error('internalNotes') <p id="notes-error" role="alert" class="mt-2 text-sm text-rose-700">{{ $message }}</p> @enderror
                        <div class="mt-3 flex items-center justify-between gap-4">
                            <p id="notes-limit" class="text-xs text-slate-500">Up to 5,000 characters.</p>
                            <button type="submit" wire:loading.attr="disabled" class="min-h-11 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-wait disabled:opacity-50">
                                <span wire:loading.remove wire:target="saveNotes">Save notes</span>
                                <span wire:loading wire:target="saveNotes">Saving…</span>
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <aside class="space-y-5 lg:sticky lg:top-6">
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="decision-heading">
                    <div class="bg-[#0F3D3E] p-5 text-white">
                        <p class="text-xs font-semibold uppercase tracking-wider text-emerald-200">Care assistance program</p>
                        <p class="mt-2 text-3xl font-bold">$23 <span class="text-sm font-normal text-white/80">per hour</span></p>
                        <p class="mt-2 text-xs leading-5 text-white/75">For approved applicants. Review goal: about 5 business days.</p>
                    </div>
                    <div class="p-5">
                        <h2 id="decision-heading" class="font-bold text-slate-950">Review &amp; decision</h2>
                        <p class="mt-2 text-xs leading-5 text-slate-500">Changing status also saves your current private notes.</p>
                        <div class="mt-4 space-y-2">
                            <button type="button" wire:click="updateStatus('under_review')" wire:loading.attr="disabled" @disabled($application->status === 'under_review') class="min-h-11 w-full rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-45">{{ in_array($application->status, ['approved', 'declined'], true) ? 'Reopen for review' : 'Mark under review' }}</button>
                            <button type="button" wire:click="updateStatus('needs_information')" wire:loading.attr="disabled" @disabled($application->status === 'needs_information') class="min-h-11 w-full rounded-xl border border-amber-200 bg-amber-50 px-4 text-sm font-semibold text-amber-900 hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-45">More information needed</button>
                        </div>
                        <div class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                            <button type="button" wire:click="updateStatus('approved')" wire:confirm="Approve this care assistance application? The decision and your notes will be saved. You will need to contact the applicant directly." wire:loading.attr="disabled" @disabled($application->status === 'approved') class="min-h-12 w-full rounded-xl bg-[#0F3D3E] px-4 text-sm font-semibold text-white shadow-sm hover:bg-emerald-900 disabled:cursor-not-allowed disabled:opacity-45">Approve application</button>
                            <button type="button" wire:click="updateStatus('declined')" wire:confirm="Decline this care assistance application? The decision and your notes will be saved. You will need to contact the applicant directly." wire:loading.attr="disabled" @disabled($application->status === 'declined') class="min-h-11 w-full rounded-xl border border-rose-200 px-4 text-sm font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-45">Decline application</button>
                        </div>
                        <p wire:loading wire:target="updateStatus" role="status" class="mt-3 text-xs text-slate-500">Saving review…</p>
                        <p class="mt-4 rounded-xl bg-slate-50 p-3 text-xs leading-5 text-slate-600"><strong class="font-semibold text-slate-800">Manual follow-up:</strong> These actions save an internal decision. No email or text is sent, and booking prices are not changed. Contact the applicant to explain the outcome and next steps.</p>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="record-heading">
                    <h2 id="record-heading" class="text-sm font-bold text-slate-950">Review record</h2>
                    <dl class="mt-4 space-y-4 text-xs">
                        <div>
                            <dt class="font-medium text-slate-500">Decision recorded by</dt>
                            <dd class="mt-1 font-semibold text-slate-800">{{ $application->reviewed_at ? ($application->reviewer?->name ?? 'Former administrator') : 'No decision recorded yet' }}</dd>
                        </div>
                        @if ($application->reviewed_at)
                            <div><dt class="font-medium text-slate-500">Decision date</dt><dd class="mt-1 text-slate-800">{{ $application->reviewed_at->format('M j, Y \a\t g:i A T') }}</dd></div>
                        @endif
                        <div><dt class="font-medium text-slate-500">Last updated</dt><dd class="mt-1 text-slate-800">{{ $application->updated_at?->format('M j, Y \a\t g:i A T') }}</dd></div>
                        <div><dt class="font-medium text-slate-500">Application consent received</dt><dd class="mt-1 text-slate-800">{{ $application->consented_at?->format('M j, Y \a\t g:i A T') ?? 'Not recorded' }}</dd></div>
                    </dl>
                </section>
                <p class="px-1 text-xs leading-5 text-slate-500">Personal circumstances are shared in confidence. Keep application information within the review team.</p>
            </aside>
        </div>
    </div>
</div>
