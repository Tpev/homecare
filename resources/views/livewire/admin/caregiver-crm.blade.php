<div class="caregiver-crm">
    @php
        $stages = ['new' => 'New', 'applied' => 'Applied', 'hired' => 'Hired'];
        $descriptions = ['new' => 'Approved and ready for their first application.', 'applied' => 'Applied for care. Help them find their first hire.', 'hired' => 'Hired for care at least once. Keep the relationship going.'];
        $formatDate = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('M j, Y · g:i A T') : null;
        $isDue = fn ($value) => $value && \Illuminate\Support\Carbon::parse($value)->isPast();
        $activityLabels = ['note' => 'Note', 'call' => 'Phone call', 'email' => 'Email', 'sms' => 'Text message', 'meeting' => 'Meeting'];
        $callLabels = ['connected' => 'Spoke on phone', 'no_answer' => 'No answer', 'voicemail' => 'Left voicemail'];
    @endphp
    <div class="cg-intro">
        <div>
            <h2>Approved caregivers</h2>
            <p class="cg-muted">A clear next step, from approval to their first hire.</p>
        </div>
        <button type="button" class="cg-button" wire:click="$refresh" wire:loading.attr="disabled">Refresh board</button>
    </div>

    <section class="cg-toolbar" aria-label="Filter caregivers">
        <div class="cg-filters">
            <label>Search caregivers
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Name, email, phone or ZIP" maxlength="200">
            </label>
            <label>Phone conversation
                <select wire:model.live="contact">
                    <option value="all">Everyone</option>
                    <option value="called">Spoke on phone</option>
                    <option value="not_called">Not spoken with yet</option>
                </select>
            </label>
            <label>Follow-up
                <select wire:model.live="followUp">
                    <option value="all">All follow-ups</option>
                    <option value="due">Due now</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="none">No follow-up date</option>
                </select>
            </label>
            <label>Assigned to
                <select wire:model.live="assigned">
                    <option value="all">Everyone</option>
                    <option value="me">Me</option>
                    <option value="unassigned">Unassigned</option>
                </select>
            </label>
        </div>
        <div class="cg-summary" aria-live="polite">
            <span><strong>{{ number_format($board['stats']['total']) }}</strong> approved caregivers</span>
            <span><strong>{{ number_format($board['stats']['called']) }}</strong> spoken with</span>
            <span><strong>{{ number_format($board['stats']['due']) }}</strong> follow-ups due</span>
            <span><strong>{{ number_format($board['stats']['unassigned']) }}</strong> unassigned</span>
            @if ($search !== '' || $contact !== 'all' || $followUp !== 'all' || $assigned !== 'all')
                <button type="button" class="cg-text-button" wire:click="clearFilters">Clear filters</button>
            @endif
        </div>
    </section>

    <div class="cg-board">
        @foreach ($stages as $stage => $label)
            @php
                $cards = $board['columns'][$stage];
                $offset = (int) ($board['offsets'][$stage] ?? $columnOffsets[$stage] ?? 0);
                $count = (int) $board['counts'][$stage];
            @endphp
            <section class="cg-column" data-stage="{{ $stage }}" aria-labelledby="caregiver-stage-{{ $stage }}" wire:key="caregiver-column-{{ $stage }}">
                <div class="cg-column-heading">
                    <h3 id="caregiver-stage-{{ $stage }}">{{ $label }}</h3>
                    <span aria-label="{{ $count }} caregivers">{{ number_format($count) }}</span>
                </div>
                <p class="cg-muted">{{ $descriptions[$stage] }}</p>
                <div class="cg-cards">
                    @forelse ($cards as $card)
                        <button type="button" class="cg-card" wire:click="openCaregiver({{ $card['user_id'] }})" wire:key="caregiver-card-{{ $card['user_id'] }}" aria-label="Open {{ $card['name'] }}">
                            <span class="cg-card-top">
                                <span class="cg-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($card['name'], 0, 1)) }}</span>
                                <span class="cg-card-identity">
                                    <span class="cg-card-name">{{ $card['name'] }}</span>
                                    <span class="cg-card-location">{{ $card['zip'] ? 'ZIP '.$card['zip'] : 'Service area not provided' }}</span>
                                </span>
                                <span aria-hidden="true">↗</span>
                            </span>
                            <span class="cg-card-email">{{ $card['email'] ?: $card['phone'] ?: 'No contact details' }}</span>
                            <span class="cg-tags">
                                @if ($card['phone_spoken_at'])
                                    <span class="cg-tag">✓ Spoke on phone</span>
                                @endif
                                @if (in_array($card['priority'], ['high', 'urgent'], true))
                                    <span class="cg-tag cg-tag-{{ $card['priority'] }}">{{ ucfirst($card['priority']) }} priority</span>
                                @endif
                                @if ($isDue($card['next_follow_up_at']))
                                    <span class="cg-tag cg-tag-due">Follow-up due</span>
                                @endif
                            </span>
                            <span class="cg-card-counts">
                                <span>{{ $card['applications_count'] }} {{ $card['applications_count'] === 1 ? 'application' : 'applications' }}</span>
                                <span>{{ $card['hires_count'] }} {{ $card['hires_count'] === 1 ? 'hire' : 'hires' }}</span>
                            </span>
                            @if ($card['next_action'])
                                <span class="cg-card-action">
                                    <strong>{{ \Illuminate\Support\Str::limit($card['next_action'], 100) }}</strong>
                                    @if ($card['next_follow_up_at'])
                                        <time datetime="{{ \Illuminate\Support\Carbon::parse($card['next_follow_up_at'])->toIso8601String() }}">{{ $formatDate($card['next_follow_up_at']) }}</time>
                                    @endif
                                </span>
                            @elseif ($card['notes'])
                                <span class="cg-card-action cg-muted">{{ \Illuminate\Support\Str::limit($card['notes'], 105) }}</span>
                            @endif
                            <span class="cg-card-owner">{{ $card['assigned_name'] ? 'Owner: '.$card['assigned_name'] : 'Unassigned' }}</span>
                        </button>
                    @empty
                        <div class="cg-empty">{{ $search || $contact !== 'all' || $followUp !== 'all' || $assigned !== 'all' ? 'No caregivers match these filters.' : 'No caregivers in this stage yet.' }}</div>
                    @endforelse
                </div>
                @if ($count > 8)
                    <div class="cg-summary">
                        <span>{{ min($offset + 1, $count) }}–{{ min($offset + count($cards), $count) }} of {{ $count }}</span>
                        <div class="flex gap-2">
                            <button type="button" class="cg-button" wire:click="previousPage('{{ $stage }}')" @disabled($offset === 0) aria-label="Previous {{ $label }} caregivers">←</button>
                            <button type="button" class="cg-button" wire:click="loadMore('{{ $stage }}')" @disabled($offset + count($cards) >= $count) aria-label="Next {{ $label }} caregivers">→</button>
                        </div>
                    </div>
                @endif
            </section>
        @endforeach
    </div>
    <p class="cg-muted cg-board-footer">Stages follow application and hiring history automatically. Only caregivers currently approved for work appear here.</p>

    @if ($selectedCaregiverId)
        <div class="cg-modal" wire:key="caregiver-panel-{{ $selectedCaregiverId }}" x-data="{
            recordIsDirty() { return JSON.stringify($wire.recordForm) !== JSON.stringify($wire.savedRecordForm); },
            activityIsDirty() { return JSON.stringify($wire.activityForm) !== JSON.stringify($wire.savedActivityForm); },
            requestClose() {
                if ((!this.recordIsDirty() && !this.activityIsDirty()) || window.confirm('Discard your unsaved notes and changes?')) $wire.closeCaregiver();
            }
        }" x-trap.inert.noscroll="true" x-on:keydown.escape.window="requestClose()">
            <button type="button" class="cg-backdrop" x-on:click="requestClose()" aria-label="Close caregiver panel" tabindex="-1"></button>
            <section class="cg-panel" role="dialog" aria-modal="true" aria-labelledby="caregiver-panel-heading">
                <header class="cg-panel-header">
                    <div>
                        <p class="cg-eyebrow">Caregiver relationship</p>
                        <h2 id="caregiver-panel-heading">{{ $selected['name'] ?? 'Caregiver unavailable' }}</h2>
                        @if ($selected)<p class="cg-muted">Approved · {{ $stages[$selected['stage']] }}</p>@endif
                    </div>
                    <button type="button" class="cg-button" x-on:click="requestClose()">Close</button>
                </header>
                <div class="cg-panel-body">
                    @if ($notice)<div class="cg-notice" role="status">{{ $notice }}</div>@endif
                    @if ($errors->any())
                        <div class="cg-error" role="alert">
                            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                            @if ($errors->has('record'))
                                <button type="button" wire:click="reloadCaregiver" class="cg-text-button">Reload saved details</button>
                            @endif
                        </div>
                    @endif
                    @if (! $selected)
                        <div class="cg-section">
                            <h3>This caregiver is no longer available in this board.</h3>
                            <p class="cg-muted">They may no longer be approved for work. Their saved notes and activity are preserved.</p>
                        </div>
                    @else
                        <section class="cg-section">
                            <div class="cg-tags">
                                <span class="cg-tag">{{ $stages[$selected['stage']] }}</span>
                                @if ($selected['phone_spoken_at'])<span class="cg-tag">✓ Spoke on phone</span>@endif
                                @if ($isDue($selected['next_follow_up_at']))<span class="cg-tag cg-tag-due">Follow-up due</span>@endif
                            </div>
                            <div class="cg-contact-details">
                                @if ($selected['email'])<a href="mailto:{{ $selected['email'] }}">{{ $selected['email'] }}</a>@endif
                                @if ($selected['phone'])<a href="tel:{{ preg_replace('/[^0-9+]/', '', $selected['phone']) }}">{{ $selected['phone'] }}</a>@endif
                            </div>
                            <p class="cg-muted mt-3">{{ $selected['applications_count'] }} applications · {{ $selected['hires_count'] }} hires @if($selected['zip']) · ZIP {{ $selected['zip'] }} @endif</p>
                            @if ($selected['phone_spoken_at'])
                                <p class="cg-muted">Phone conversation recorded {{ $formatDate($selected['phone_spoken_at']) }}</p>
                            @endif
                            <a class="cg-text-button inline-block mt-2" href="{{ route('admin.users.show', $selected['user_id']) }}" target="_blank" rel="noopener">Open caregiver account ↗</a>
                        </section>

                        <form class="cg-section" wire:submit="saveRecord">
                            <h3>Notes & next action</h3>
                            <div class="cg-detail-grid">
                                <label>Owner
                                    <select wire:model="recordForm.assigned_admin_id">
                                        <option value="">Unassigned</option>
                                        @foreach ($owners as $owner)<option value="{{ $owner->id }}">{{ $owner->name }}</option>@endforeach
                                    </select>
                                </label>
                                <label>Priority
                                    <select wire:model="recordForm.priority">
                                        <option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option>
                                    </select>
                                </label>
                                <label class="cg-full">Pinned notes
                                    <textarea rows="3" wire:model="recordForm.notes" maxlength="5000" placeholder="Availability, preferences, or useful context for the team."></textarea>
                                </label>
                                <label class="cg-full">Next action
                                    <input type="text" wire:model="recordForm.next_action" maxlength="255" placeholder="For example, call about the weekend request">
                                </label>
                                <label class="cg-full">Follow-up date
                                    <input type="datetime-local" wire:model="recordForm.next_follow_up_at">
                                </label>
                            </div>
                            <div class="cg-form-actions">
                                <span class="cg-muted" x-show="recordIsDirty()" x-cloak>Unsaved changes</span>
                                @if ($selected['next_action'])
                                    <button type="button" class="cg-button" wire:click="completeNextAction" wire:loading.attr="disabled" x-bind:disabled="recordIsDirty()">Mark action complete</button>
                                @endif
                                <button type="submit" class="cg-button cg-button-primary" wire:loading.attr="disabled">Save details</button>
                            </div>
                        </form>

                        <form class="cg-section" wire:submit="logActivity">
                            <h3>Add a note or interaction</h3>
                            <div class="cg-detail-grid">
                                <label>Activity type
                                    <select wire:model.live="activityForm.type">
                                        @foreach ($activityLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                    </select>
                                </label>
                                <label>Happened at
                                    <input type="datetime-local" wire:model="activityForm.occurred_at">
                                </label>
                                @if (($activityForm['type'] ?? '') === 'call')
                                    <label class="cg-full">Call outcome
                                        <select wire:model="activityForm.call_outcome" required>
                                            <option value="">Choose an outcome</option>
                                            @foreach ($callLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                        </select>
                                    </label>
                                @endif
                                <label class="cg-full">What happened?
                                    <textarea rows="3" wire:model="activityForm.body" maxlength="5000" required placeholder="Capture the conversation and anything the next person should know."></textarea>
                                </label>
                            </div>
                            <div class="cg-form-actions">
                                <span class="cg-muted">Times shown in {{ config('app.timezone') }}.<br>Logging an interaction does not send a message.</span>
                                <button type="submit" class="cg-button cg-button-primary" wire:loading.attr="disabled">Add to timeline</button>
                            </div>
                        </form>

                        <section class="cg-section" aria-label="Caregiver activity timeline">
                            <h3>Activity timeline</h3>
                            <ol class="cg-timeline">
                                @forelse ($activities as $activity)
                                    <li wire:key="caregiver-activity-{{ $activity->id }}">
                                        <div class="cg-timeline-title">
                                            <span>{{ $activity->type === 'system' ? (data_get($activity->metadata, 'action') === 'next_action_completed' ? 'Next action completed' : 'Details updated') : ($activityLabels[$activity->type] ?? 'Activity') }}</span>
                                            <time datetime="{{ $activity->occurred_at->toIso8601String() }}">{{ $formatDate($activity->occurred_at) }}</time>
                                        </div>
                                        @if ($activity->call_outcome)<span class="cg-tag mt-2">{{ $callLabels[$activity->call_outcome] ?? '' }}</span>@endif
                                        @if ($activity->body)<p>{{ $activity->body }}</p>@endif
                                        <small>{{ $activity->actor?->name ?? 'Former team member' }}</small>
                                    </li>
                                @empty
                                    <li><p class="cg-muted">No interactions recorded yet. Add a note or log your first conversation above.</p></li>
                                @endforelse
                            </ol>
                            @if ($activityOffset > 0 || $hasMoreActivity)
                                <div class="cg-form-actions">
                                    @if ($activityOffset > 0)<button type="button" class="cg-button" wire:click="previousActivity">Newer activity</button>@endif
                                    @if ($hasMoreActivity)<button type="button" class="cg-button" wire:click="loadMoreActivity">Older activity</button>@endif
                                </div>
                            @endif
                        </section>
                    @endif
                </div>
            </section>
        </div>
    @endif
</div>
