<div class="hc-page space-y-6 py-8" @if($roster && in_array($roster->status, ['queued', 'generating', 'approved'])) wire:poll.8s="refreshRoster" @endif>
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div><p class="hc-care-eyebrow">Customer Success · Internal</p><h1 class="hc-care-title">Caregiver roster</h1><p class="hc-care-subtitle">Find the right people, review the evidence, then approve outreach.</p></div>
        <a href="{{ route('customer-care.index') }}" class="hc-secondary-button">Back to Customer Success</a>
    </header>
    @if($errors->any())<div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form wire:submit="generate" x-data x-cloak class="hc-surface space-y-4 p-5 sm:p-6">
        <div class="grid gap-4 md:grid-cols-2">
            <label class="block text-sm font-semibold">Find customer or request<input wire:model.live.debounce.300ms="search" type="search" maxlength="120" placeholder="Customer name, request title or ID" class="mt-2 block w-full rounded-xl border p-3 font-normal"></label>
            <label class="block text-sm font-semibold">Customer and request<select wire:model.live="requestId" required class="mt-2 block w-full rounded-xl border p-3 font-normal"><option value="">Choose a managed request</option>@foreach($requests as $request)<option value="{{ $request->id }}">{{ $request->family?->name }} · #{{ $request->id }} · {{ $request->title }}</option>@endforeach</select><span class="mt-1 block text-xs font-normal text-slate-500">Showing up to 50 matches. Search to find another request.</span></label>
        </div>
        <label class="block text-sm font-semibold">Context for this search<textarea wire:model="context" rows="3" maxlength="6000" placeholder="Deadline, flexible hours, customer feedback, previous conversations, or anything the care team should account for…" class="mt-2 block w-full rounded-xl border p-3 font-normal"></textarea></label>
        <div class="flex flex-wrap items-end justify-between gap-4"><label class="text-sm font-semibold">Timeline<select wire:model="urgency" class="mt-2 block rounded-xl border p-3 font-normal"><option value="normal">Normal</option><option value="soon">Needed this week</option><option value="urgent">Urgent / within 48 hours</option></select></label><button class="hc-primary-button" wire:loading.attr="disabled" wire:target="generate">Generate fresh roster</button></div>
        <p class="text-sm text-slate-600">Only active caregivers with verified identity and eligibility for new work are considered. The review reads conversations, notes and commitments and may take several minutes. Generation sends nothing. SMS stays optional, including urgent searches. Only admin and CS can access this workspace.</p>
    </form>
    @if($history->isNotEmpty())<nav aria-label="Recent rosters" class="flex flex-wrap items-center gap-3 text-sm"><span class="font-semibold">Recent drafts</span>@foreach($history as $past)<button type="button" wire:click="loadRoster({{ $past->id }})" class="hc-care-text-link">#{{ $past->id }} · {{ $past->created_at->format('M j, H:i') }} · {{ ucfirst($past->status) }}</button>@endforeach</nav>@endif
    @if($roster)
        <section class="hc-surface space-y-4 p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-semibold">{{ $roster->careRequest->family?->name }} · {{ $roster->careRequest->title }}</h2><a class="hc-care-text-link" href="{{ route('customer-care.show', $roster->care_request_id) }}">Open request #{{ $roster->care_request_id }}</a></div>
            @if(in_array($roster->status, ['queued', 'generating']))<p role="status">{{ $roster->status === 'queued' ? 'Waiting for the existing worker…' : 'Reviewing caregiver profiles, conversations and commitments…' }} You can leave this page and reopen the draft.</p>
                @if($roster->generation_data)<p class="text-sm text-slate-600">{{ count(data_get($roster->generation_data, 'assessments', [])) }} of {{ data_get($roster->coverage, 'candidates_reviewed', 0) }} candidates assessed. The final roster appears after every batch is reviewed.</p>@endif
            @elseif($roster->status === 'failed')<p role="alert" class="text-red-800">{{ $roster->error }} Nothing was sent.</p>
            @else
                <p class="whitespace-pre-line">{{ $roster->summary }}</p>
                @if(data_get($roster->coverage, 'eligibility'))<p class="text-sm text-slate-600">{{ data_get($roster->coverage, 'eligibility') }}</p>@endif
                <p class="text-sm text-slate-600">Evidence reviewed {{ $roster->generated_at?->format('M j, Y H:i') }} {{ config('app.timezone') }} · {{ data_get($roster->coverage, 'profiles_screened', 0) }} profiles screened · {{ data_get($roster->coverage, 'candidates_reviewed', 0) }} regional candidates reviewed.</p>
                <details class="text-sm"><summary class="cursor-pointer font-semibold">Search coverage and limits</summary><div class="mt-2 space-y-1"><p>{{ data_get($roster->coverage, 'geography') }}</p><p>{{ data_get($roster->coverage, 'conflicts') }}</p><p>Excluded: {{ data_get($roster->coverage, 'excluded.not_eligible', 0) }} ineligible, {{ data_get($roster->coverage, 'excluded.outside_search_area', 0) }} outside search area, {{ data_get($roster->coverage, 'excluded.opted_out', 0) }} opted out.</p><p>History includes up to {{ config('care_roster.history_limit') }} newest records per source. Longer text is marked when clipped. Saved schedules and self-reported qualifications require confirmation.</p></div></details>
                @if($roster->questions)<div class="rounded-xl bg-amber-50 p-4"><h3 class="font-semibold">Confirm with the customer</h3><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($roster->questions as $question)<li>{{ $question }}</li>@endforeach</ul></div>@endif
            @endif
        </section>
        @if($reviewToken && ! $roster->approved_at)
            <section class="hc-surface space-y-5 border-2 border-emerald-800 p-5 sm:p-6" aria-label="Review exact outreach">
                <h2 class="text-xl font-semibold">Review exact outreach</h2><p>Only the people and messages below will be sent. Invitation notifications use the existing LoLo templates. Internal reasoning stays private.</p>
                @foreach($roster->entries->where('selected', true) as $entry)<article class="space-y-3 rounded-xl border p-4"><h3 class="font-semibold">{{ $entry->caregiver->name }} · #{{ $entry->caregiver_user_id }}</h3>
                    @if($entry->action === 'invite')<div><p class="text-sm font-semibold">New invitation</p><p class="mt-1 whitespace-pre-line">{{ $entry->invitation_body }}</p></div>@else<p>Existing invitation stays in place; SMS follow-up only.</p>@endif
                    @if($entry->send_sms)<div><p class="text-sm font-semibold">SMS to {{ $entry->caregiver->phone }}</p><p class="mt-1 whitespace-pre-line">{{ $entry->sms_body }}</p></div>@else<p class="text-sm text-slate-600">No SMS selected.</p>@endif
                </article>@endforeach
                <div class="flex flex-wrap gap-3"><button type="button" class="hc-primary-button" wire:click="approve" wire:loading.attr="disabled">Approve and send to {{ $roster->entries->where('selected', true)->count() }} {{ \Illuminate\Support\Str::plural('caregiver', $roster->entries->where('selected', true)->count()) }}</button><button type="button" class="hc-secondary-button" wire:click="editPreview">Back to editing</button></div><p class="text-sm text-slate-600">This preview expires after 15 minutes. Sending does not hire or book anyone.</p>
            </section>
        @elseif(in_array($roster->status, ['ready', 'approved']))
            @if($roster->approved_at)<div role="status" class="rounded-xl bg-emerald-50 p-4">Approved {{ $roster->approved_at->format('M j, H:i') }}. Results below track each selected caregiver. New replies remain in the existing request and SMS inboxes.</div>@endif
            <div class="space-y-4">
                @forelse($roster->entries as $entry)
                    @php
                        $op = $entry->evidence['caregiver']['operational'];
                        $blocked = $entry->action === 'review_only' || $op['blocked_reason'] || $entry->assessment['avoid_contact'];
                        $invitation = $invitations->get($entry->caregiver_user_id);
                        $application = $applications->get($entry->caregiver_user_id);
                    @endphp
                    <article wire:key="roster-entry-{{ $entry->id }}" class="hc-surface space-y-4 p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="text-lg font-semibold">{{ $entry->rank }}. {{ $entry->caregiver->name }}</h3><p class="text-sm text-slate-600">Caregiver #{{ $entry->caregiver_user_id }} · {{ ucfirst($entry->assessment['fit']) }} fit · {{ $application ? 'Application: '.$application->status : ($invitation ? 'Invitation: '.$invitation->status : 'Not yet invited') }}</p></div>
                            @if(! $roster->approved_at && ! $blocked)<label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" wire:model="drafts.{{ $entry->id }}.selected" class="rounded">Select for outreach</label>@endif
                        </div>
                        <p>{{ $entry->assessment['why'] }}</p>
                        <div class="grid gap-4 md:grid-cols-2"><div><h4 class="text-sm font-semibold">Concerns</h4><ul class="mt-1 list-disc space-y-1 pl-5 text-sm">@forelse($entry->assessment['concerns'] as $item)<li>{{ $item }}</li>@empty<li>No additional concern identified; availability still needs confirmation.</li>@endforelse</ul></div><div><h4 class="text-sm font-semibold">Confirm before placement</h4><ul class="mt-1 list-disc space-y-1 pl-5 text-sm">@foreach($entry->assessment['confirm'] as $item)<li>{{ $item }}</li>@endforeach</ul></div></div>
                        @if($blocked)<p class="rounded-xl bg-amber-50 p-3 text-sm">{{ $op['blocked_reason'] ?: 'Review the existing response in the request. No new invitation will be sent from this roster.' }}</p>@endif
                        <details class="text-sm"><summary class="cursor-pointer font-semibold">Evidence and saved availability</summary><div class="mt-3 space-y-3">
                            <p>Approximate ZIP-centre distance: {{ $entry->evidence['caregiver']['distance_miles_estimate'] ?? 'unknown' }} miles. Outside commitments are not recorded here.</p>
                            @foreach(collect($entry->evidence['caregiver']['records'])->filter(fn ($record) => str_starts_with($record['ref'], 'caregiver_availabilities:')) as $availability)<p>{{ \App\Support\WeeklySchedule::DAY_LABELS[$availability['day_of_week']] ?? '' }} {{ $availability['start_time'] }}–{{ $availability['end_time'] }} · updated {{ $availability['updated_at'] }}</p>@endforeach
                            @foreach(collect(array_merge($entry->evidence['caregiver']['records'], $entry->evidence['customer']['records']))->unique('ref')->whereIn('ref', $entry->assessment['evidence_refs']) as $record)<div class="rounded-lg bg-slate-50 p-3"><p class="font-semibold">{{ $record['ref'] }}</p>@foreach($record as $key => $value)@if($key !== 'ref' && $value !== null && ! is_array($value))<p class="mt-1 break-words whitespace-pre-line"><span class="font-medium">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span> {{ $value }}</p>@endif@endforeach</div>@endforeach
                            @foreach(['caregiver', 'customer'] as $owner)@foreach($entry->evidence[$owner]['coverage'] as $source => $counts)@if($counts['included'] < $counts['total'])<p class="text-amber-800">{{ ucfirst($owner) }} {{ $source }}: {{ $counts['included'] }} of {{ $counts['total'] }} records included (newest first).</p>@endif@endforeach
                            @endforeach
                        </div></details>
                        @if(! $roster->approved_at && ! $blocked)
                            @if($entry->action === 'invite')<label class="block text-sm font-semibold">Invitation message<textarea wire:model="drafts.{{ $entry->id }}.invitation_body" rows="3" maxlength="1000" class="mt-2 block w-full rounded-xl border p-3 font-normal"></textarea></label>@else<p class="text-sm">Already invited. Select SMS for a follow-up; no duplicate invitation will be created.</p>@endif
                            @if($op['sms_allowed'])<label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" wire:model.live="drafts.{{ $entry->id }}.send_sms" class="rounded">Also send SMS{{ $entry->action === 'follow_up' ? ' follow-up' : '' }}</label>
                                @if(data_get($drafts, $entry->id.'.send_sms'))<label class="block text-sm font-semibold">SMS message<textarea wire:model="drafts.{{ $entry->id }}.sms_body" rows="3" maxlength="1600" class="mt-2 block w-full rounded-xl border p-3 font-normal"></textarea></label>@endif
                            @else<p class="text-sm text-amber-800">{{ $op['sms_note'] }}</p>@endif
                        @endif
                        @if($roster->approved_at)<p class="text-sm font-semibold">{{ $entry->selected ? ucfirst(str_replace('_', ' ', $entry->dispatch_status)) : 'Not selected — nothing sent' }}{{ $entry->smsMessage ? ' · SMS: '.$entry->smsMessage->status : '' }}</p>@if($entry->dispatch_note)<p class="text-sm">{{ $entry->dispatch_note }}</p>@endif @endif
                    </article>
                @empty<p class="hc-surface p-6">No suitable shortlist was produced. Review the questions above before broadening the search.</p>@endforelse
            </div>
            @if(! $roster->approved_at && $roster->entries->isNotEmpty())<div class="sticky bottom-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-white p-4 shadow-lg"><p class="text-sm">Select caregivers and review every message before sending.</p><button type="button" wire:click="review" wire:loading.attr="disabled" class="hc-primary-button">Review selected outreach</button></div>@endif
        @endif
    @endif
</div>
