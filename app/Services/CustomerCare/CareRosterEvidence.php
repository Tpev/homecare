<?php

namespace App\Services\CustomerCare;

use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\User;
use App\Services\Marketplace\CaregiverDistanceFilter;
use App\Services\Messaging\TwilioSmsClient;
use App\Services\RegularCare\CarePlanOccurrenceService;
use App\Support\WeeklySchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CareRosterEvidence
{
    public function requestData(CareRequest $request): array
    {
        return $request->only(['id', 'family_account_id', 'family_user_id', 'title', 'scope_of_work',
            'additional_info', 'time_expectations', 'city', 'state', 'zip', 'status', 'staff_managed',
            'staff_archived_at', 'request_type', 'requested_start_at', 'requested_end_at', 'recurring_days',
            'recurring_start_time', 'recurring_end_time', 'recurring_schedule', 'recurring_starts_on', 'recurring_ends_on'])
            + ['tasks' => $request->tasks()->orderBy('care_tasks.id')->get()->map(fn ($task) => ['id' => $task->id, 'name' => $task->name, 'notes' => $task->pivot?->task_note])->all()];
    }

    public function requestFingerprint(CareRequest $request): string
    {
        $request = $request->fresh();
        $leads = $request->family_account_id ? DB::table('leads')->where('family_account_id', $request->family_account_id)->pluck('id')->all() : [];
        $data = $this->requestData($request) + ['customer_evidence_version' => [
            'lead_ids' => $leads,
            'sms' => DB::table('sms_messages')->whereIn('lead_id', $leads)->max('id'),
            'notes' => DB::table('lead_activities')->whereIn('lead_id', $leads)->max('updated_at'),
            'follow_up' => DB::table('customer_care_actions')->where('care_request_id', $request->id)->where('action', 'follow_up_note')->max('id'),
        ]];

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function customer(CareRequest $request): array
    {
        $coverage = [];
        $leadIds = $request->family_account_id
            ? DB::table('leads')->where('family_account_id', $request->family_account_id)->pluck('id')->all() : [];
        $records = $this->rows('customer_care_actions', DB::table('customer_care_actions')->where('care_request_id', $request->id),
            ['id', 'actor_user_id', 'action', 'note', 'created_at'], $coverage);
        $records = array_merge($records,
            $this->rows('lead_activities', DB::table('lead_activities')->whereIn('lead_id', $leadIds)->whereNull('sms_message_id'),
                ['id', 'lead_id', 'actor_user_id', 'type', 'summary', 'body', 'occurred_at', 'created_at'], $coverage),
            $this->rows('sms_messages', DB::table('sms_messages')->whereIn('lead_id', $leadIds),
                ['id', 'lead_id', 'direction', 'body', 'status', 'created_at'], $coverage),
            $this->messages(DB::table('care_request_conversations')->where('care_request_id', $request->id)->pluck('id')->all(), $coverage));

        return ['request' => $this->requestData($request), 'customer_name' => $request->family?->name,
            'lead_ids' => $leadIds, 'lead_link_note' => $leadIds ? null : 'No linked lead history; customer context may be incomplete.',
            'records' => $records, 'coverage' => $coverage, 'timezone' => config('app.timezone')];
    }

    public function pool(CareRequest $request): array
    {
        $profiles = CaregiverProfile::with(['user', 'skills', 'languages', 'availabilities'])->orderBy('user_id')->get();
        $distances = app(CaregiverDistanceFilter::class)->within($request, 100);
        $invited = $request->invitations()->pluck('caregiver_user_id')->merge($request->applications()->pluck('caregiver_user_id'))->all();
        $candidates = [];
        $excluded = ['not_eligible' => 0, 'outside_search_area' => 0, 'opted_out' => 0];
        foreach ($profiles as $profile) {
            $user = $profile->user;
            if (! $user || $user->role !== 'caregiver' || $user->access_revoked_at || ! $profile->isReadyForInvitation() || ! $profile->is_accepting_new_clients) {
                $excluded['not_eligible']++;

                continue;
            }
            if ($this->optedOut($user)) {
                $excluded['opted_out']++;

                continue;
            }
            $distance = $distances[$profile->service_area_zip] ?? null;
            $knownFar = app(CaregiverDistanceFilter::class)->hasOrigin($request)
                && app(CaregiverDistanceFilter::class)->hasZip($profile->service_area_zip)
                && ($distance === null || $distance > max(50, min(100, (int) $profile->service_radius_miles)));
            if ($knownFar && ! in_array($user->id, $invited)) {
                $excluded['outside_search_area']++;

                continue;
            }
            $candidates[] = $this->caregiver($request, $user, $distance);
        }
        if (count($candidates) > config('care_roster.maximum_candidates')) {
            throw new \RuntimeException('Too many regional candidates for one review. No partial roster was created.');
        }

        return ['candidates' => $candidates, 'coverage' => [
            'profiles_screened' => $profiles->count(), 'candidates_reviewed' => count($candidates), 'excluded' => $excluded,
            'geography' => 'ZIP-centre estimates; search includes at least 50 miles, stated radii up to 100 miles, unknown locations and existing invitees.',
            'conflicts' => 'Checks the first '.config('care_roster.conflict_days').' days from the next requested visit; outside commitments require confirmation.',
        ]];
    }

    public function caregiver(CareRequest $request, User $user, ?float $distance = null): array
    {
        $profile = $user->caregiverProfile()->with(['availabilities', 'certifications.type', 'skills', 'languages', 'careExperiences'])->firstOrFail();
        $coverage = [];
        $records = [['ref' => 'caregiver_profiles:'.$profile->id] + $profile->only([
            'id', 'user_id', 'bio', 'care_experience_notes', 'years_experience', 'service_area_zip', 'service_radius_miles',
            'status', 'screening_initial_approved_at', 'screening_suspended_at', 'updated_at'])];
        foreach ($profile->availabilities as $availability) {
            $records[] = ['ref' => 'caregiver_availabilities:'.$availability->id] + $availability->only(['id', 'day_of_week', 'start_time', 'end_time', 'updated_at']);
        }
        foreach ($profile->certifications as $cert) {
            $records[] = ['ref' => 'caregiver_certifications:'.$cert->id, 'label' => $cert->displayName()]
                + $cert->only(['id', 'verification_status', 'expires_at', 'verified_at', 'updated_at']);
        }
        $specs = [
            'caregiver_crm_records' => ['id', 'notes', 'next_action', 'next_follow_up_at', 'assigned_admin_id', 'updated_at'],
            'caregiver_crm_activities' => ['id', 'actor_user_id', 'type', 'call_outcome', 'body', 'occurred_at', 'created_at'],
            'sms_messages' => ['id', 'direction', 'body', 'status', 'created_at'],
            'care_request_invitations' => ['id', 'care_request_id', 'status', 'responded_at', 'created_at'],
            'care_request_applications' => ['id', 'care_request_id', 'status', 'cover_note', 'selected_days', 'offered_schedule', 'updated_at'],
            'care_request_recruitments' => ['id', 'care_request_id', 'status', 'created_at'],
            'care_plans' => ['id', 'source_care_request_id', 'status', 'schedule_days', 'schedule_start_time', 'schedule_end_time', 'schedule_slots', 'starts_on', 'ends_on', 'timezone', 'updated_at'],
            'care_bookings' => ['id', 'care_request_id', 'status', 'scheduled_start_at', 'scheduled_end_at', 'replacement_released_at', 'updated_at'],
            'continuous_coverage_roster_members' => ['id', 'continuous_coverage_plan_id', 'status', 'role', 'eligible_days', 'updated_at'],
            'continuous_coverage_shift_offers' => ['id', 'continuous_coverage_shift_id', 'status', 'expires_at', 'updated_at'],
        ];
        foreach ($specs as $table => $fields) {
            $query = DB::table($table)->where('caregiver_user_id', $user->id);
            if ($table === 'caregiver_crm_activities') {
                $query->whereNull('sms_message_id');
            }
            $records = array_merge($records, $this->rows($table, $query, $fields, $coverage));
        }
        $records = array_merge($records,
            $this->rows('continuous_coverage_shifts', DB::table('continuous_coverage_shifts')->where('assigned_caregiver_user_id', $user->id),
                ['id', 'status', 'scheduled_start_at', 'scheduled_end_at', 'updated_at'], $coverage),
            $this->messages(DB::table('care_request_conversations')->where('caregiver_user_id', $user->id)->pluck('id')->all(), $coverage));
        // Include schedules for other outreach so a generic positive SMS is not assigned to this job.
        $otherIds = collect($records)->pluck('care_request_id')->filter()->unique()->values()->all();
        $records = array_merge($records, $this->rows('care_requests', DB::table('care_requests')->whereIn('id', $otherIds),
            ['id', 'title', 'city', 'zip', 'status', 'requested_start_at', 'requested_end_at', 'recurring_schedule', 'recurring_starts_on'], $coverage));
        $state = $this->state($request, $user);

        return ['caregiver_user_id' => $user->id, 'name' => $user->name, 'city' => $user->city, 'state' => $user->state,
            'distance_miles_estimate' => $distance === null ? null : round($distance, 1),
            'skills' => $profile->skills->pluck('name')->all(), 'languages' => $profile->languages->pluck('name')->all(),
            'experience_tags' => $profile->careExperiences->pluck('label')->all(),
            'operational' => $state, 'records' => $this->sanitize($records), 'coverage' => $coverage];
    }

    public function state(CareRequest $request, User $user): array
    {
        $profile = $user->caregiverProfile;
        $invitation = $request->invitations()->where('caregiver_user_id', $user->id)->first();
        $application = $request->applications()->where('caregiver_user_id', $user->id)->first();
        $block = null;
        if ($request->status !== 'open' || ! $request->isAcceptingApplications() || $request->applications()->where('status', 'hired')->exists()) {
            $block = 'This request is no longer accepting caregivers.';
        } elseif ($user->role !== 'caregiver' || $user->access_revoked_at || ! $profile?->isReadyForInvitation() || ! $profile->is_accepting_new_clients) {
            $block = 'This caregiver is not eligible for new invitations.';
        } elseif ($this->optedOut($user)) {
            $block = 'The latest messaging preference is an opt-out.';
        }
        $action = 'invite';
        if ($application || $invitation) {
            $action = ! $application && $invitation->status === 'pending' && ! $invitation->isExpired() ? 'follow_up' : 'review_only';
        }
        $conflicts = $this->conflicts($request, $user->id);
        if ($conflicts) {
            $block ??= 'A recorded care commitment overlaps the requested hours.';
        }
        $phone = TwilioSmsClient::validPhone($user->phone);
        $smsAllowed = $phone && $user->sms_opt_in && (! $user->sms_opt_in_phone || TwilioSmsClient::validPhone($user->sms_opt_in_phone) === $phone) && ! $this->optedOut($user);

        return ['action' => $action, 'blocked_reason' => $block, 'invitation_id' => $invitation?->id,
            'invitation_status' => $invitation?->status, 'application_status' => $application?->status,
            'conflicts' => $conflicts, 'sms_allowed' => (bool) $smsAllowed,
            'sms_note' => $smsAllowed ? null : 'SMS needs a valid phone and current consent.',
            'evidence_version' => [
                'profile' => $profile?->updated_at?->toISOString(),
                'availability' => $profile?->availabilities()->max('updated_at'),
                'certifications' => $profile?->certifications()->max('updated_at'),
                'sms' => DB::table('sms_messages')->where('caregiver_user_id', $user->id)->max('id'),
                'notes' => DB::table('caregiver_crm_activities')->where('caregiver_user_id', $user->id)->max('id'),
                'crm' => DB::table('caregiver_crm_records')->where('caregiver_user_id', $user->id)->max('updated_at'),
                'messages' => DB::table('care_request_messages')->whereIn('care_request_conversation_id',
                    DB::table('care_request_conversations')->where('caregiver_user_id', $user->id)->select('id'))->max('id'),
            ],
            'phone_fingerprint' => hash('sha256', (string) $phone)];
    }

    public function stateFingerprint(array $state): string
    {
        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    public function optedOut(User $user): bool
    {
        $preference = DB::table('sms_messages')->where('caregiver_user_id', $user->id)->where('direction', 'incoming')
            ->whereIn(DB::raw('UPPER(TRIM(body))'), ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'START', 'UNSTOP'])
            ->orderByDesc('created_at')->orderByDesc('id')->value('body');

        return $preference !== null && ! in_array(strtoupper(trim($preference)), ['START', 'UNSTOP'], true);
    }

    public function conflicts(CareRequest $request, int $userId): array
    {
        $windows = $this->windows($request);
        if (! $windows) {
            return [];
        }
        $start = $windows[0][0];
        $end = $windows[array_key_last($windows)][1];
        $bookings = DB::table('care_bookings')->where('caregiver_user_id', $userId)->whereNull('replacement_released_at')
            ->whereNotIn('status', ['cancelled', 'completed', 'reviewed'])->where('scheduled_start_at', '<', $end)->where('scheduled_end_at', '>', $start)->get();
        $shifts = DB::table('continuous_coverage_shifts')->where('assigned_caregiver_user_id', $userId)
            ->whereNotIn('status', ['cancelled', 'completed', 'released'])->where('scheduled_start_at', '<', $end)->where('scheduled_end_at', '>', $start)->get();
        $plans = CarePlan::withoutGlobalScopes()->where('caregiver_user_id', $userId)->whereIn('status', ['active', 'paused', 'payment_attention'])
            ->whereNotNull('starts_on')->get();
        $conflicts = [];
        foreach ($windows as [$from, $until]) {
            foreach (['care_bookings' => $bookings, 'continuous_coverage_shifts' => $shifts] as $table => $rows) {
                foreach ($rows as $row) {
                    if (Carbon::parse($row->scheduled_start_at)->lt($until) && Carbon::parse($row->scheduled_end_at)->gt($from)) {
                        $conflicts[] = $table.':'.$row->id;
                    }
                }
            }
            foreach ($plans as $plan) {
                if (app(CarePlanOccurrenceService::class)->overlapsScheduledRegularOccurrence($plan, $from, $until)) {
                    $conflicts[] = 'care_plans:'.$plan->id;
                }
            }
        }

        return array_values(array_unique($conflicts));
    }

    private function windows(CareRequest $request): array
    {
        if (! $request->isRecurring()) {
            return $request->requested_start_at && $request->requested_end_at ? [[$request->requested_start_at, $request->requested_end_at]] : [];
        }
        $slots = WeeklySchedule::normalize($request->recurring_schedule, $request->recurring_days, $request->recurring_start_time, $request->recurring_end_time);
        $start = $request->recurring_starts_on?->copy()->startOfDay() ?? today();
        if ($start->lt(today())) {
            $start = today();
        }
        $end = $start->copy()->addDays((int) config('care_roster.conflict_days'));
        if ($request->recurring_ends_on?->lt($end)) {
            $end = $request->recurring_ends_on->copy()->endOfDay();
        }
        $windows = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            foreach ($slots as $slot) {
                if ($day->dayOfWeek === $slot['day']) {
                    $windows[] = [$day->copy()->setTimeFromTimeString($slot['start_time']), $day->copy()->setTimeFromTimeString($slot['end_time'])];
                }
            }
        }

        return $windows;
    }

    private function messages(array $conversationIds, array &$coverage): array
    {
        $rows = $this->rows('care_request_messages', DB::table('care_request_messages')->whereIn('care_request_conversation_id', $conversationIds),
            ['id', 'care_request_conversation_id', 'sender_user_id', 'body', 'created_at', 'edited_at'], $coverage);
        $conversations = DB::table('care_request_conversations')->whereIn('id', $conversationIds)->pluck('care_request_id', 'id');
        foreach ($rows as &$row) {
            $row['care_request_id'] = $conversations[$row['care_request_conversation_id']] ?? null;
        }

        return $rows;
    }

    private function rows(string $table, $query, array $fields, array &$coverage): array
    {
        $total = (clone $query)->count();
        $order = in_array('occurred_at', $fields) ? 'occurred_at' : (in_array('created_at', $fields) ? 'created_at' : 'updated_at');
        $rows = $query->select($fields)->orderByDesc($order)->orderByDesc('id')->limit((int) config('care_roster.history_limit'))->get();
        $coverage[$table] = ['included' => $rows->count(), 'total' => $total, 'newest_records_first' => true];

        return $this->sanitize($rows->map(fn ($row) => ['ref' => $table.':'.$row->id] + (array) $row)->all());
    }

    public function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            } elseif (is_string($value) && in_array($key, ['body', 'bio', 'care_experience_notes', 'notes', 'note', 'next_action', 'summary', 'scope_of_work', 'additional_info', 'context', 'cover_note'], true)) {
                $value = preg_replace(['~https?://[^\s]+~i', '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '/(?<!\w)\+?\d[\d ().-]{8,}\d(?!\w)/'], '[contact/link removed]', $value);
                $limit = (int) config('care_roster.text_limit');
                $data[$key] = mb_strlen($value) > $limit ? mb_substr($value, 0, $limit).' [text truncated]' : $value;
            }
        }

        return $data;
    }
}
