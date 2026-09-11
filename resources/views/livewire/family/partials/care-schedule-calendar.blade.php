<section class="hc-schedule-calendar" aria-label="Upcoming care calendar" x-data>
    <div class="hc-calendar-toolbar">
        <div aria-live="polite" aria-atomic="true">
            <h2 id="care-calendar-month">{{ $calendarMonth->format('F Y') }}</h2>
            <p>{{ $monthVisitCount }} upcoming {{ $monthVisitCount === 1 ? 'visit' : 'visits' }} this month</p>
        </div>
        <div class="hc-calendar-controls">
            <button type="button" wire:click="goToToday" class="hc-calendar-today">Today</button>
            <button type="button" wire:click="previousMonth" aria-label="Previous month" wire:loading.attr="disabled">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12 5-5 5 5 5" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </button>
            <button type="button" wire:click="nextMonth" aria-label="Next month" wire:loading.attr="disabled">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m8 5 5 5-5 5" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </button>
        </div>
    </div>

    <div class="hc-calendar-grid" role="group" aria-labelledby="care-calendar-month">
        @foreach (['Sun' => 'Sunday', 'Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday', 'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday'] as $short => $long)
            <div class="hc-calendar-weekday"><abbr title="{{ $long }}">{{ $short }}</abbr></div>
        @endforeach
        @foreach ($calendarDays as $day)
            @php
                $date = $day['date'];
                $dayCount = $day['visits']->count();
                $selected = $date->isSameDay($selectedDay);
            @endphp
            <button type="button"
                wire:key="calendar-day-{{ $date->toDateString() }}"
                x-on:click="await $wire.selectDate('{{ $date->toDateString() }}'); document.getElementById('care-calendar-day-visits').scrollIntoView({ block: 'nearest', behavior: 'instant' })"
                class="hc-calendar-day {{ $day['in_month'] ? '' : 'hc-calendar-day-outside' }}"
                data-date="{{ $date->toDateString() }}"
                aria-pressed="{{ $selected ? 'true' : 'false' }}"
                aria-controls="care-calendar-day-visits"
                aria-label="{{ $date->format('l, F j, Y') }}{{ $date->isToday() ? ', today' : '' }} — {{ $dayCount }} upcoming {{ $dayCount === 1 ? 'visit' : 'visits' }}"
                @if ($date->isToday()) aria-current="date" @endif
            >
                <span class="hc-calendar-date">{{ $date->day }}</span>
                <span class="hc-calendar-previews" aria-hidden="true">
                    @foreach ($day['visits']->take(2) as $visit)
                        <span class="hc-calendar-preview {{ $visit['payment_needs_action'] ? 'hc-calendar-preview-attention' : '' }}">
                            <strong>{{ $visit['starts_at']->lt($date) ? 'Continues' : $visit['starts_at']->format('g:i A') }} · {{ $visit['recipient'] }}</strong>
                            <span>{{ $visit['payment_needs_action'] ? 'Payment needs attention' : $visit['caregiver'] }}</span>
                        </span>
                    @endforeach
                    @if ($dayCount > 2)<span class="hc-calendar-more">+{{ $dayCount - 2 }} more</span>@endif
                </span>
                @if ($dayCount)
                    <span class="hc-calendar-mobile-count" aria-hidden="true"><span></span>{{ $dayCount }}</span>
                @endif
            </button>
        @endforeach
    </div>

    <div id="care-calendar-day-visits" class="hc-calendar-agenda" aria-live="polite" aria-atomic="false">
        <div class="hc-calendar-agenda-heading">
            <div>
                <p>{{ $selectedDay->isToday() ? 'Today' : 'Selected date' }}</p>
                <h3>{{ $selectedDay->format('l, F j') }}</h3>
            </div>
            <span>{{ $selectedVisits->count() }} {{ $selectedVisits->count() === 1 ? 'visit' : 'visits' }}</span>
        </div>
        @forelse ($selectedVisits as $visit)
            <article class="hc-calendar-visit" wire:key="calendar-visit-{{ $visit['id'] }}">
                <div class="hc-calendar-visit-time">
                    <strong>{{ $visit['starts_at']->format('g:i A') }}@if ($visit['ends_at']) – {{ $visit['ends_at']->format('g:i A') }}@endif</strong>
                    @if ($visit['ends_at'] && ! $visit['starts_at']->isSameDay($visit['ends_at']))
                        <span>{{ $visit['starts_at']->format('M j') }} – {{ $visit['ends_at']->format('M j') }}</span>
                    @endif
                    <span>{{ $visit['type_label'] }}</span>
                </div>
                <div class="hc-calendar-visit-person">
                    <h4>{{ $visit['recipient'] }}</h4>
                    <div class="hc-calendar-caregiver">
                        <x-caregiver-identity :caregiver="$visit['caregiver_user']" avatar-only class="shrink-0" />
                        <span>{{ $visit['caregiver'] }}</span>
                    </div>
                    @if ($visit['location'])<p>{{ $visit['location'] }}</p>@endif
                    <p>{{ $visit['reference'] }}</p>
                </div>
                <div class="hc-calendar-visit-actions">
                    <span class="hc-calendar-status {{ $visit['status']['tone'] === 'amber' ? 'hc-calendar-status-attention' : '' }}">{{ $visit['status']['label'] }}</span>
                    @if ($visit['payment_needs_action'])
                        <a href="{{ $visit['action_url'] }}" wire:navigate class="hc-primary-button">Fix payment</a>
                    @else
                        <a href="{{ $visit['action_url'] }}" wire:navigate class="hc-secondary-button">View visit</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="hc-calendar-empty">
                <p>No upcoming visits on this date.</p>
                <span>Choose another date or switch to List to see your next visits.</span>
            </div>
        @endforelse
    </div>
</section>
