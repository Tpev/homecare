<?php

namespace App\Livewire\Dashboard;

use App\Models\CareBooking;
use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestInvitation;
use App\Services\Caregiver\CaregiverVisitTimelineService;
use App\Support\CaregiverOnboardingState;
use App\Support\CaregiverPrelaunch;
use App\Support\CaregiverWorkInboxBuilder;
use App\Support\FamilyRebookingOptions;
use App\Support\MarketplaceEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Home extends Component
{
    public function mount(CaregiverOnboardingState $onboardingState): void
    {
        $user = auth()->user();
        if ($user && $user->role === 'sales') {
            $this->redirect(route('admin.crm.index', absolute: false), navigate: true);

            return;
        }

        if ($user && $user->role === 'sdr') {
            $this->redirect(route('sdr.calling', absolute: false), navigate: true);

            return;
        }

        if (! $user || $user->role !== 'caregiver') {
            return;
        }

        $state = $onboardingState->build($user);
        if (($state['onboarding_mode'] ?? false) === true) {
            $this->redirect(route('caregiver.setup.index', absolute: false), navigate: true);
        }
    }

    public function render()
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $mode = $user->role;

        $familyData = [];
        $caregiverData = [];

        if ($mode === 'family') {
            $requestQuery = CareRequest::query()
                ->where('family_user_id', $user->id)
                ->where('is_system_generated', false);
            $openRequestQuery = CareRequest::query()
                ->where('family_user_id', $user->id)
                ->where('is_system_generated', false)
                ->where('status', CareRequest::STATUS_OPEN);

            $readyToReviewCount = (clone $openRequestQuery)
                ->whereHas('applications', function ($query) {
                    $query->whereIn('status', [
                        CareRequestApplication::STATUS_APPLIED,
                        CareRequestApplication::STATUS_SHORTLISTED,
                    ]);
                })
                ->count();

            $waitingApplicantsCount = (clone $openRequestQuery)
                ->whereDoesntHave('applications')
                ->count();

            $activeShiftCount = CareBooking::query()
                ->where('family_user_id', $user->id)
                ->whereNull('care_plan_id')
                ->whereIn('status', [
                    CareBooking::STATUS_SCHEDULED,
                    CareBooking::STATUS_IN_PROGRESS,
                    CareBooking::STATUS_PAUSED,
                    CareBooking::STATUS_COMPLETED,
                ])
                ->count()
                + CarePlan::query()
                    ->where('family_user_id', $user->id)
                    ->whereIn('status', [
                        CarePlan::STATUS_ACTIVE,
                        CarePlan::STATUS_PAYMENT_ATTENTION,
                        CarePlan::STATUS_PAUSED,
                    ])
                    ->count();

            $familyData['stats'] = [
                'open_requests' => (clone $requestQuery)->where('status', CareRequest::STATUS_OPEN)->count(),
                'ready_to_review' => $readyToReviewCount,
                'waiting_for_applicants' => $waitingApplicantsCount,
                'active_shifts' => $activeShiftCount,
                'unread_messages' => $this->unreadMessagesCount($user->id, 'family'),
            ];
            $familyData['billing_ready'] = filled($user->stripe_customer_id);

            $familyData['urgent_open_requests'] = (clone $openRequestQuery)
                ->whereDoesntHave('applications')
                ->where('created_at', '<=', now()->subHours(6))
                ->count();

            $familyData['focus_requests'] = CareRequest::query()
                ->with(['recipient', 'booking'])
                ->withCount([
                    'applications',
                    'applications as pending_candidate_count' => function ($query) {
                        $query->whereIn('status', [
                            CareRequestApplication::STATUS_APPLIED,
                            CareRequestApplication::STATUS_SHORTLISTED,
                        ]);
                    },
                ])
                ->where('family_user_id', $user->id)
                ->where('is_system_generated', false)
                ->whereIn('status', [CareRequest::STATUS_OPEN, CareRequest::STATUS_FILLED])
                ->orderByRaw("CASE WHEN status = '".CareRequest::STATUS_OPEN."' THEN 0 ELSE 1 END")
                ->orderByDesc('updated_at')
                ->limit(8)
                ->get();

            $dashboardBookingRelations = [
                'careRequest.recipient',
                'caregiver.caregiverProfile:id,user_id,profile_photo_path',
                'payment:id,care_booking_id,status,last_error',
            ];
            $timesheetBooking = CareBooking::query()
                ->with($dashboardBookingRelations)
                ->where('family_user_id', $user->id)
                ->whereIn('status', [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED])
                ->whereNull('family_confirmed_at')
                ->orderBy('completed_at')
                ->first();
            $liveBooking = CareBooking::query()
                ->with($dashboardBookingRelations)
                ->where('family_user_id', $user->id)
                ->whereIn('status', [CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED])
                ->orderBy('scheduled_start_at')
                ->first();
            $nextScheduledBooking = CareBooking::query()
                ->with($dashboardBookingRelations)
                ->where('family_user_id', $user->id)
                ->where('status', CareBooking::STATUS_SCHEDULED)
                ->whereScheduledCheckInNotExpired()
                ->orderBy('scheduled_start_at')
                ->first();
            $familyData['active_shifts'] = collect([$timesheetBooking, $liveBooking, $nextScheduledBooking])
                ->filter()
                ->unique('id')
                ->map(function (CareBooking $booking) {
                    $request = $booking->careRequest;
                    if ($request) {
                        $request->setRelation('booking', $booking);
                    }

                    return $request;
                })
                ->filter()
                ->values();

            $familyData['recent_applicants'] = CareRequestApplication::query()
                ->with([
                    'careRequest:id,title,family_user_id,status',
                    'caregiver:id,name',
                    'conversation:id,care_request_application_id',
                ])
                ->whereHas('careRequest', fn ($q) => $q
                    ->where('family_user_id', $user->id)
                    ->where('is_system_generated', false))
                ->latest()
                ->limit(6)
                ->get();

            $familyData['regular_care_plans'] = CarePlan::query()
                ->with([
                    'caregiver:id,name',
                    'nextBooking:id,care_request_id,status,scheduled_start_at,scheduled_end_at',
                    'nextBooking.payment:id,care_booking_id,status,last_error',
                ])
                ->where('family_user_id', $user->id)
                ->latest()
                ->limit(4)
                ->get();

            $familyData['regular_care_sources'] = app(FamilyRebookingOptions::class)->forUser($user, 3);

            $familyData['notification_digest'] = $this->notificationDigestForRole($user, 'family');
        }

        if ($mode === 'caregiver') {
            $workInboxBuilder = app(CaregiverWorkInboxBuilder::class);
            $prelaunchMode = CaregiverPrelaunch::enabled();

            $caregiverData['profile'] = CaregiverProfile::query()
                ->firstOrCreate(
                    ['user_id' => $user->id],
                    ['status' => 'draft']
                );
            $caregiverData['prelaunch_mode'] = $prelaunchMode;
            $caregiverData['prelaunch_message'] = CaregiverPrelaunch::message();

            $caregiverData['stats'] = [
                'applications_total' => CareRequestApplication::query()
                    ->where('caregiver_user_id', $user->id)
                    ->whereHas('careRequest', fn ($query) => $query->where('is_system_generated', false))
                    ->count(),
                'shortlisted' => CareRequestApplication::query()
                    ->where('caregiver_user_id', $user->id)
                    ->whereHas('careRequest', fn ($query) => $query->where('is_system_generated', false))
                    ->where('status', CareRequestApplication::STATUS_SHORTLISTED)
                    ->count(),
                'hired' => CareRequestApplication::query()
                    ->where('caregiver_user_id', $user->id)
                    ->whereHas('careRequest', fn ($query) => $query->where('is_system_generated', false))
                    ->where('status', CareRequestApplication::STATUS_HIRED)
                    ->count(),
                'invitations_pending' => CareRequestInvitation::query()
                    ->where('caregiver_user_id', $user->id)
                    ->where('status', CareRequestInvitation::STATUS_PENDING)
                    ->where(function ($query) {
                        $query->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                    })
                    ->count(),
                'unread_messages' => $this->unreadMessagesCount($user->id, 'caregiver'),
            ];

            $caregiverData['work_inbox_counts'] = $workInboxBuilder->countsForUser($user);
            $caregiverData['work_inbox_preview'] = $workInboxBuilder->buildForUser($user, 'all', 'priority', 5);
            $caregiverData['stats']['needs_response'] = (int) ($caregiverData['work_inbox_counts']['needs_response'] ?? 0);
            $caregiverData['stats']['regular_care_offers'] = CarePlan::query()
                ->where('caregiver_user_id', $user->id)
                ->where('status', CarePlan::STATUS_PENDING_CAREGIVER)
                ->count();
            $caregiverData['stats']['regular_clients'] = CarePlan::query()
                ->where('caregiver_user_id', $user->id)
                ->whereIn('status', [
                    CarePlan::STATUS_ACTIVE,
                    CarePlan::STATUS_PAYMENT_ATTENTION,
                    CarePlan::STATUS_PAUSED,
                ])
                ->count();

            $caregiverData['recent_applications'] = CareRequestApplication::query()
                ->with(['careRequest:id,title,status,city,state,requested_start_at'])
                ->where('caregiver_user_id', $user->id)
                ->whereHas('careRequest', fn ($query) => $query->where('is_system_generated', false))
                ->latest()
                ->limit(8)
                ->get();

            $caregiverData['recent_invitations'] = CareRequestInvitation::query()
                ->with(['careRequest:id,title,request_type,city,state,requested_start_at', 'family:id,name'])
                ->where('caregiver_user_id', $user->id)
                ->latest()
                ->limit(6)
                ->get();

            $caregiverData['active_shift'] = CareBooking::query()
                ->with(['careRequest:id,title,city,state,request_type,requested_start_at,requested_end_at'])
                ->where('caregiver_user_id', $user->id)
                ->whereIn('status', [CareBooking::STATUS_IN_PROGRESS, CareBooking::STATUS_PAUSED])
                ->latest('started_at')
                ->first();

            $caregiverData['next_shift'] = CareBooking::query()
                ->with(['careRequest:id,title,city,state,request_type,requested_start_at,requested_end_at'])
                ->where('caregiver_user_id', $user->id)
                ->where('status', CareBooking::STATUS_SCHEDULED)
                ->whereScheduledCheckInNotExpired()
                ->orderBy('scheduled_start_at')
                ->first();

            $caregiverData['quick_shifts'] = CareBooking::query()
                ->with(['careRequest:id,title,city,state,request_type,requested_start_at,requested_end_at'])
                ->where('caregiver_user_id', $user->id)
                ->whereIn('status', [
                    CareBooking::STATUS_IN_PROGRESS,
                    CareBooking::STATUS_PAUSED,
                    CareBooking::STATUS_SCHEDULED,
                    CareBooking::STATUS_COMPLETED,
                ])
                ->where(function ($query): void {
                    $query->where('status', '!=', CareBooking::STATUS_SCHEDULED)
                        ->orWhere(function ($scheduledQuery): void {
                            $scheduledQuery
                                ->where('status', CareBooking::STATUS_SCHEDULED)
                                ->whereScheduledCheckInNotExpired();
                        });
                })
                ->orderByRaw("CASE status
                    WHEN 'in_progress' THEN 0
                    WHEN 'paused' THEN 1
                    WHEN 'scheduled' THEN 2
                    WHEN 'completed' THEN 3
                    ELSE 4 END")
                ->orderBy('scheduled_start_at')
                ->limit(3)
                ->get();

            $visitTimeline = app(CaregiverVisitTimelineService::class);
            $caregiverData['next_visit'] = $visitTimeline->nextScheduled($user);
            $caregiverData['quick_visits'] = $visitTimeline->quickAccess($user, 3);

            $profile = $caregiverData['profile'];
            $identityComplete = $profile->hasIdentityVerifiedBadge();
            $tasksComplete = $profile->skills()->exists();
            $insuranceComplete = $profile->insuranceIsComplete();
            $videoComplete = filled($profile->intro_video_path);
            $payoutSetupComplete = $profile->stripeConnectIsReady();

            $basicsComplete = filled($profile->bio)
                && ! is_null($profile->years_experience)
                && filled($profile->service_area_zip)
                && ! is_null($profile->service_radius_miles)
                && $profile->languages()->exists()
                && $profile->availabilities()->exists();

            $caregiverData['setup_cards'] = [
                [
                    'title' => 'Complete profile basics',
                    'description' => 'Bio, location, languages, and precise availability.',
                    'route' => route('caregiver.onboarding'),
                    'cta' => 'Open basics',
                    'required' => true,
                    'done' => $basicsComplete,
                ],
                [
                    'title' => 'Identity verification',
                    'description' => 'Required to be review-eligible and searchable.',
                    'route' => route('caregiver.verification.show'),
                    'cta' => 'Start KYC',
                    'required' => true,
                    'done' => $identityComplete,
                ],
                [
                    'title' => 'Task comfort selection',
                    'description' => 'Pick exactly which care tasks you are comfortable doing.',
                    'route' => route('caregiver.tasks.edit'),
                    'cta' => 'Select tasks',
                    'required' => true,
                    'done' => $tasksComplete,
                ],
                [
                    'title' => 'Payout setup',
                    'description' => 'Connect Stripe so completed shifts can be paid out to you.',
                    'route' => route('caregiver.payouts.connect.show'),
                    'cta' => 'Connect payouts',
                    'required' => false,
                    'done' => $payoutSetupComplete,
                ],
                [
                    'title' => 'Insurance setup',
                    'description' => 'Tell families whether you are insured and upload proof if yes.',
                    'route' => route('caregiver.insurance.edit'),
                    'cta' => 'Set insurance',
                    'required' => false,
                    'done' => $insuranceComplete,
                ],
                [
                    'title' => 'Intro video',
                    'description' => 'Optional, but usually improves profile conversion.',
                    'route' => route('caregiver.video.edit'),
                    'cta' => 'Upload video',
                    'required' => false,
                    'done' => $videoComplete,
                ],
            ];

            $caregiverData['setup_cards'] = collect($caregiverData['setup_cards'])
                ->filter(fn ($card) => ! $card['done'])
                ->values()
                ->all();

            $requiredCards = collect($caregiverData['setup_cards'])->filter(fn ($card) => $card['required']);
            $requiredTotal = 3;
            $requiredCompleted = $requiredTotal - $requiredCards->count();

            $caregiverData['required_setup_total'] = $requiredTotal;
            $caregiverData['required_setup_completed'] = $requiredCompleted;
            $caregiverData['ready_for_review'] = $requiredCompleted >= $requiredTotal;
            $caregiverData['can_submit_for_review'] = $caregiverData['ready_for_review']
                && in_array((string) $profile->status, ['draft', 'suspended'], true);
            $caregiverData['notification_digest'] = $this->notificationDigestForRole($user, 'caregiver');
        }

        return view('livewire.dashboard.home', compact('mode', 'familyData', 'caregiverData'));
    }

    private function unreadMessagesCount(int $userId, string $role): int
    {
        if (! Schema::hasTable('care_request_conversations')) {
            return 0;
        }

        if ($role === 'family') {
            return CareRequestConversation::query()
                ->where('family_user_id', $userId)
                ->whereNotNull('last_message_at')
                ->where('last_message_sender_id', '!=', $userId)
                ->where(function ($query) {
                    $query->whereNull('family_last_read_at')
                        ->orWhereColumn('last_message_at', '>', 'family_last_read_at');
                })
                ->count();
        }

        return CareRequestConversation::query()
            ->where('caregiver_user_id', $userId)
            ->whereNotNull('last_message_at')
            ->where('last_message_sender_id', '!=', $userId)
            ->where(function ($query) {
                $query->whereNull('caregiver_last_read_at')
                    ->orWhereColumn('last_message_at', '>', 'caregiver_last_read_at');
            })
            ->count();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function notificationDigestForRole($user, string $role): Collection
    {
        if (! Schema::hasTable('notifications')) {
            return collect();
        }

        $eventMap = $this->notificationEventMapForRole($role);
        $eventKeys = array_keys($eventMap);

        return $user->unreadNotifications()
            ->whereIn('data->event_key', $eventKeys)
            ->latest()
            ->limit(3)
            ->get()
            ->map(function ($notification) use ($eventMap): array {
                $eventKey = (string) data_get($notification->data, 'event_key', '');
                $eventMeta = $eventMap[$eventKey] ?? ['label' => 'Update', 'tone' => 'neutral'];

                return [
                    'id' => (string) $notification->id,
                    'event_key' => $eventKey,
                    'event_label' => (string) ($eventMeta['label'] ?? 'Update'),
                    'tone' => (string) ($eventMeta['tone'] ?? 'neutral'),
                    'title' => (string) data_get($notification->data, 'title', 'Notification'),
                    'body' => (string) data_get($notification->data, 'body', ''),
                    'url' => (string) data_get($notification->data, 'url', ''),
                    'created_at' => $notification->created_at,
                ];
            });
    }

    /**
     * @return array<string, array{label: string, tone: string}>
     */
    private function notificationEventMapForRole(string $role): array
    {
        if ($role === 'family') {
            return [
                MarketplaceEvent::FAMILY_WELCOME => ['label' => 'Welcome', 'tone' => 'info'],
                MarketplaceEvent::NEW_APPLICANT => ['label' => 'Applicant', 'tone' => 'info'],
                MarketplaceEvent::INVITE_ACCEPTED => ['label' => 'Invite accepted', 'tone' => 'success'],
                MarketplaceEvent::INVITE_DECLINED => ['label' => 'Invite declined', 'tone' => 'warning'],
                MarketplaceEvent::HIRE_CONFIRMED => ['label' => 'Hire confirmed', 'tone' => 'success'],
                MarketplaceEvent::SHIFT_CANCELLED => ['label' => 'Cancelled', 'tone' => 'warning'],
                MarketplaceEvent::SHIFT_STARTING_SOON => ['label' => 'Shift soon', 'tone' => 'info'],
                MarketplaceEvent::SHIFT_REMINDER_24H => ['label' => 'Visit tomorrow', 'tone' => 'info'],
                MarketplaceEvent::SHIFT_STARTED => ['label' => 'Shift started', 'tone' => 'info'],
                MarketplaceEvent::SHIFT_COMPLETED => ['label' => 'Shift completed', 'tone' => 'success'],
                MarketplaceEvent::TIMESHEET_AUTO_APPROVED => ['label' => 'Timesheet approved', 'tone' => 'success'],
                MarketplaceEvent::REVIEW_RECEIVED => ['label' => 'Review received', 'tone' => 'info'],
                MarketplaceEvent::MESSAGE_RECEIVED => ['label' => 'Message', 'tone' => 'neutral'],
                MarketplaceEvent::BOOKING_CHANGE_REQUESTED => ['label' => 'Visit change', 'tone' => 'info'],
                MarketplaceEvent::BOOKING_CHANGE_ACCEPTED => ['label' => 'Visit change accepted', 'tone' => 'success'],
                MarketplaceEvent::BOOKING_CHANGE_DECLINED => ['label' => 'Visit change declined', 'tone' => 'warning'],
                MarketplaceEvent::SUPPORT_TICKET_REPLY => ['label' => 'Support reply', 'tone' => 'info'],
                MarketplaceEvent::PAYMENT_ACTION_REQUIRED => ['label' => 'Payment action', 'tone' => 'warning'],
                MarketplaceEvent::PAYMENT_AUTHORIZATION_FAILED => ['label' => 'Payment issue', 'tone' => 'warning'],
                MarketplaceEvent::PAYMENT_AUTHORIZED => ['label' => 'Payment secured', 'tone' => 'success'],
                MarketplaceEvent::REGULAR_CARE_ACCEPTED => ['label' => 'Regular care', 'tone' => 'success'],
                MarketplaceEvent::REGULAR_CARE_COUNTERED => ['label' => 'Regular care', 'tone' => 'warning'],
                MarketplaceEvent::REGULAR_CARE_DECLINED => ['label' => 'Regular care', 'tone' => 'warning'],
                MarketplaceEvent::REGULAR_CARE_PAYMENT_ATTENTION => ['label' => 'Payment issue', 'tone' => 'warning'],
                MarketplaceEvent::REGULAR_CARE_SCHEDULE_CHANGE_ACCEPTED => ['label' => 'Schedule accepted', 'tone' => 'success'],
                MarketplaceEvent::REGULAR_CARE_SCHEDULE_CHANGE_DECLINED => ['label' => 'Schedule declined', 'tone' => 'warning'],
                MarketplaceEvent::REGULAR_CARE_PAUSED => ['label' => 'Regular care paused', 'tone' => 'warning'],
                MarketplaceEvent::REGULAR_CARE_RESUMED => ['label' => 'Regular care resumed', 'tone' => 'success'],
                MarketplaceEvent::REGULAR_CARE_ENDED => ['label' => 'Regular care ended', 'tone' => 'warning'],
                MarketplaceEvent::TIME_CORRECTION_REQUESTED => ['label' => 'Time review', 'tone' => 'info'],
                MarketplaceEvent::TIME_CORRECTION_CHANGES_REQUESTED => ['label' => 'Time changes', 'tone' => 'warning'],
                MarketplaceEvent::TIME_CORRECTION_RESUBMITTED => ['label' => 'Updated time', 'tone' => 'info'],
                MarketplaceEvent::TIME_CORRECTION_APPROVED => ['label' => 'Time approved', 'tone' => 'success'],
                MarketplaceEvent::TIME_CORRECTION_PAYMENT_ACTION_REQUIRED => ['label' => 'Payment action', 'tone' => 'warning'],
                MarketplaceEvent::TIME_CORRECTION_APPLIED => ['label' => 'Time updated', 'tone' => 'success'],
                MarketplaceEvent::TIME_CORRECTION_ESCALATED => ['label' => 'LoLo Care review', 'tone' => 'warning'],
                MarketplaceEvent::TIME_CORRECTION_WITHDRAWN => ['label' => 'Time withdrawn', 'tone' => 'neutral'],
            ];
        }

        return [
            MarketplaceEvent::CAREGIVER_WELCOME => ['label' => 'Welcome', 'tone' => 'info'],
            MarketplaceEvent::CAREGIVER_ONBOARDING_REMINDER_24H => ['label' => 'Setup reminder', 'tone' => 'info'],
            MarketplaceEvent::INVITATION_RECEIVED => ['label' => 'Care invitation', 'tone' => 'info'],
            MarketplaceEvent::MATCHING_REQUEST_REMINDER => ['label' => 'New invite', 'tone' => 'info'],
            MarketplaceEvent::APPLICATION_SUBMITTED => ['label' => 'Applied', 'tone' => 'success'],
            MarketplaceEvent::CARE_REQUEST_WITHDRAWN => ['label' => 'Withdrawn', 'tone' => 'warning'],
            MarketplaceEvent::CAREGIVER_HIRED => ['label' => 'Hired', 'tone' => 'success'],
            MarketplaceEvent::SHIFT_CANCELLED => ['label' => 'Cancelled', 'tone' => 'warning'],
            MarketplaceEvent::SHIFT_STARTING_SOON => ['label' => 'Shift soon', 'tone' => 'info'],
            MarketplaceEvent::SHIFT_REMINDER_24H => ['label' => 'Shift tomorrow', 'tone' => 'info'],
            MarketplaceEvent::SHIFT_STARTED => ['label' => 'Shift started', 'tone' => 'info'],
            MarketplaceEvent::SHIFT_COMPLETED => ['label' => 'Shift completed', 'tone' => 'success'],
            MarketplaceEvent::TIMESHEET_AUTO_APPROVED => ['label' => 'Timesheet approved', 'tone' => 'success'],
            MarketplaceEvent::MESSAGE_RECEIVED => ['label' => 'Message', 'tone' => 'neutral'],
            MarketplaceEvent::BOOKING_CHANGE_REQUESTED => ['label' => 'Visit change', 'tone' => 'info'],
            MarketplaceEvent::BOOKING_CHANGE_ACCEPTED => ['label' => 'Visit change accepted', 'tone' => 'success'],
            MarketplaceEvent::BOOKING_CHANGE_DECLINED => ['label' => 'Visit change declined', 'tone' => 'warning'],
            MarketplaceEvent::SUPPORT_TICKET_REPLY => ['label' => 'Support reply', 'tone' => 'info'],
            MarketplaceEvent::REVIEW_RECEIVED => ['label' => 'Review received', 'tone' => 'warning'],
            MarketplaceEvent::PAYOUT_TRANSFERRED => ['label' => 'Payout sent', 'tone' => 'success'],
            MarketplaceEvent::PAYOUT_TRANSFER_FAILED => ['label' => 'Payout issue', 'tone' => 'warning'],
            MarketplaceEvent::REGULAR_CARE_OFFERED => ['label' => 'Regular care', 'tone' => 'info'],
            MarketplaceEvent::REGULAR_CARE_ACCEPTED => ['label' => 'Regular care', 'tone' => 'success'],
            MarketplaceEvent::REGULAR_CARE_ENDED => ['label' => 'Regular care', 'tone' => 'warning'],
            MarketplaceEvent::REGULAR_CARE_SCHEDULE_CHANGE_REQUESTED => ['label' => 'Schedule request', 'tone' => 'info'],
            MarketplaceEvent::REGULAR_CARE_EXTRA_VISIT_REQUESTED => ['label' => 'Extra visit', 'tone' => 'info'],
            MarketplaceEvent::REGULAR_CARE_VISIT_SKIPPED => ['label' => 'Visit skipped', 'tone' => 'warning'],
            MarketplaceEvent::REGULAR_CARE_PAUSED => ['label' => 'Regular care paused', 'tone' => 'warning'],
            MarketplaceEvent::REGULAR_CARE_RESUMED => ['label' => 'Regular care resumed', 'tone' => 'success'],
            MarketplaceEvent::TIME_CORRECTION_REQUESTED => ['label' => 'Time requested', 'tone' => 'info'],
            MarketplaceEvent::TIME_CORRECTION_CHANGES_REQUESTED => ['label' => 'Time changes', 'tone' => 'warning'],
            MarketplaceEvent::TIME_CORRECTION_RESUBMITTED => ['label' => 'Time updated', 'tone' => 'info'],
            MarketplaceEvent::TIME_CORRECTION_APPROVED => ['label' => 'Time approved', 'tone' => 'success'],
            MarketplaceEvent::TIME_CORRECTION_PAYMENT_ACTION_REQUIRED => ['label' => 'Payment action', 'tone' => 'warning'],
            MarketplaceEvent::TIME_CORRECTION_APPLIED => ['label' => 'Time finalized', 'tone' => 'success'],
            MarketplaceEvent::TIME_CORRECTION_ESCALATED => ['label' => 'LoLo Care review', 'tone' => 'warning'],
            MarketplaceEvent::TIME_CORRECTION_WITHDRAWN => ['label' => 'Time withdrawn', 'tone' => 'neutral'],
        ];
    }
}
