<?php

namespace App\Services\Ops;

use App\Jobs\SendManagedCareOpsNotification;
use App\Models as M;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class ManagedCareOpsRecorder
{
    private ?array $batch = null;

    public function __construct(private readonly ManagedCareOpsContext $contexts, private readonly ManagedCareOpsCatalog $catalog) {}

    /** Report a successful compare-and-swap without changing its concurrency semantics. */
    public function recordChanges(Model $before, array $attributes): void
    {
        $snapshot = clone $before;
        $snapshot->forceFill($attributes)->syncChanges();
        $this->record($snapshot, false);
    }

    /** Preserve conditional bulk writes, including provider-state and revision guards. */
    public function updateQuery(Builder|Relation $query, array $attributes): int
    {
        if (! $this->available()) {
            return $query->update($attributes);
        }

        return DB::transaction(function () use ($query, $attributes): int {
            $before = (clone $query)->lockForUpdate()->get();
            $updated = $query->update($attributes);
            foreach ($before as $model) {
                $this->recordChanges($model, array_filter($attributes, fn ($value) => ! $value instanceof Expression));
            }

            return $updated;
        });
    }

    public function record(Model $model, bool $created): void
    {
        if (! $this->available()) {
            return;
        }
        $definition = ManagedCareOpsCatalog::definitions()[$model::class] ?? null;
        if (! $definition) {
            return;
        }
        $changed = $created ? $definition[1] : array_values(array_intersect(array_keys($model->getChanges()), $definition[1]));
        if (! $changed || ($model instanceof M\CareRequest && $model->is_system_generated)) {
            return;
        }
        $this->safely(function () use ($model, $created, $changed): void {
            $context = $this->contexts->resolve($model);
            if (! $context) {
                return;
            }
            if ($model instanceof M\CareRequestApplication && $context['request']->is_system_generated && $model->status === 'hired') {
                // Generated visit assignments repeat an existing hire; the booking carries the event.
                return;
            }
            $event = $this->catalog->describe($model, $created, $changed, $context);
            $actor = $this->contexts->actor($model, $created);
            $details = $this->details($model, $created, $changed, $context);
            if ($created && $model instanceof M\CareBooking && $this->batch && (int) $model->care_plan_id === $this->batch['plan']) {
                $this->recordBatchVisit($model, $context, $actor);

                return;
            }
            $key = $created ? 'created:'.$model::class.':'.$model->getKey() : (string) Str::uuid();
            $this->store($context, $event, $actor, $details, $this->links($model, $context, $event['kind']), $key);
        });
    }

    /** Notes have no Eloquent model; all other staff actions are observed at their source. */
    public function note(M\CareRequest $request, M\User $actor, int $auditId): void
    {
        if (! $this->available()) {
            return;
        }
        $this->safely(function () use ($request, $actor, $auditId): void {
            if ($context = $this->contexts->resolve($request)) {
                $this->store($context, ['kind' => 'note', 'title' => 'Internal note added', 'urgent' => false, 'next' => 'Open the internal note for follow-up.'],
                    $actor, ['A staff note was added. Its contents remain in LoLo.'],
                    [['Open internal notes', route('customer-care.show', ['careRequest' => $request->id, 'panel' => 'activity'])]], 'note:'.$auditId);
            }
        });
    }

    /** Covers changes made only to recipient/task details, without copying their contents. */
    public function detailsSaved(M\CareRequest $request, M\User $actor, int $auditId): void
    {
        if (! $this->available()) {
            return;
        }
        $this->safely(function () use ($request, $actor, $auditId): void {
            if ($context = $this->contexts->resolve($request)) {
                $this->store($context, ['kind' => 'request_details', 'title' => 'Care details saved', 'urgent' => false, 'next' => 'Review the updated care instructions in LoLo.'],
                    $actor, ['Recipient, care instructions, or requested tasks changed. Details remain in LoLo.'],
                    [['Open care details', route('customer-care.show', ['careRequest' => $request->id, 'tab' => 'overview'])]], 'details:'.$auditId);
            }
        });
    }

    public function batchOccurrences(M\CarePlan $plan, Closure $callback): mixed
    {
        $previous = $this->batch;
        $this->batch = ['plan' => (int) $plan->id, 'id' => (string) Str::uuid(), 'count' => 0];
        try {
            return $callback();
        } finally {
            $this->batch = $previous;
        }
    }

    private function recordBatchVisit(M\CareBooking $booking, array $context, ?M\User $actor): void
    {
        $group = intdiv($this->batch['count']++, 20);
        $key = hash('sha256', 'occurrences:'.$this->batch['id'].':'.$group);
        $line = 'Visit #'.$booking->id.' · '.$this->contexts->period($booking->scheduled_start_at, $booking->scheduled_end_at, $context['timezone']);
        $existing = M\ManagedCareOpsEvent::where('dedupe_key', $key)->lockForUpdate()->first();
        if ($existing && $existing->status !== 'pending') {
            // A slow generation run may outlive the first delivery; start a fresh group.
            $this->batch['count'] = ($group + 1) * 20;
            $this->recordBatchVisit($booking, $context, $actor);

            return;
        }
        $link = $this->bookingUrl($booking, $context);
        if ($existing && $existing->status === 'pending') {
            $payload = $existing->payload;
            // Each line links to its own visit. Chunking keeps every date visible within Slack limits.
            array_splice($payload['blocks'], count($payload['blocks']) - 2, 0, [[
                'type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '<'.$link.'|'.$this->escape($line).'>'],
            ]]);
            $existing->update(['payload' => $payload, 'available_at' => now()->addSeconds(30)]);

            return;
        }
        $batchContext = $context;
        $batchContext['booking'] = null;
        $this->store($batchContext, ['kind' => 'visits_generated', 'title' => 'Recurring visits generated', 'urgent' => false,
            'next' => 'Review the upcoming schedule. Each visit is linked below.'], $actor, [],
            [['Open recurring care', $this->recurringUrl($context)]],
            'occurrences:'.$this->batch['id'].':'.$group,
            [['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '<'.$link.'|'.$this->escape($line).'>']]], 30);
    }

    private function store(array $context, array $event, ?M\User $actor, array $details, array $links, string $key, array $extraBlocks = [], int $delay = 0): void
    {
        $root = $context['root'];
        $booking = $context['booking'];
        $family = $root->family;
        $fields = [
            ['Family', ($family?->name ?: 'Family account').' · #'.$root->family_user_id],
            ['Request', Str::limit((string) $root->title, 180).' · #'.$root->id],
            ['Caregiver', $context['caregiver'] ? $context['caregiver']->name.' · #'.$context['caregiver']->id : 'Not assigned / request-level event'],
            ['By', $actor ? $actor->name.' · '.$this->catalog->label($actor->role).' #'.$actor->id : 'Automatic system update'],
        ];
        if ($booking) {
            $fields[] = ['Booking', '#'.$booking->id.' · '.$this->contexts->period($booking->scheduled_start_at, $booking->scheduled_end_at, $context['timezone'])];
        } else {
            $schedule = $context['plan']?->weeklyScheduleSlots() ?? ($root->isRecurring() ? $root->recurringScheduleSlots() : []);
            $fields[] = ['Schedule', $schedule ? \App\Support\WeeklySchedule::label($schedule).' ('.$context['timezone'].')'
                : $this->contexts->period($root->requested_start_at, $root->requested_end_at, $context['timezone'])];
        }
        $fields[] = ['Event time', now()->setTimezone($context['timezone'])->format('M j, Y · g:i:s A T')];
        $blocks = [
            ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => Str::limit(($event['urgent'] ? '🔴 ' : 'ℹ️ ').$event['title'], 150, '')]],
            ['type' => 'section', 'fields' => array_map(fn ($field) => ['type' => 'mrkdwn', 'text' => '*'.$field[0]."*\n".$this->escape($field[1])], $fields)],
        ];
        foreach ($details as $detail) {
            $blocks[] = ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $this->escape($detail)]];
        }
        $blocks = array_merge($blocks, $extraBlocks);
        $blocks[] = ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '*Next action:* '.$this->escape($event['next'])]];
        $links[] = ['Open request', route('customer-care.show', $root->staff_managed ? $root->id : $context['request']->id)];
        $blocks[] = ['type' => 'actions', 'elements' => array_map(fn ($link) => [
            'type' => 'button', 'text' => ['type' => 'plain_text', 'text' => $link[0]], 'url' => $link[1],
        ], array_slice($links, 0, 5))];
        $entry = M\ManagedCareOpsEvent::firstOrCreate(['dedupe_key' => hash('sha256', $key)], [
            'event_key' => $event['kind'], 'care_request_id' => $root->id, 'care_booking_id' => $booking?->id,
            'payload' => ['text' => $this->escape($event['title'].' · '.($family?->name ?: 'Family').' · Request #'.$root->id),
                'unfurl_links' => false, 'unfurl_media' => false, 'blocks' => $blocks],
            'available_at' => now()->addSeconds($delay),
        ]);
        if ($entry->wasRecentlyCreated) {
            DB::afterCommit(function () use ($entry, $delay): void {
                try {
                    SendManagedCareOpsNotification::dispatch($entry->id)->delay(now()->addSeconds($delay));
                } catch (Throwable) {
                    // The scheduled outbox drain recovers a broker outage.
                    Log::warning('Managed care Slack dispatch deferred.', ['event_id' => $entry->id]);
                }
            });
        }
    }

    private function details(Model $model, bool $created, array $changed, array $context): array
    {
        $details = [];
        $reference = match (true) {
            $model instanceof M\SupportTicket => 'Support ticket',
            $model instanceof M\CareBookingTimeCorrection => 'Time correction',
            $model instanceof M\CareBookingIncident => 'Incident',
            $model instanceof M\CareBookingChangeRequest => 'Visit change',
            $model instanceof M\CarePlanScheduleChange => 'Schedule change',
            $model instanceof M\CompletedExtraVisitRequest => 'Extra care report',
            default => null,
        };
        if ($reference) {
            $details[] = $reference.' #'.$model->id;
        }
        if ($model instanceof M\SupportTicketMessage) {
            $details[] = 'Support ticket #'.$model->support_ticket_id;
        }
        if (! $created && in_array('status', $changed)) {
            $details[] = 'Status: '.$this->catalog->label((string) $model->getRawOriginal('status')).' → '.$this->catalog->label((string) $model->status);
        }
        foreach ([['scheduled_start_at', 'scheduled_end_at'], ['requested_start_at', 'requested_end_at'], ['proposed_start_at', 'proposed_end_at'], ['proposed_started_at', 'proposed_completed_at']] as [$start, $end]) {
            if (($created || array_intersect([$start, $end], $changed)) && ($model->getAttribute($start) || $model->getRawOriginal($start))) {
                if (! $created) {
                    $details[] = 'Previously: '.$this->contexts->period($model->getRawOriginal($start), $model->getRawOriginal($end), $context['timezone']);
                }
                $details[] = ($created ? 'When: ' : 'Now: ').$this->contexts->period($model->getAttribute($start), $model->getAttribute($end), $context['timezone']);
            }
        }
        if ($model instanceof M\CarePlanScheduleChange) {
            foreach (['current_schedule' => 'Previously', 'proposed_schedule' => 'Proposed'] as $field => $label) {
                $details[] = $label.': '.$this->schedule($model->getAttribute($field), $context['timezone']);
            }
        }
        if ($model instanceof M\CarePlan && array_intersect(['schedule_days', 'schedule_slots', 'schedule_start_time', 'schedule_end_time'], $changed)) {
            if (! $created) {
                $old = new M\CarePlan;
                $old->setRawAttributes($model->getRawOriginal());
                $details[] = 'Previous weekly schedule: '.\App\Support\WeeklySchedule::label($old->weeklyScheduleSlots()).' ('.$context['timezone'].')';
            }
            $details[] = 'Weekly schedule: '.\App\Support\WeeklySchedule::label($model->weeklyScheduleSlots()).' ('.$context['timezone'].')';
        }
        if ($model instanceof M\CarePlan && $model->status === 'countered') {
            $counter = \App\Support\WeeklySchedule::normalize($model->counter_schedule_slots);
            if (! $counter) {
                $counter = array_map(fn ($day) => ['day' => $day, 'start_time' => $model->counter_schedule_start_time, 'end_time' => $model->counter_schedule_end_time], $model->counter_schedule_days ?? []);
            }
            $details[] = 'Caregiver proposed: '.\App\Support\WeeklySchedule::label($counter).' ('.$context['timezone'].')';
        }
        if ($model instanceof M\CareRequestApplication && $model->selected_days !== null) {
            $details[] = 'Offered schedule: '.$this->schedule($model->offered_schedule, $context['timezone']);
        }
        if ($model instanceof M\CareRequest && $model->isRecurring() && array_intersect(['recurring_schedule', 'recurring_days', 'recurring_start_time', 'recurring_end_time'], $changed)) {
            if (! $created) {
                $old = new M\CareRequest;
                $old->setRawAttributes($model->getRawOriginal());
                $details[] = 'Previous weekly schedule: '.\App\Support\WeeklySchedule::label($old->recurringScheduleSlots()).' ('.$context['timezone'].')';
            }
            $details[] = 'Weekly schedule: '.\App\Support\WeeklySchedule::label($model->recurringScheduleSlots()).' ('.$context['timezone'].')';
        }
        foreach (['starts_on', 'ends_on', 'pause_starts_on', 'resumes_on', 'effective_on', 'recurring_starts_on', 'recurring_ends_on'] as $field) {
            if (($created || in_array($field, $changed)) && $model->getAttribute($field) !== null) {
                $details[] = $this->catalog->label($field).': '.(! $created ? substr((string) $model->getRawOriginal($field), 0, 10).' → ' : '').substr((string) $model->getAttribute($field), 0, 10);
            }
        }
        if ($model instanceof M\CareBookingPayment) {
            foreach (['amount_authorized_cents' => 'Authorized', 'amount_captured_cents' => 'Captured', 'amount_refunded_cents' => 'Refunded'] as $field => $label) {
                if ((int) $model->getAttribute($field) > 0) {
                    $details[] = $label.': '.strtoupper($model->currency ?: 'usd').' '.number_format($model->getAttribute($field) / 100, 2);
                }
            }
        }
        foreach (['worked_minutes' => 'Actual care minutes', 'proposed_worked_minutes' => 'Proposed care minutes', 'reserved_minutes' => 'Reserved prepaid minutes', 'shortfall_minutes' => 'Minutes needing payment', 'prepaid_minutes' => 'Prepaid minutes used', 'card_minutes' => 'Minutes paid separately'] as $field => $label) {
            if (($created || in_array($field, $changed)) && $model->getAttribute($field) !== null) {
                $details[] = $label.': '.(! $created ? (int) $model->getRawOriginal($field).' → ' : '').(int) $model->getAttribute($field);
            }
        }
        if (! $created && array_intersect(['scope_of_work', 'home_access_notes', 'address_line1', 'address_line2', 'cover_note', 'body'], $changed)) {
            $details[] = 'Content was updated. Read the full details inside LoLo.';
        }

        return $details;
    }

    private function schedule(?array $schedule, string $timezone): string
    {
        if (! $schedule) {
            return 'See schedule in LoLo';
        }
        $slots = $schedule['slots'] ?? $schedule['schedule_slots'] ?? (array_is_list($schedule) ? $schedule : []);
        if ($slots) {
            return \App\Support\WeeklySchedule::label($slots).' ('.$timezone.')';
        }

        return $this->contexts->period($schedule['start_at'] ?? $schedule['starts_at'] ?? $schedule['start'] ?? null, $schedule['end_at'] ?? $schedule['ends_at'] ?? $schedule['end'] ?? null, $timezone);
    }

    private function links(Model $subject, array $context, string $kind): array
    {
        $requestId = $context['request']->staff_managed ? $context['request']->id : $context['root']->id;
        if ($kind === 'message' && $conversation = $context['conversation']) {
            return [['Open conversation', route('customer-care.show', ['careRequest' => $requestId, 'panel' => 'messages', 'conversation' => $conversation->id]).'#conversation-'.$conversation->id]];
        }
        if ($kind === 'application') {
            return [['Review applicant', route('customer-care.show', ['careRequest' => $requestId, 'tab' => 'applicants',
                ...(in_array($subject->status, ['applied', 'shortlisted'], true) ? ['review_hire' => $subject->id] : [])])]];
        }
        if ($context['booking']) {
            return [['Open booking', $this->bookingUrl($context['booking'], $context, in_array($kind, ['incident', 'support', 'support_message', 'visit_change'], true) ? 'support' : 'shift')]];
        }
        if ($context['plan']) {
            return [['Open recurring care', $this->recurringUrl($context)]];
        }

        return [];
    }

    private function bookingUrl(M\CareBooking $booking, array $context, string $tab = 'shift'): string
    {
        if ($booking->careRequest->staff_managed) {
            return route('customer-care.show', ['careRequest' => $booking->care_request_id, 'tab' => $tab]);
        }

        // Earlier visits can predate takeover. Open them through the managed parent.
        return route('customer-care.show', ['careRequest' => $context['root']->id, 'panel' => 'visits', 'booking' => $booking->id]).'#visit-'.$booking->id;
    }

    private function recurringUrl(array $context): string
    {
        $source = $context['plan']->sourceCareRequest;

        return route('customer-care.show', ['careRequest' => $source?->staff_managed ? $source->id : $context['root']->id, 'panel' => 'visits']);
    }

    private function escape(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], Str::limit($value, 2500, '…'));
    }

    private function available(): bool
    {
        return app(SlackOpsNotificationService::class)->enabled() && Schema::hasTable('managed_care_ops_events');
    }

    private function safely(Closure $callback): void
    {
        try {
            DB::transaction($callback);
        } catch (Throwable $exception) {
            // Do not include exception text: queries can contain private operational data.
            Log::error('Managed care Slack event could not be recorded.', ['exception_type' => $exception::class]);
        }
    }
}
