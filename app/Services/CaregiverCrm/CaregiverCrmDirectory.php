<?php

namespace App\Services\CaregiverCrm;

use App\Models\CaregiverProfile;
use App\Models\CareRequestApplication;
use App\Models\CareRequestInvitation;
use App\Services\Screening\CaregiverWorkEligibility;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CaregiverCrmDirectory
{
    public const STAGES = ['new', 'applied', 'hired'];

    /**
     * Counts and stats reflect the same filters as the cards. Each column is
     * independently limited; loading more never loads the entire directory.
     */
    public function board(array $filters = [], array $limits = [], array $offsets = []): array
    {
        $query = $this->filteredQuery($filters);
        $summary = (clone $query)->selectRaw(
            "COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN stage = 'new' THEN 1 ELSE 0 END), 0) AS new_count,
            COALESCE(SUM(CASE WHEN stage = 'applied' THEN 1 ELSE 0 END), 0) AS applied_count,
            COALESCE(SUM(CASE WHEN stage = 'hired' THEN 1 ELSE 0 END), 0) AS hired_count,
            COALESCE(SUM(CASE WHEN phone_spoken_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS called,
            COALESCE(SUM(CASE WHEN next_follow_up_at <= ? THEN 1 ELSE 0 END), 0) AS due,
            COALESCE(SUM(CASE WHEN assigned_admin_id IS NULL THEN 1 ELSE 0 END), 0) AS unassigned",
            [now()]
        )->first();

        $columns = [];
        $counts = [];
        $pageOffsets = [];
        foreach (self::STAGES as $stage) {
            $counts[$stage] = (int) $summary->{$stage.'_count'};
            $limit = max(1, min(100, (int) ($limits[$stage] ?? 8)));
            $lastPageOffset = intdiv(max(0, $counts[$stage] - 1), $limit) * $limit;
            $pageOffsets[$stage] = min($lastPageOffset, max(0, (int) ($offsets[$stage] ?? 0)));
            $columns[$stage] = (clone $query)->where('stage', $stage)
                ->orderByRaw('CASE WHEN next_follow_up_at <= ? THEN 0 ELSE 1 END', [now()])
                ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
                ->orderByRaw('CASE WHEN next_follow_up_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('next_follow_up_at')
                ->orderBy('name')->orderBy('user_id')
                ->offset($pageOffsets[$stage])->limit($limit)
                ->get()->map(fn (object $row): array => $this->row($row));
        }

        return [
            'columns' => $columns,
            'counts' => $counts,
            'offsets' => $pageOffsets,
            'stats' => [
                'total' => (int) $summary->total,
                'called' => (int) $summary->called,
                'due' => (int) $summary->due,
                'unassigned' => (int) $summary->unassigned,
            ],
        ];
    }

    public function find(int $caregiverUserId): ?array
    {
        $row = $this->query()->where('user_id', $caregiverUserId)->first();

        return $row ? $this->row($row) : null;
    }

    private function filteredQuery(array $filters): Builder
    {
        $query = $this->query();
        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 200);
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                foreach (['name', 'email', 'phone', 'zip'] as $field) {
                    $query->orWhere($field, 'like', '%'.$search.'%');
                }
            });
        }

        match ($filters['contact'] ?? 'all') {
            'called' => $query->whereNotNull('phone_spoken_at'),
            'not_called' => $query->whereNull('phone_spoken_at'),
            default => null,
        };
        match ($filters['follow_up'] ?? 'all') {
            'due' => $query->where('next_follow_up_at', '<=', now()),
            'upcoming' => $query->where('next_follow_up_at', '>', now()),
            'none' => $query->whereNull('next_follow_up_at'),
            default => null,
        };
        match ($filters['assigned'] ?? 'all') {
            'me' => $query->where('assigned_admin_id', auth()->id()),
            'unassigned' => $query->whereNull('assigned_admin_id'),
            default => null,
        };

        return $query;
    }

    private function query(): Builder
    {
        abort_unless(auth()->user()?->isAdministrator(), 403);

        $activities = DB::table('caregiver_crm_activities')
            ->where('occurred_at', '<=', now())
            ->selectRaw("caregiver_user_id, MAX(occurred_at) AS last_activity_at,
                MAX(CASE WHEN type = 'call' AND call_outcome = 'connected' THEN occurred_at ELSE NULL END) AS phone_spoken_at")
            ->groupBy('caregiver_user_id');
        $profiles = app(CaregiverWorkEligibility::class)->constrain(CaregiverProfile::query())
            ->join('users as caregiver', 'caregiver.id', '=', 'caregiver_profiles.user_id')
            ->where('caregiver.role', 'caregiver')
            ->leftJoin('caregiver_crm_records as crm', 'crm.caregiver_user_id', '=', 'caregiver.id')
            ->leftJoin('users as assigned', 'assigned.id', '=', 'crm.assigned_admin_id')
            ->leftJoinSub($activities, 'activity', 'activity.caregiver_user_id', '=', 'caregiver.id')
            ->leftJoinSub($this->milestones(), 'milestone', 'milestone.caregiver_user_id', '=', 'caregiver.id')
            ->selectRaw("caregiver.id AS user_id, caregiver_profiles.id AS profile_id,
                caregiver.name, caregiver.email, caregiver.phone, caregiver_profiles.service_area_zip AS zip,
                CASE WHEN COALESCE(milestone.hires_count, 0) > 0 THEN 'hired'
                    WHEN COALESCE(milestone.applications_count, 0) > 0 THEN 'applied' ELSE 'new' END AS stage,
                COALESCE(milestone.applications_count, 0) AS applications_count,
                COALESCE(milestone.hires_count, 0) AS hires_count,
                milestone.first_applied_at, milestone.first_hired_at,
                milestone.last_applied_at, milestone.last_hired_at,
                activity.phone_spoken_at, activity.last_activity_at,
                crm.assigned_admin_id, assigned.name AS assigned_name, COALESCE(crm.priority, 'normal') AS priority,
                crm.notes, crm.next_action, crm.next_follow_up_at, COALESCE(crm.version, 0) AS version");

        return DB::query()->fromSub($profiles->toBase(), 'caregivers');
    }

    /**
     * One engagement is one genuine request, one directly accepted regular-care
     * plan, or one coverage plan for this caregiver. Applications, bookings and
     * repeated generated visits never multiply a single engagement.
     */
    private function milestones(): Builder
    {
        $applications = $this->genuineRequest(DB::table('care_request_applications as application')
            ->join('care_requests as request', 'request.id', '=', 'application.care_request_id'))
            ->selectRaw("application.caregiver_user_id, 'request' AS kind, request.id AS engagement_id,
                application.created_at AS applied_at,
                CASE WHEN application.status = 'hired' THEN 1 ELSE 0 END AS hired,
                NULL AS hired_at, NULL AS latest_hired_at");
        // Resubmissions reuse the application row. Only an attributed submission
        // event can advance its date; updated_at also changes for staff decisions.
        $resubmissions = $this->genuineRequest(DB::table('funnel_events as event')
            ->join('care_request_applications as application', 'application.id', '=', 'event.entity_id')
            ->join('care_requests as request', 'request.id', '=', 'application.care_request_id'))
            ->where('event.event', 'care_request_application_submitted')
            ->where('event.entity_type', CareRequestApplication::class)
            ->whereColumn('event.user_id', 'application.caregiver_user_id')
            ->whereColumn('event.occurred_at', '>=', 'application.created_at')
            ->selectRaw("application.caregiver_user_id, 'request' AS kind, request.id AS engagement_id,
                event.occurred_at AS applied_at, 0 AS hired, NULL AS hired_at, NULL AS latest_hired_at");
        $acceptedInvitations = $this->genuineRequest(DB::table('funnel_events as event')
            ->join('care_request_invitations as invitation', 'invitation.id', '=', 'event.entity_id')
            ->join('care_request_applications as application', 'application.id', '=', 'invitation.care_request_application_id')
            ->join('care_requests as request', 'request.id', '=', 'application.care_request_id'))
            ->where('event.event', 'care_request_invitation_accepted')
            ->where('event.entity_type', CareRequestInvitation::class)
            ->whereColumn('event.user_id', 'application.caregiver_user_id')
            ->whereColumn('invitation.caregiver_user_id', 'application.caregiver_user_id')
            ->whereColumn('invitation.care_request_id', 'application.care_request_id')
            ->whereColumn('event.occurred_at', '>=', 'application.created_at')
            ->selectRaw("application.caregiver_user_id, 'request' AS kind, request.id AS engagement_id,
                event.occurred_at AS applied_at, 0 AS hired, NULL AS hired_at, NULL AS latest_hired_at");
        $bookings = $this->genuineRequest(DB::table('care_bookings as booking')
            ->join('care_requests as request', 'request.id', '=', 'booking.care_request_id'))
            ->selectRaw("booking.caregiver_user_id, 'request' AS kind, request.id AS engagement_id,
                NULL AS applied_at, 1 AS hired, booking.created_at AS hired_at, booking.created_at AS latest_hired_at");

        // Marketplace plans reuse their request; shared care reuses the original
        // request rather than the generated agreement copy. Direct offers count
        // only after activation, even if later paused or ended.
        $planOrigin = DB::connection()->getQueryGrammar()->wrap('plan.metadata->origin');
        $plans = DB::table('care_plans as plan')
            ->leftJoin('care_requests as source', 'source.id', '=', 'plan.source_care_request_id')
            ->leftJoin('care_arrangements as arrangement', 'arrangement.id', '=', 'plan.care_arrangement_id')
            ->whereNotNull('plan.activated_at')
            ->selectRaw("plan.caregiver_user_id,
                CASE WHEN arrangement.source_care_request_id IS NOT NULL OR source.is_system_generated = 0
                    THEN 'request' ELSE 'plan' END AS kind,
                CASE WHEN arrangement.source_care_request_id IS NOT NULL THEN arrangement.source_care_request_id
                    WHEN source.is_system_generated = 0 THEN source.id ELSE plan.id END AS engagement_id,
                NULL AS applied_at, 1 AS hired, plan.activated_at AS hired_at,
                CASE WHEN {$planOrigin} = ? THEN NULL ELSE plan.activated_at END AS latest_hired_at",
                ['existing_customer_migration']
            );

        $coverageApplications = DB::table('continuous_coverage_events as event')
            ->whereIn('event.event_type', ['coverage_application_received', 'recurring_lane_requested'])
            ->whereNotNull('event.actor_user_id')
            ->selectRaw("event.actor_user_id AS caregiver_user_id, 'coverage' AS kind,
                event.continuous_coverage_plan_id AS engagement_id,
                event.happened_at AS applied_at, 0 AS hired, NULL AS hired_at, NULL AS latest_hired_at");
        $coverageApplicants = DB::table('continuous_coverage_roster_members as member')
            ->where('member.status', 'applied')
            ->selectRaw("member.caregiver_user_id, 'coverage' AS kind,
                member.continuous_coverage_plan_id AS engagement_id,
                member.created_at AS applied_at, 0 AS hired, NULL AS hired_at, NULL AS latest_hired_at");
        $coverageLaneRequests = DB::table('continuous_coverage_lane_requests as lane')
            ->join('continuous_coverage_roster_members as member', 'member.id', '=', 'lane.roster_member_id')
            ->join('continuous_coverage_shift_templates as template', 'template.id', '=', 'lane.shift_template_id')
            ->whereColumn('member.caregiver_user_id', 'lane.caregiver_user_id')
            ->whereColumn('member.continuous_coverage_plan_id', 'lane.continuous_coverage_plan_id')
            ->whereColumn('template.continuous_coverage_plan_id', 'lane.continuous_coverage_plan_id')
            ->whereNotNull('lane.requested_at')
            ->selectRaw("lane.caregiver_user_id, 'coverage' AS kind,
                lane.continuous_coverage_plan_id AS engagement_id, lane.requested_at AS applied_at,
                CASE WHEN lane.status = 'approved' AND lane.responded_at IS NOT NULL THEN 1 ELSE 0 END AS hired,
                CASE WHEN lane.status = 'approved' THEN lane.responded_at ELSE NULL END AS hired_at,
                CASE WHEN lane.status = 'approved' THEN lane.responded_at ELSE NULL END AS latest_hired_at");
        // Family approval can hire a caregiver who requested a lane. The actor
        // here is the family; attribute the durable event to the linked request.
        $coverageLaneApprovals = DB::table('continuous_coverage_events as event')
            ->join('continuous_coverage_lane_requests as lane', 'lane.id', '=', 'event.payload->lane_request_id')
            ->where('event.event_type', 'recurring_lane_request_approved')
            ->whereColumn('lane.continuous_coverage_plan_id', 'event.continuous_coverage_plan_id')
            ->whereColumn('lane.caregiver_user_id', 'event.payload->caregiver_user_id')
            ->selectRaw("lane.caregiver_user_id, 'coverage' AS kind,
                lane.continuous_coverage_plan_id AS engagement_id, NULL AS applied_at,
                1 AS hired, event.happened_at AS hired_at, event.happened_at AS latest_hired_at");
        $coverageShifts = DB::table('continuous_coverage_shifts as shift')
            ->whereNotNull('shift.assigned_caregiver_user_id')
            ->whereNotNull('shift.caregiver_accepted_at')
            ->whereNotNull('shift.family_confirmed_at')
            ->whereNotNull('shift.confirmed_at')
            ->selectRaw("shift.assigned_caregiver_user_id AS caregiver_user_id, 'coverage' AS kind,
                shift.continuous_coverage_plan_id AS engagement_id, NULL AS applied_at,
                1 AS hired, shift.confirmed_at AS hired_at, shift.confirmed_at AS latest_hired_at");

        // These events name the caregiver as actor, and remain after a shift
        // clears its current assignment during replacement.
        // Automatic schedule generation also emits shift_confirmed. Its logging
        // time is not a new hire; retain actual lane acceptance dates instead.
        $coverageAccepted = DB::table('continuous_coverage_events')
            ->whereNotNull('actor_user_id')
            ->where(fn (Builder $query) => $query->where('event_type', 'recurring_lane_accepted')
                ->orWhere(fn (Builder $query) => $query->where('event_type', 'shift_confirmed')
                    ->where('payload->source', 'accepted_recurring_lane')))
            ->selectRaw("actor_user_id AS caregiver_user_id, 'coverage' AS kind,
                continuous_coverage_plan_id AS engagement_id, NULL AS applied_at, 1 AS hired,
                MIN(happened_at) AS hired_at, MAX(happened_at) AS latest_hired_at")
            ->groupBy('actor_user_id', 'continuous_coverage_plan_id');
        $coverageConfirmed = DB::table('continuous_coverage_events as event')
            ->where('event.event_type', 'shift_confirmed')
            ->whereNotNull('event.actor_user_id')
            ->selectRaw("event.actor_user_id AS caregiver_user_id, 'coverage' AS kind,
                event.continuous_coverage_plan_id AS engagement_id, NULL AS applied_at,
                1 AS hired, event.happened_at AS hired_at, NULL AS latest_hired_at");
        $coverageReleased = DB::table('continuous_coverage_replacement_cases as replacement')
            ->join('continuous_coverage_shifts as shift', 'shift.id', '=', 'replacement.continuous_coverage_shift_id')
            ->whereNotNull('replacement.original_caregiver_user_id')
            ->selectRaw("replacement.original_caregiver_user_id AS caregiver_user_id, 'coverage' AS kind,
                shift.continuous_coverage_plan_id AS engagement_id, NULL AS applied_at,
                1 AS hired, NULL AS hired_at, NULL AS latest_hired_at");
        $coverageReplacements = DB::table('continuous_coverage_replacement_cases as replacement')
            ->join('continuous_coverage_shift_offers as offer', 'offer.id', '=', 'replacement.winning_offer_id')
            ->join('continuous_coverage_shifts as shift', 'shift.id', '=', 'replacement.continuous_coverage_shift_id')
            ->where('replacement.status', 'resolved')
            ->whereNotNull('replacement.resolved_at')
            ->selectRaw("offer.caregiver_user_id, 'coverage' AS kind,
                shift.continuous_coverage_plan_id AS engagement_id, NULL AS applied_at,
                1 AS hired, replacement.resolved_at AS hired_at, replacement.resolved_at AS latest_hired_at");

        $evidence = $applications->unionAll($resubmissions)->unionAll($acceptedInvitations)
            ->unionAll($bookings)->unionAll($plans)
            ->unionAll($coverageApplications)->unionAll($coverageApplicants)
            ->unionAll($coverageLaneRequests)->unionAll($coverageLaneApprovals)
            ->unionAll($coverageAccepted)
            ->unionAll($coverageShifts)->unionAll($coverageConfirmed)
            ->unionAll($coverageReleased)->unionAll($coverageReplacements);
        $engagements = DB::query()->fromSub($evidence, 'evidence')
            ->selectRaw('caregiver_user_id, kind, engagement_id,
                MIN(applied_at) AS applied_at, MAX(applied_at) AS last_applied_at,
                MAX(hired) AS hired, MIN(hired_at) AS hired_at, MAX(latest_hired_at) AS last_hired_at')
            ->groupBy('caregiver_user_id', 'kind', 'engagement_id');

        return DB::query()->fromSub($engagements, 'engagement')
            ->selectRaw('caregiver_user_id,
                SUM(CASE WHEN applied_at IS NOT NULL THEN 1 ELSE 0 END) AS applications_count,
                SUM(hired) AS hires_count, MIN(applied_at) AS first_applied_at, MIN(hired_at) AS first_hired_at,
                MAX(last_applied_at) AS last_applied_at, MAX(last_hired_at) AS last_hired_at')
            ->groupBy('caregiver_user_id');
    }

    private function genuineRequest(Builder $query): Builder
    {
        return $query->where('request.is_system_generated', false)
            ->where('request.status', '!=', 'draft');
    }

    private function row(object $record): array
    {
        $row = (array) $record;
        foreach (['user_id', 'profile_id', 'applications_count', 'hires_count', 'version'] as $field) {
            $row[$field] = (int) $row[$field];
        }
        $row['assigned_admin_id'] = $row['assigned_admin_id'] === null ? null : (int) $row['assigned_admin_id'];

        return $row;
    }
}
