<?php

namespace App\Services\ContinuousCoverage;

use App\Models\ContinuousCoverageLaneRequest;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageRosterMember;
use App\Models\ContinuousCoverageShift;
use App\Models\ContinuousCoverageShiftOffer;
use App\Models\ContinuousCoverageShiftTemplate;
use App\Models\User;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Notifications\NotificationChannels;
use App\Support\MarketplaceEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ContinuousCoverageNotificationService
{
    public function __construct(
        private readonly MarketplaceNotificationService $notifications,
        private readonly ContinuousCoverageAccess $access,
        private readonly ContinuousCoveragePricingService $pricing,
    ) {}

    public function teamInvitation(ContinuousCoverageRosterMember $member): void
    {
        $member->loadMissing('plan.family', 'caregiver');
        $this->send(
            $member->caregiver,
            MarketplaceEvent::CONTINUOUS_COVERAGE_TEAM_INVITATION,
            'Join a family-approved Continuous Coverage care team',
            $member->plan->family->name.' approved you to review ongoing coverage opportunities. You decide whether to join and which recurring shifts to accept.',
            $this->caregiverCoverageUrl('offers', 'team-invitation-'.$member->id),
            $member->plan,
            'coverage-team-invitation:'.$member->id,
            $this->careTeamInvitationDetails($member),
        );
    }

    public function applicationReceived(ContinuousCoverageRosterMember $member): void
    {
        $member->loadMissing('plan.family', 'caregiver');
        $this->send(
            $member->plan->family,
            MarketplaceEvent::CONTINUOUS_COVERAGE_APPLICATION_RECEIVED,
            $member->caregiver->name.' applied to join your coverage care team',
            'Review this caregiver’s profile and decide whether your family wants to approve them. Applying does not assign them to any coverage.',
            $this->familyPlanUrl($member->plan, 'team', 'coverage-applicant-'.$member->id),
            $member,
            'coverage-application-received:'.$member->id,
            [
                ['label' => 'Caregiver', 'value' => $member->caregiver->name],
                ['label' => 'Coverage plan', 'value' => $member->plan->title],
                ['label' => 'Next step', 'value' => 'Approve or decline this application.'],
            ],
        );
    }

    public function applicantApproved(ContinuousCoverageRosterMember $member): void
    {
        $member->loadMissing('plan.family', 'caregiver');
        $this->send(
            $member->caregiver,
            MarketplaceEvent::CONTINUOUS_COVERAGE_CAREGIVER_APPROVED,
            'The family approved your care-team application',
            'Review the invitation and choose whether to join. Approval alone does not assign you to a recurring lane or individual shift.',
            $this->caregiverCoverageUrl('offers', 'team-invitation-'.$member->id),
            $member,
            'coverage-applicant-approved:'.$member->id,
            $this->careTeamInvitationDetails($member),
        );
    }

    public function teamAccepted(ContinuousCoverageRosterMember $member): void
    {
        $member->loadMissing('plan.family', 'caregiver');
        $this->send(
            $member->plan->family,
            MarketplaceEvent::CONTINUOUS_COVERAGE_TEAM_ACCEPTED,
            $member->caregiver->name.' joined your approved care team',
            'They can now review recurring lanes and optional backup shifts that match the availability they accepted.',
            $this->familyPlanUrl($member->plan, 'team'),
            $member->plan,
            'coverage-team-accepted:'.$member->id,
            [['label' => 'Caregiver', 'value' => $member->caregiver->name]],
        );
    }

    public function laneOffered(ContinuousCoverageShiftTemplate $template): void
    {
        $template->loadMissing('plan', 'rosterMember.caregiver');
        $this->send(
            $template->rosterMember->caregiver,
            MarketplaceEvent::CONTINUOUS_COVERAGE_LANE_OFFERED,
            'Would you like to accept this recurring coverage?',
            'This recurring lane comes from a family that already approved you. Review the schedule and choose whether it fits your availability.',
            $this->caregiverCoverageUrl('offers', 'lane-offer-'.$template->id),
            $template,
            'coverage-lane-offered:'.$template->id.':'.$template->updated_at?->timestamp,
            array_merge($this->planDetails($template->plan, approximate: true), $this->templateDetails($template)),
        );
    }

    public function laneResponded(ContinuousCoverageShiftTemplate $template, bool $accepted): void
    {
        $template->loadMissing('plan.family', 'rosterMember.caregiver');
        $this->send(
            $template->plan->family,
            $accepted ? MarketplaceEvent::CONTINUOUS_COVERAGE_LANE_ACCEPTED : MarketplaceEvent::CONTINUOUS_COVERAGE_LANE_DECLINED,
            $template->rosterMember->caregiver->name.($accepted ? ' accepted recurring coverage' : ' declined recurring coverage'),
            $accepted
                ? 'The accepted recurring lane is now reflected in your coverage calendar.'
                : 'The lane remains uncovered so you can offer it to another family-approved caregiver.',
            $this->familyPlanUrl($template->plan, 'team'),
            $template,
            'coverage-lane-response:'.$template->id.':'.($accepted ? 'accepted' : 'declined'),
            array_merge([['label' => 'Caregiver', 'value' => $template->rosterMember->caregiver->name]], $this->templateDetails($template)),
        );
    }

    /** @param Collection<int, ContinuousCoverageLaneRequest> $requests */
    public function laneRequested(Collection $requests): void
    {
        if ($requests->isEmpty()) {
            return;
        }
        $requests->loadMissing('plan.family', 'caregiver', 'template', 'rosterMember.caregiver');
        $first = $requests->first();
        $caregiver = $first->caregiver;
        $details = collect([
            ['label' => 'Caregiver', 'value' => $caregiver->name],
            ['label' => 'Coverage plan', 'value' => $first->plan->title],
            ['label' => 'Requested lanes', 'value' => (string) $requests->count()],
        ])->concat($requests->take(6)->map(fn (ContinuousCoverageLaneRequest $request): array => [
            'label' => $this->laneLabel($request->template),
            'value' => $this->duration($request->template->duration_minutes).' each week',
        ]))->values()->all();

        $this->send(
            $first->plan->family,
            MarketplaceEvent::CONTINUOUS_COVERAGE_LANE_REQUESTED,
            $caregiver->name.' requested recurring coverage',
            $requests->count() === 1
                ? 'Review the requested lane. Nothing is assigned until your family approves it.'
                : 'Review the '.$requests->count().' requested lanes. Nothing is assigned until your family approves them.',
            $this->familyPlanUrl($first->plan, 'team', 'coverage-lane-requests'),
            $first,
            'coverage-lane-requested:'.$first->batch_uuid.':family:'.$first->plan->family_user_id,
            $details,
        );
    }

    public function laneRequestApproved(ContinuousCoverageLaneRequest $request): void
    {
        $request->loadMissing('plan.family', 'caregiver', 'template');
        $this->send(
            $request->caregiver,
            MarketplaceEvent::CONTINUOUS_COVERAGE_LANE_REQUEST_APPROVED,
            'Your recurring coverage request was approved',
            'The family approved this lane. Its future visits are now included in your confirmed Continuous Coverage schedule.',
            $this->caregiverCoverageUrl('schedule'),
            $request,
            'coverage-lane-request-approved:'.$request->id.':'.$request->responded_at?->timestamp,
            array_merge($this->planDetails($request->plan, approximate: true), $this->laneRequestDetails($request)),
        );
    }

    public function laneRequestDeclined(ContinuousCoverageLaneRequest $request): void
    {
        $request->loadMissing('plan', 'caregiver', 'template');
        $this->send(
            $request->caregiver,
            MarketplaceEvent::CONTINUOUS_COVERAGE_LANE_REQUEST_DECLINED,
            'The family did not approve this recurring lane',
            'You are not assigned to this lane. Your other care-team membership and confirmed commitments are unchanged.',
            $this->caregiverCoverageUrl('offers'),
            $request,
            'coverage-lane-request-declined:'.$request->id.':'.$request->responded_at?->timestamp,
            array_merge($this->planDetails($request->plan, approximate: true), $this->laneRequestDetails($request)),
        );
    }

    public function laneRequestNotSelected(ContinuousCoverageLaneRequest $request): void
    {
        $request->loadMissing('plan', 'caregiver', 'template');
        $this->send(
            $request->caregiver,
            MarketplaceEvent::CONTINUOUS_COVERAGE_LANE_REQUEST_NOT_SELECTED,
            'This recurring lane was assigned to another caregiver',
            'You are not assigned to this lane. You can review other open lanes available to your approved care team.',
            $this->caregiverCoverageUrl('offers'),
            $request,
            'coverage-lane-request-not-selected:'.$request->id.':'.$request->responded_at?->timestamp,
            array_merge($this->planDetails($request->plan, approximate: true), $this->laneRequestDetails($request)),
        );
    }

    public function laneRequestUnavailable(ContinuousCoverageLaneRequest $request): void
    {
        $request->loadMissing('plan', 'caregiver', 'template');
        $this->send(
            $request->caregiver,
            MarketplaceEvent::CONTINUOUS_COVERAGE_LANE_REQUEST_UNAVAILABLE,
            'This recurring lane is no longer available',
            'The request could not be confirmed, so nothing was added to your schedule. Your care-team membership and other commitments are unchanged.',
            $this->caregiverCoverageUrl('offers'),
            $request,
            'coverage-lane-request-unavailable:'.$request->id.':'.$request->responded_at?->timestamp,
            array_merge($this->planDetails($request->plan, approximate: true), $this->laneRequestDetails($request)),
        );
    }

    public function laneRequestWithdrawn(ContinuousCoverageLaneRequest $request): void
    {
        $request->loadMissing('plan.family', 'caregiver', 'template');
        $this->send(
            $request->plan->family,
            MarketplaceEvent::CONTINUOUS_COVERAGE_LANE_REQUEST_WITHDRAWN,
            $request->caregiver->name.' withdrew a recurring lane request',
            'The lane remains open and no schedule assignment was created.',
            $this->familyPlanUrl($request->plan, 'team', 'coverage-lane-requests'),
            $request,
            'coverage-lane-request-withdrawn:'.$request->id.':'.$request->responded_at?->timestamp,
            array_merge([['label' => 'Caregiver', 'value' => $request->caregiver->name]], $this->laneRequestDetails($request)),
        );
    }

    /** @param Collection<int, User> $caregivers */
    public function planEnded(ContinuousCoveragePlan $plan, Collection $caregivers, bool $deleted = false): void
    {
        $plan->loadMissing('family');
        if (! $this->access->enabled() || ! $this->access->allows($plan->family)) {
            return;
        }

        foreach ($caregivers->unique('id') as $caregiver) {
            $this->notifications->notify(
                recipients: $caregiver,
                eventKey: MarketplaceEvent::CONTINUOUS_COVERAGE_PLAN_ENDED,
                title: $deleted ? 'A Continuous Coverage plan was removed' : 'A Continuous Coverage plan ended',
                body: $deleted
                    ? $plan->family->name.' removed this unbilled coverage plan. You are no longer expected to cover its future shifts.'
                    : $plan->family->name.' ended this coverage plan. No new shifts will be created, and existing care or earnings history remains available.',
                url: route('dashboard'),
                payload: [
                    'email_details' => [
                        ['label' => 'Coverage plan', 'value' => $plan->title],
                        ['label' => 'Family', 'value' => $plan->family->name],
                        ['label' => 'Effective', 'value' => now($plan->timezone)->format('F j, Y · g:i A T')],
                    ],
                    'email_next_steps' => ['No action is needed. You are not expected to attend future shifts from this plan.'],
                ],
                subject: $plan,
                dedupeKey: 'coverage-plan-ended:'.$plan->id.':'.$caregiver->id.':'.($deleted ? 'deleted' : 'ended'),
            );
        }
    }

    public function shiftConfirmed(ContinuousCoverageShift $shift): void
    {
        $shift->loadMissing('plan.family', 'assignedCaregiver');
        $caregiverDetails = $this->shiftDetails($shift, includeEarnings: true);
        $familyDetails = $this->shiftDetails($shift);
        $this->send(
            $shift->assignedCaregiver,
            MarketplaceEvent::CONTINUOUS_COVERAGE_SHIFT_CONFIRMED,
            'Your coverage visit is confirmed',
            'This shift is now ready in the normal LoLo Care visit flow. Review the details before check-in.',
            $this->caregiverCoverageUrl('schedule', 'coverage-shift-'.$shift->id),
            $shift,
            'coverage-shift-confirmed:'.$shift->id.':caregiver:'.$shift->assigned_caregiver_user_id,
            $caregiverDetails,
        );
        $this->send(
            $shift->plan->family,
            MarketplaceEvent::CONTINUOUS_COVERAGE_SHIFT_CONFIRMED,
            'An upcoming coverage visit is confirmed',
            $shift->assignedCaregiver->name.' accepted this coverage and the visit is ready.',
            $this->familyShiftUrl($shift),
            $shift,
            'coverage-shift-confirmed:'.$shift->id.':family:'.$shift->plan->family_user_id,
            array_merge([['label' => 'Caregiver', 'value' => $shift->assignedCaregiver->name]], $familyDetails),
        );
    }

    public function scheduleChanged(ContinuousCoveragePlan $plan, string $effectiveOn): void
    {
        $plan->loadMissing('rosterMembers.caregiver');
        $effective = \Illuminate\Support\Carbon::parse($effectiveOn, $plan->timezone);
        foreach ($plan->rosterMembers->filter->isActive() as $member) {
            $this->send(
                $member->caregiver,
                MarketplaceEvent::CONTINUOUS_COVERAGE_SCHEDULE_CHANGED,
                'A future coverage schedule changed',
                'The family changed coverage beginning '.$effective->format('F j, Y').'. Existing completed visits and payment history were not changed. Review any new recurring offers before committing.',
                $this->caregiverCoverageUrl('commitments'),
                $plan,
                'coverage-schedule-changed:'.$plan->id.':'.$effectiveOn.':caregiver:'.$member->caregiver_user_id,
                [
                    ['label' => 'Effective date', 'value' => $effective->format('F j, Y')],
                    ['label' => 'Coverage plan', 'value' => $plan->title],
                    ['label' => 'Next step', 'value' => 'Review your recurring commitments and any new offers.'],
                ],
            );
        }
    }

    public function shiftReleased(ContinuousCoverageShift $shift, User $caregiver): void
    {
        $shift->loadMissing('plan.family');
        $this->send(
            $shift->plan->family,
            MarketplaceEvent::CONTINUOUS_COVERAGE_SHIFT_RELEASED,
            'A coverage shift needs a replacement',
            $caregiver->name.' released this shift. Family-approved backups are being invited to accept it.',
            $this->familyShiftUrl($shift),
            $shift,
            'coverage-shift-released:'.$shift->id.':'.$shift->released_at?->timestamp,
            $this->shiftDetails($shift),
        );
    }

    public function replacementOffer(ContinuousCoverageShiftOffer $offer): void
    {
        $offer->loadMissing('shift.plan', 'caregiver');
        $this->send(
            $offer->caregiver,
            MarketplaceEvent::CONTINUOUS_COVERAGE_REPLACEMENT_OFFERED,
            'Would you like to cover this backup shift?',
            'This shift matches the backup availability you accepted for this family-approved care team. Accepting is optional.',
            $this->caregiverCoverageUrl('offers', 'replacement-offer-'.$offer->id),
            $offer,
            'coverage-replacement-offer:'.$offer->id.':'.$offer->updated_at?->timestamp,
            array_merge(
                $this->planDetails($offer->shift->plan, approximate: true),
                $this->shiftDetails($offer->shift, exactLocation: false, includeEarnings: true, caregiver: $offer->caregiver),
            ),
        );
    }

    public function replacementAccepted(ContinuousCoverageShiftOffer $offer): void
    {
        $offer->loadMissing('shift.plan.family', 'caregiver');
        $this->send(
            $offer->shift->plan->family,
            MarketplaceEvent::CONTINUOUS_COVERAGE_REPLACEMENT_ACCEPTED,
            $offer->caregiver->name.' accepted the backup offer',
            $offer->shift->plan->replacementRequiresFamilyConfirmation()
                ? 'Review and confirm this family-approved caregiver for the shift.'
                : 'Your preapproved backup rule confirmed the replacement.',
            $this->familyShiftUrl($offer->shift),
            $offer,
            'coverage-replacement-accepted:'.$offer->id,
            array_merge([['label' => 'Caregiver', 'value' => $offer->caregiver->name]], $this->shiftDetails($offer->shift)),
        );
    }

    public function replacementConfirmed(ContinuousCoverageShift $shift): void
    {
        $shift->loadMissing('plan.family', 'assignedCaregiver', 'replacementCase');
        $replacementCycle = $shift->replacementCase?->id ?: 'unassigned';
        $caregiverDetails = $this->shiftDetails($shift, includeEarnings: true, caregiver: $shift->assignedCaregiver);
        $familyDetails = $this->shiftDetails($shift);
        $this->send(
            $shift->assignedCaregiver,
            MarketplaceEvent::CONTINUOUS_COVERAGE_REPLACEMENT_CONFIRMED,
            'Your backup coverage shift is confirmed',
            'You accepted this shift from a family-approved care team. Open it to review the final details.',
            $this->caregiverCoverageUrl('schedule', 'coverage-shift-'.$shift->id),
            $shift,
            'coverage-replacement-confirmed:'.$shift->id.':'.$replacementCycle.':caregiver:'.$shift->assigned_caregiver_user_id,
            $caregiverDetails,
        );
        $this->send(
            $shift->plan->family,
            MarketplaceEvent::CONTINUOUS_COVERAGE_REPLACEMENT_CONFIRMED,
            'Replacement coverage is confirmed',
            $shift->assignedCaregiver->name.' voluntarily accepted this shift from your approved care team.',
            $this->familyShiftUrl($shift),
            $shift,
            'coverage-replacement-confirmed:'.$shift->id.':'.$replacementCycle.':family:'.$shift->plan->family_user_id,
            array_merge([['label' => 'Caregiver', 'value' => $shift->assignedCaregiver->name]], $familyDetails),
        );
    }

    public function replacementNotSelected(ContinuousCoverageShiftOffer $offer): void
    {
        $offer->loadMissing('shift.plan', 'caregiver');
        $this->send(
            $offer->caregiver,
            MarketplaceEvent::CONTINUOUS_COVERAGE_REPLACEMENT_NOT_SELECTED,
            'The family is continuing its replacement search',
            'You are not confirmed for this backup shift and are not expected to attend. Your other commitments are unchanged.',
            $this->caregiverCoverageUrl('schedule'),
            $offer,
            'coverage-replacement-not-selected:'.$offer->id,
            $this->shiftDetails($offer->shift, exactLocation: false),
        );
    }

    public function gapUnresolved(ContinuousCoverageShift $shift): void
    {
        $shift->loadMissing('plan.family', 'replacementCase');
        $replacementCycle = $shift->replacementCase?->id ?: 'unassigned';
        $this->send(
            $shift->plan->family,
            MarketplaceEvent::CONTINUOUS_COVERAGE_GAP_UNRESOLVED,
            'This coverage period is still open',
            'No family-approved backup accepted yet. Review the gap and decide whether to invite additional caregivers to apply.',
            $this->familyShiftUrl($shift),
            $shift,
            'coverage-gap-unresolved:'.$shift->id.':'.$replacementCycle,
            $this->shiftDetails($shift),
        );
        $this->notifyOperationsAboutGap($shift);
    }

    public function shiftReminder(ContinuousCoverageShift $shift): void
    {
        $shift->loadMissing('plan', 'assignedCaregiver');
        $this->send(
            $shift->assignedCaregiver,
            MarketplaceEvent::CONTINUOUS_COVERAGE_SHIFT_REMINDER,
            'Your Continuous Coverage shift starts soon',
            'Open the confirmed visit to review the time, care location, and check-in action.',
            $this->caregiverCoverageUrl('schedule', 'coverage-shift-'.$shift->id),
            $shift,
            'coverage-shift-reminder-24h:'.$shift->id,
            $this->shiftDetails($shift, includeEarnings: true),
        );
    }

    public function paymentAttention(ContinuousCoverageShift $shift): void
    {
        $shift->loadMissing('plan.family');
        $this->send(
            $shift->plan->family,
            MarketplaceEvent::CONTINUOUS_COVERAGE_PAYMENT_ATTENTION,
            'A Continuous Coverage payment needs attention',
            'The caregiver and schedule remain visible. Review your payment method so this confirmed visit can be prepared safely.',
            $this->familyShiftUrl($shift),
            $shift,
            'coverage-payment-attention:'.$shift->id.':'.now()->format('Y-m-d'),
            $this->shiftDetails($shift),
        );
    }

    public function shiftCompleted(ContinuousCoverageShift $shift): void
    {
        $shift->loadMissing('plan.family', 'assignedCaregiver');
        $this->send(
            $shift->plan->family,
            MarketplaceEvent::CONTINUOUS_COVERAGE_SHIFT_COMPLETED,
            'A Continuous Coverage shift was completed',
            $shift->assignedCaregiver?->name.' completed the visit. Open the coverage history to review the recorded time and visit details.',
            $this->familyShiftUrl($shift, 'history'),
            $shift,
            'coverage-shift-completed:'.$shift->id,
            $this->shiftDetails($shift),
        );
    }

    public function earningsFinalized(ContinuousCoverageShift $shift): void
    {
        $shift->loadMissing('plan', 'assignedCaregiver', 'booking.payment');
        if (! $shift->assignedCaregiver || ! $shift->booking?->payment) {
            return;
        }

        $this->send(
            $shift->assignedCaregiver,
            MarketplaceEvent::CONTINUOUS_COVERAGE_EARNINGS_FINALIZED,
            'Your coverage earnings are finalized',
            'The finalized earnings for this completed coverage visit are available in your LoLo Care earnings history.',
            route('caregiver.earnings.index'),
            $shift,
            'coverage-earnings-finalized:'.$shift->id,
            array_merge($this->shiftDetails($shift), [[
                'label' => 'Finalized earnings',
                'value' => '$'.number_format((int) $shift->booking->payment->caregiver_amount_cents / 100, 2),
            ]]),
        );
    }

    /** @param list<array{label:string,value:string}> $details */
    private function send(User $recipient, string $event, string $title, string $body, string $url, Model $subject, string $dedupe, array $details): void
    {
        $plan = $this->planForSubject($subject);
        $plan?->loadMissing('family');
        if (! $this->access->enabled() || ! $plan || ! $this->access->allows($plan->family)) {
            return;
        }

        $this->notifications->notify(
            $recipient,
            $event,
            $title,
            $body,
            $url,
            [
                'email_details' => $details,
                'email_next_steps' => ['Open the linked Continuous Coverage page to review the current status and available action.'],
            ],
            $subject,
            $dedupe,
        );
    }

    private function planForSubject(Model $subject): ?ContinuousCoveragePlan
    {
        return match (true) {
            $subject instanceof ContinuousCoveragePlan => $subject,
            $subject instanceof ContinuousCoverageLaneRequest => $subject->plan,
            $subject instanceof ContinuousCoverageRosterMember => $subject->plan,
            $subject instanceof ContinuousCoverageShiftTemplate => $subject->plan,
            $subject instanceof ContinuousCoverageShift => $subject->plan,
            $subject instanceof ContinuousCoverageShiftOffer => $subject->shift?->plan,
            default => null,
        };
    }

    /** @return list<array{label:string,value:string}> */
    private function planDetails(ContinuousCoveragePlan $plan, bool $approximate = false): array
    {
        $address = (array) $plan->address_snapshot;
        $location = collect([
            data_get($address, 'city'), data_get($address, 'state'), data_get($address, 'zip'),
        ])->filter()->implode(', ');

        $activities = collect((array) $plan->task_snapshot)
            ->pluck('name')
            ->filter()
            ->take(4)
            ->implode(', ');

        return array_values(array_filter([
            ['label' => 'Coverage plan', 'value' => $plan->title],
            ['label' => 'Care for', 'value' => $plan->recipientName()],
            $location !== '' ? ['label' => $approximate ? 'Approximate location' : 'Location', 'value' => $location] : null,
            $activities !== '' ? ['label' => 'Care activities', 'value' => $activities] : null,
        ]));
    }

    /** @return list<array{label:string,value:string}> */
    private function careTeamInvitationDetails(ContinuousCoverageRosterMember $member): array
    {
        $address = (array) $member->plan->address_snapshot;
        $location = collect([
            data_get($address, 'city'), data_get($address, 'state'),
        ])->filter()->implode(', ');
        $activities = collect((array) $member->plan->task_snapshot)
            ->pluck('name')
            ->filter()
            ->take(4)
            ->implode(', ');
        $coverage = $member->plan->coverage_pattern === ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK
            ? '24/7 coverage in '.number_format($member->plan->shift_length_minutes / 60, 1).'-hour shifts'
            : ucfirst($member->plan->coverage_pattern).' recurring coverage';

        return array_values(array_filter([
            ['label' => 'Family', 'value' => $member->plan->family->name],
            $location !== '' ? ['label' => 'Approximate service area', 'value' => $location] : null,
            ['label' => 'Coverage begins', 'value' => $member->plan->starts_on->format('F j, Y').' · '.$member->plan->timezone],
            ['label' => 'Coverage schedule', 'value' => $coverage],
            $activities !== '' ? ['label' => 'Care activities', 'value' => $activities] : null,
            [
                'label' => 'Estimated caregiver earnings',
                'value' => $this->pricing->caregiverEarningsLabel(
                    $member->plan,
                    $member->caregiver,
                    $member->plan->shift_length_minutes,
                ),
            ],
            ['label' => 'Your choice', 'value' => 'Join the care team or decline. No shift is assigned by this invitation.'],
        ]));
    }

    /** @return list<array{label:string,value:string}> */
    private function templateDetails(ContinuousCoverageShiftTemplate $template): array
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        $details = [
            ['label' => 'Recurring day', 'value' => $days[$template->day_of_week] ?? 'Weekly'],
            ['label' => 'Recurring time', 'value' => substr($template->starts_at, 0, 5).' – '.substr($template->ends_at, 0, 5).' '.$template->plan->timezone],
            ['label' => 'Expected duration', 'value' => $this->duration($template->duration_minutes)],
            $template->offer_expires_at
                ? ['label' => 'Respond by', 'value' => $template->offer_expires_at->copy()->setTimezone($template->plan->timezone)->format('F j, Y · g:i A T')]
                : ['label' => 'Respond by', 'value' => 'No deadline recorded'],
        ];

        if ($template->rosterMember?->caregiver) {
            $details[] = [
                'label' => 'Estimated caregiver earnings',
                'value' => $this->pricing->caregiverEarningsLabel(
                    $template->plan,
                    $template->rosterMember->caregiver,
                    $template->duration_minutes,
                ),
            ];
        }

        return $details;
    }

    /** @return list<array{label:string,value:string}> */
    private function laneRequestDetails(ContinuousCoverageLaneRequest $request): array
    {
        return [
            ['label' => 'Recurring lane', 'value' => $this->laneLabel($request->template)],
            ['label' => 'Expected duration', 'value' => $this->duration($request->template->duration_minutes)],
            [
                'label' => 'Estimated caregiver earnings',
                'value' => $this->pricing->caregiverEarningsLabel(
                    $request->plan,
                    $request->caregiver,
                    $request->template->duration_minutes,
                ),
            ],
        ];
    }

    private function laneLabel(ContinuousCoverageShiftTemplate $template): string
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return ($days[$template->day_of_week] ?? 'Weekly').' · '.substr($template->starts_at, 0, 5).'–'.substr($template->ends_at, 0, 5).' '.$template->plan->timezone;
    }

    /** @return list<array{label:string,value:string}> */
    private function shiftDetails(
        ContinuousCoverageShift $shift,
        bool $exactLocation = false,
        bool $includeEarnings = false,
        ?User $caregiver = null,
    ): array {
        $timezone = $shift->plan->timezone;
        $details = [
            ['label' => 'Coverage plan', 'value' => $shift->plan->title],
            ['label' => 'When', 'value' => $shift->scheduled_start_at->copy()->setTimezone($timezone)->format('l, F j, Y · g:i A').' – '.$shift->scheduled_end_at->copy()->setTimezone($timezone)->format('g:i A T')],
            ['label' => 'Expected duration', 'value' => $this->duration($shift->scheduled_minutes)],
        ];
        $activities = collect((array) $shift->plan->task_snapshot)
            ->pluck('name')
            ->filter()
            ->take(4)
            ->implode(', ');
        if ($activities !== '') {
            $details[] = ['label' => 'Care activities', 'value' => $activities];
        }
        $caregiver ??= $shift->assignedCaregiver;
        if ($includeEarnings && $caregiver) {
            $details[] = [
                'label' => 'Estimated caregiver earnings',
                'value' => $this->pricing->caregiverEarningsLabel(
                    $shift->plan,
                    $caregiver,
                    $shift->scheduled_minutes,
                ),
            ];
        }
        $address = (array) $shift->plan->address_snapshot;
        $location = $exactLocation
            ? collect([data_get($address, 'address_line1'), data_get($address, 'city'), data_get($address, 'state'), data_get($address, 'zip')])->filter()->implode(', ')
            : collect([data_get($address, 'city'), data_get($address, 'state'), data_get($address, 'zip')])->filter()->implode(', ');
        if ($location !== '') {
            $details[] = ['label' => $exactLocation ? 'Care location' : 'Approximate location', 'value' => $location];
        }

        return $details;
    }

    private function familyPlanUrl(ContinuousCoveragePlan $plan, string $tab, ?string $fragment = null): string
    {
        $url = route('family.continuous-coverage.show', $plan).'?'.http_build_query(['tab' => $tab]);

        return $fragment ? $url.'#'.$fragment : $url;
    }

    private function familyShiftUrl(ContinuousCoverageShift $shift, string $tab = 'calendar'): string
    {
        $localDate = $shift->scheduled_start_at->copy()->setTimezone($shift->plan->timezone);

        return route('family.continuous-coverage.show', $shift->plan).'?'.http_build_query([
            'tab' => $tab,
            'week' => $localDate->copy()->startOfWeek()->toDateString(),
            'day' => $localDate->toDateString(),
            'selectedShift' => $shift->id,
        ]);
    }

    private function caregiverCoverageUrl(string $tab, ?string $fragment = null): string
    {
        $url = route('caregiver.continuous-coverage.index').'?'.http_build_query(['tab' => $tab]);

        return $fragment ? $url.'#'.$fragment : $url;
    }

    private function notifyOperationsAboutGap(ContinuousCoverageShift $shift): void
    {
        if (! $this->access->enabled() || ! $this->access->allows($shift->plan->family)) {
            return;
        }

        $admins = User::query()->where('role', 'admin')->get();
        if ($admins->isEmpty()) {
            return;
        }

        $timezone = $shift->plan->timezone;
        $this->notifications->notify(
            recipients: $admins,
            eventKey: MarketplaceEvent::CONTINUOUS_COVERAGE_GAP_UNRESOLVED,
            title: 'Continuous Coverage gap needs operational follow-up',
            body: 'No family-approved backup has accepted coverage shift #'.$shift->id.'. The family retains all caregiver decisions.',
            url: route('admin.continuous-coverage.index'),
            payload: [
                'email_details' => [
                    ['label' => 'Coverage plan', 'value' => '#'.$shift->continuous_coverage_plan_id],
                    ['label' => 'Coverage shift', 'value' => '#'.$shift->id],
                    ['label' => 'When', 'value' => $shift->scheduled_start_at->copy()->setTimezone($timezone)->format('F j, Y · g:i A T')],
                    ['label' => 'Next step', 'value' => 'Monitor the unresolved gap and support the family if requested.'],
                ],
            ],
            subject: $shift,
            dedupeKey: 'coverage-gap-unresolved-operations:'.$shift->id.':'.($shift->replacementCase?->id ?: 'unassigned'),
            channelOverrides: [
                NotificationChannels::EMAIL => false,
                NotificationChannels::SMS => false,
                NotificationChannels::PUSH => false,
                NotificationChannels::IN_APP => true,
            ],
        );
    }

    private function duration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return trim(($hours ? $hours.' hour'.($hours === 1 ? '' : 's') : '').($remaining ? ' '.$remaining.' minutes' : ''));
    }
}
