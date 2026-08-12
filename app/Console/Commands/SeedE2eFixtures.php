<?php

namespace App\Console\Commands;

use App\Models\CareBooking;
use App\Models\CareBookingTimeCorrection;
use App\Models\CaregiverCertification;
use App\Models\CaregiverCertificationType;
use App\Models\CaregiverExperienceType;
use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareTask;
use App\Models\FamilyAccountInvitation;
use App\Models\FamilyAccountMember;
use App\Models\Language;
use App\Models\Skill;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Booking\BookingTrustService;
use App\Services\Caregiver\CaregiverBackgroundService;
use App\Services\FamilyAccounts\FamilyAccountProvisioner;
use App\Services\RegularCare\CarePlanHealthService;
use App\Services\RegularCare\CarePlanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SeedE2eFixtures extends Command
{
    protected $signature = 'homecare:e2e-seed';

    protected $description = 'Seed deterministic fixtures for Playwright E2E scenarios.';

    public function handle(): int
    {
        $this->seedCatalog();

        $family = User::query()->create([
            'name' => 'E2E Family',
            'email' => 'family.e2e@example.com',
            'role' => 'family',
            'phone' => '+1 919 555 0100',
            'city' => 'Raleigh',
            'state' => 'NC',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $familyAccount = app(FamilyAccountProvisioner::class)->provisionOwner($family, 'e2e_fixture');

        $familyMember = User::query()->create([
            'name' => 'E2E Family Member',
            'email' => 'family.member.e2e@example.com',
            'role' => 'family',
            'phone' => '+1 919 555 0104',
            'city' => 'Raleigh',
            'state' => 'NC',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $familyAccount->memberships()->create([
            'user_id' => $familyMember->id,
            'access_level' => FamilyAccountMember::ACCESS_MEMBER,
            'status' => FamilyAccountMember::STATUS_ACTIVE,
            'joined_at' => now()->subDay(),
        ]);

        $eligibleFamily = User::query()->create([
            'name' => 'E2E Eligible Relative',
            'email' => 'family.eligible.e2e@example.com',
            'role' => 'family',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        app(FamilyAccountProvisioner::class)->provisionOwner($eligibleFamily, 'e2e_empty_existing_account');
        $removedFamily = User::query()->create([
            'name' => 'E2E Removed Relative',
            'email' => 'family.removed.e2e@example.com',
            'role' => 'family',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $familyAccount->memberships()->create([
            'user_id' => $removedFamily->id,
            'access_level' => FamilyAccountMember::ACCESS_MEMBER,
            'status' => FamilyAccountMember::STATUS_REMOVED,
            'joined_at' => now()->subWeek(),
            'ended_at' => now()->subDay(),
            'ended_by_user_id' => $family->id,
        ]);

        $newInviteToken = str_repeat('a', 64);
        $existingInviteToken = str_repeat('b', 64);
        foreach ([
            ['email' => 'family.invited.e2e@example.com', 'token' => $newInviteToken],
            ['email' => $eligibleFamily->email, 'token' => $existingInviteToken],
        ] as $fixtureInvitation) {
            FamilyAccountInvitation::query()->create([
                'family_account_id' => $familyAccount->id,
                'invited_by_user_id' => $family->id,
                'email_normalized' => $fixtureInvitation['email'],
                'token_hash' => hash('sha256', $fixtureInvitation['token']),
                'expires_at' => now()->addDays(7),
            ]);
        }

        $admin = User::query()->create([
            'name' => 'E2E Admin',
            'email' => 'test@test.com',
            'role' => 'admin',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $readyCaregiver = User::query()->create([
            'name' => 'E2E Ready Caregiver',
            'email' => 'caregiver.ready.e2e@example.com',
            'role' => 'caregiver',
            'phone' => '+1 919 555 0101',
            'city' => 'Raleigh',
            'state' => 'NC',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'onboarding_completed_at' => now(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        User::query()->create([
            'name' => 'E2E Mobile Visual Caregiver',
            'email' => 'caregiver.mobile-visual.e2e@example.com',
            'role' => 'caregiver',
            'phone' => '+1 919 555 0107',
            'city' => 'Raleigh',
            'state' => 'NC',
            'date_of_birth' => now()->subYears(29)->toDateString(),
            'onboarding_completed_at' => now(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $newCaregiver = User::query()->create([
            'name' => 'E2E New Caregiver',
            'email' => 'caregiver.new.e2e@example.com',
            'role' => 'caregiver',
            'phone' => '+1 919 555 0102',
            'city' => 'Raleigh',
            'state' => 'NC',
            'date_of_birth' => now()->subYears(28)->toDateString(),
            'onboarding_completed_at' => null,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $underReviewCaregiver = User::query()->create([
            'name' => 'E2E Under Review Caregiver',
            'email' => 'caregiver.review.e2e@example.com',
            'role' => 'caregiver',
            'phone' => '+1 919 555 0103',
            'city' => 'Raleigh',
            'state' => 'NC',
            'date_of_birth' => now()->subYears(33)->toDateString(),
            'onboarding_completed_at' => now(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $backgroundReviewCaregiver = User::query()->create([
            'name' => 'E2E Background Review Caregiver',
            'email' => 'caregiver.background.review.e2e@example.com',
            'role' => 'caregiver',
            'phone' => '+1 919 555 0105',
            'city' => 'Raleigh',
            'state' => 'NC',
            'date_of_birth' => now()->subYears(34)->toDateString(),
            'onboarding_completed_at' => now(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $marketplaceCaregiver = User::query()->create([
            'name' => 'E2E Marketplace Caregiver',
            'email' => 'caregiver.marketplace.e2e@example.com',
            'role' => 'caregiver',
            'phone' => '+1 919 555 0106',
            'city' => 'Raleigh',
            'state' => 'NC',
            'date_of_birth' => now()->subYears(31)->toDateString(),
            'onboarding_completed_at' => now(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $readyProfile = $this->makeMarketplaceReadyProfile($readyCaregiver, 'e2e-ready-caregiver');
        $newProfile = $this->makeDraftOnboardingProfile($newCaregiver, 'e2e-new-caregiver');
        $underReviewProfile = $this->makeUnderReviewProfile($underReviewCaregiver, 'e2e-under-review-caregiver');
        $backgroundReviewProfile = $this->makeUnderReviewProfile($backgroundReviewCaregiver, 'e2e-background-review-caregiver');
        $marketplaceProfile = $this->makeMarketplaceReadyProfile($marketplaceCaregiver, 'e2e-marketplace-caregiver');

        $this->attachCatalogToProfile($readyProfile);
        $this->attachCatalogToProfile($newProfile);
        $this->attachCatalogToProfile($underReviewProfile);
        $this->attachCatalogToProfile($backgroundReviewProfile);
        $this->attachCatalogToProfile($marketplaceProfile);
        $this->seedCareBackground($readyProfile, includeExpiredCredential: true);
        $this->seedCareBackground($underReviewProfile);
        $this->seedCareBackground($backgroundReviewProfile);
        $this->seedCareBackground(
            $marketplaceProfile,
            verificationStatus: CaregiverCertification::STATUS_VERIFIED,
        );

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'E2E Open Request - Raleigh Morning Support',
            'additional_info' => 'Family needs reliable non-medical support during a medical appointment.',
            'scope_of_work' => 'Companionship, meal prep support, and safety supervision.',
            'time_expectations' => 'Arrive 10 minutes early and follow routine.',
            'home_access_notes' => 'Lockbox code shared in chat after hire.',
            'preferred_response_hours' => 12,
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDays(2)->setTime(9, 0),
            'requested_end_at' => now()->addDays(2)->setTime(12, 0),
            'address_line1' => '123 E2E Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);

        $request->recipient()->create([
            'full_name' => 'E2E Recipient',
            'relationship_to_family' => 'Mother',
            'care_notes' => 'Needs companionship and medication reminders.',
        ]);

        $taskIds = CareTask::query()
            ->whereIn('name', ['Companionship', 'Meal preparation', 'Medication reminders'])
            ->pluck('id')
            ->all();

        $request->tasks()->sync(collect($taskIds)->mapWithKeys(
            fn (int $id) => [$id => ['task_note' => null]]
        )->all());

        $this->seedInvitationVisualRequest($family, $taskIds);
        $this->seedMissedRegularVisit($family, $readyCaregiver, $taskIds);
        $this->seedTimeCorrectionState($family, $readyCaregiver, $taskIds, CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED, 'E2E Time Correction - Payment Action');
        $this->seedTimeCorrectionState($family, $readyCaregiver, $taskIds, CareBookingTimeCorrection::STATUS_APPROVED_ADMIN_REQUIRED, 'E2E Time Correction - Admin Required');
        $this->seedTimeCorrectionState($family, $readyCaregiver, $taskIds, CareBookingTimeCorrection::STATUS_ESCALATED, 'E2E Time Correction - Escalated');
        $this->seedRegularCare($family, $readyCaregiver, $taskIds);
        $this->seedMarketplaceCoreRequest($family, $taskIds);

        // email_verified_at is intentionally not mass assignable on User.
        User::query()
            ->where(fn ($query) => $query->where('email', 'like', '%.e2e@example.com')->orWhere('email', 'test@test.com'))
            ->update(['email_verified_at' => now()]);

        $historyTicket = SupportTicket::withoutEvents(fn () => SupportTicket::query()->create([
            'opener_user_id' => $marketplaceCaregiver->id,
            'source' => SupportTicket::SOURCE_CHAT_WIDGET,
            'origin_route' => 'caregiver.work-inbox.index',
            'origin_path' => '/caregiver/work-inbox',
            'category' => 'general',
            'status' => SupportTicket::STATUS_RESOLVED,
            'priority' => 'normal',
            'subject' => 'Chat: E2E long support conversation',
            'description' => 'This seeded conversation verifies long mobile support history.',
            'resolved_at' => now()->subMinute(),
            'last_public_message_at' => now()->subMinute(),
            'last_public_message_sender_id' => $marketplaceCaregiver->id,
            'opener_last_read_at' => now(),
        ]));
        foreach (range(1, 42) as $index) {
            SupportTicketMessage::query()->create([
                'support_ticket_id' => $historyTicket->id,
                'sender_user_id' => $index % 2 === 0 ? $marketplaceCaregiver->id : $admin->id,
                'kind' => SupportTicketMessage::KIND_PUBLIC,
                'body' => $index === 1
                    ? 'E2E oldest mobile support message'
                    : 'E2E mobile support history message '.$index.' with wrapping text for responsive verification.',
                'client_message_id' => (string) Str::uuid(),
                'created_at' => now()->subMinutes(44 - $index),
                'updated_at' => now()->subMinutes(44 - $index),
            ]);
        }

        $this->line('E2E fixtures ready');
        $this->table(['Account', 'Email', 'Password'], [
            ['Family', 'family.e2e@example.com', 'password'],
            ['Family member', 'family.member.e2e@example.com', 'password'],
            ['Eligible relative', 'family.eligible.e2e@example.com', 'password'],
            ['Removed relative', 'family.removed.e2e@example.com', 'password'],
            ['Admin', 'test@test.com', 'password'],
            ['Caregiver (ready)', 'caregiver.ready.e2e@example.com', 'password'],
            ['Caregiver (new)', 'caregiver.new.e2e@example.com', 'password'],
            ['Caregiver (under review)', 'caregiver.review.e2e@example.com', 'password'],
            ['Caregiver (background review)', 'caregiver.background.review.e2e@example.com', 'password'],
            ['Caregiver (marketplace)', 'caregiver.marketplace.e2e@example.com', 'password'],
        ]);
        $this->line('Primary request ID: '.$request->id);
        $this->line('New-user invitation: '.route('family.invitations.show', $newInviteToken));
        $this->line('Existing-user invitation: '.route('family.invitations.show', $existingInviteToken));

        return self::SUCCESS;
    }

    /** @param list<int> $taskIds */
    private function seedMarketplaceCoreRequest(User $family, array $taskIds): void
    {
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'E2E Marketplace Core Request',
            'additional_info' => 'Isolated request for the complete marketplace browser workflow.',
            'scope_of_work' => 'Companionship, meal preparation, and safety supervision.',
            'time_expectations' => 'Please arrive 10 minutes early.',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDays(5)->setTime(9, 0),
            'requested_end_at' => now()->addDays(5)->setTime(12, 0),
            'address_line1' => '321 Marketplace Test Way',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $request->recipient()->create([
            'full_name' => 'E2E Marketplace Recipient',
            'relationship_to_family' => 'Mother',
        ]);
        $request->tasks()->sync(collect($taskIds)->mapWithKeys(
            fn (int $id) => [$id => ['task_note' => null]]
        )->all());
    }

    /** @param list<int> $taskIds */
    private function seedInvitationVisualRequest(User $family, array $taskIds): void
    {
        $availableCaregiver = $this->makeVisualCaregiver('Visual Available Caregiver', 'visual-available-caregiver', 201);
        $this->seedCareBackground($availableCaregiver->caregiverProfile);
        $notAcceptingCaregiver = $this->makeVisualCaregiver('Visual Paused Caregiver', 'visual-paused-caregiver', 202);
        $notAcceptingCaregiver->caregiverProfile->update(['is_accepting_new_clients' => false]);
        $repliedCaregiver = $this->makeVisualCaregiver('Visual Replied Caregiver', 'visual-replied-caregiver', 203);

        $visualRequest = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'E2E Invitation Visual States',
            'scope_of_work' => 'Companionship and meal preparation.',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDays(3)->setTime(10, 0),
            'requested_end_at' => now()->addDays(3)->setTime(13, 0),
            'address_line1' => '456 Visual State Lane',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $visualRequest->recipient()->create([
            'full_name' => 'E2E Visual Recipient',
            'relationship_to_family' => 'Father',
        ]);
        $visualRequest->tasks()->sync(collect($taskIds)->mapWithKeys(
            fn (int $id) => [$id => ['task_note' => null]]
        )->all());
        CareRequestApplication::query()->create([
            'care_request_id' => $visualRequest->id,
            'caregiver_user_id' => $repliedCaregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 30,
            'cover_note' => 'I am interested in helping with this request.',
        ]);
    }

    private function makeVisualCaregiver(string $name, string $slug, int $emailSuffix): User
    {
        $caregiver = User::query()->create([
            'name' => $name,
            'email' => 'caregiver.visual.'.$emailSuffix.'@example.com',
            'role' => 'caregiver',
            'city' => 'Raleigh',
            'state' => 'NC',
            'date_of_birth' => now()->subYears(35)->toDateString(),
            'onboarding_completed_at' => now(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $profile = $this->makeMarketplaceReadyProfile($caregiver, $slug);
        $this->attachCatalogToProfile($profile);

        return $caregiver->fresh('caregiverProfile');
    }

    private function seedCareBackground(
        CaregiverProfile $profile,
        bool $includeExpiredCredential = false,
        string $verificationStatus = CaregiverCertification::STATUS_SELF_REPORTED,
    ): void {
        $experiences = CaregiverExperienceType::query()
            ->whereIn('slug', ['memory-care', 'mobility-fall-risk'])
            ->pluck('id');
        $cpr = CaregiverCertificationType::query()->where('slug', 'cpr')->firstOrFail();

        $profile->forceFill([
            'care_experience_notes' => 'Experienced with calm companionship, familiar routines, and non-medical mobility support.',
            'care_experience_answered_at' => now(),
            'certifications_answered_at' => now(),
        ])->save();
        $profile->careExperiences()->sync($experiences);
        $profile->certifications()->updateOrCreate(
            ['caregiver_certification_type_id' => $cpr->id],
            [
                'issuer' => 'American Red Cross',
                'expires_at' => now()->addYear()->toDateString(),
                'verification_status' => $verificationStatus,
                'verified_at' => $verificationStatus === CaregiverCertification::STATUS_VERIFIED ? now() : null,
            ],
        );

        if ($includeExpiredCredential) {
            $firstAid = CaregiverCertificationType::query()->where('slug', 'first-aid')->firstOrFail();
            $profile->certifications()->updateOrCreate(
                ['caregiver_certification_type_id' => $firstAid->id],
                [
                    'issuer' => 'E2E Training Center',
                    'expires_at' => now()->subDay()->toDateString(),
                    'verification_status' => CaregiverCertification::STATUS_SELF_REPORTED,
                ],
            );
        }
    }

    /** @param list<int> $taskIds */
    private function seedMissedRegularVisit(User $family, User $caregiver, array $taskIds): void
    {
        $start = now()->subDay()->setTime(7, 30);
        $end = $start->copy()->addHours(2);
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'E2E Missed Regular Visit - Time Correction',
            'additional_info' => 'Past regular-care occurrence for time-correction browser coverage.',
            'scope_of_work' => 'Companionship and morning routine support.',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => $start,
            'requested_end_at' => $end,
            'address_line1' => '789 Correction Way',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
            'first_hire_at' => now()->subWeek(),
        ]);
        $request->recipient()->create([
            'full_name' => 'E2E Time Recipient',
            'relationship_to_family' => 'Father',
        ]);
        $request->tasks()->sync(collect($taskIds)->mapWithKeys(fn (int $id) => [$id => ['task_note' => null]])->all());
        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
            'cover_note' => 'Assigned regular-care caregiver.',
        ]);
        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $end,
            'expected_minutes' => 120,
            'family_terms_accepted_at' => now()->subWeek(),
            'caregiver_terms_accepted_at' => now()->subWeek(),
        ]);
        $plan = CarePlan::query()->create([
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'source_care_request_id' => $request->id,
            'source_care_booking_id' => $booking->id,
            'status' => CarePlan::STATUS_ACTIVE,
            'title' => 'E2E time correction regular care',
            'schedule_days' => [strtolower($start->format('l'))],
            'schedule_start_time' => '07:30:00',
            'schedule_end_time' => '09:30:00',
            'starts_on' => now()->subMonth()->toDateString(),
            'timezone' => config('app.timezone'),
            'hourly_rate' => 30,
            'payment_status' => CarePlan::PAYMENT_AUTHORIZED,
        ]);
        $request->forceFill(['care_plan_id' => $plan->id])->save();
        $booking->forceFill([
            'care_plan_id' => $plan->id,
            'occurrence_key' => 'regular-care:'.$plan->id.':regular:e2e-missed:07:30',
            'plan_visit_kind' => 'regular',
            'plan_schedule_version' => 1,
            'agreement_snapshot' => app(BookingTrustService::class)->buildAgreementSnapshot($request->fresh(['recipient', 'tasks']), $application),
        ])->save();
    }

    /** @param list<int> $taskIds */
    private function seedTimeCorrectionState(User $family, User $caregiver, array $taskIds, string $status, string $title): void
    {
        $start = now()->subDays(2)->setTime(13, 0);
        $end = $start->copy()->addHours(2);
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => $title,
            'additional_info' => 'Deterministic time-correction status fixture.',
            'scope_of_work' => 'Companionship and household routine support.',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => $start,
            'requested_end_at' => $end,
            'address_line1' => '900 Workflow State Road',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
            'first_hire_at' => now()->subWeek(),
        ]);
        $request->recipient()->create([
            'full_name' => 'E2E Workflow Recipient',
            'relationship_to_family' => 'Mother',
        ]);
        $request->tasks()->sync(collect($taskIds)->mapWithKeys(fn (int $id) => [$id => ['task_note' => null]])->all());
        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
            'cover_note' => 'Assigned workflow fixture caregiver.',
        ]);
        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_COMPLETED,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $end,
            'started_at' => $start,
            'completed_at' => $end,
            'worked_minutes' => 120,
            'timesheet_submitted_at' => $end,
            'family_confirmed_at' => now()->subDay(),
            'family_terms_accepted_at' => now()->subWeek(),
            'caregiver_terms_accepted_at' => now()->subWeek(),
        ]);

        $needsTicket = in_array($status, [
            CareBookingTimeCorrection::STATUS_APPROVED_ADMIN_REQUIRED,
            CareBookingTimeCorrection::STATUS_ESCALATED,
        ], true);
        $ticket = $needsTicket ? SupportTicket::query()->create([
            'opener_user_id' => $caregiver->id,
            'counterparty_user_id' => $family->id,
            'care_request_id' => $request->id,
            'care_booking_id' => $booking->id,
            'category' => 'time_correction',
            'priority' => 'normal',
            'subject' => 'Time correction for visit #'.$booking->id,
            'description' => 'E2E workflow state awaiting LoLo review.',
        ]) : null;

        CareBookingTimeCorrection::query()->create([
            'client_request_id' => (string) Str::uuid(),
            'care_booking_id' => $booking->id,
            'requester_user_id' => $caregiver->id,
            'family_user_id' => $family->id,
            'version' => 1,
            'status' => $status,
            'reason_code' => CareBookingTimeCorrection::REASON_FORGOT_END,
            'explanation' => 'The caregiver forgot to end the visit and the family confirmed the actual time.',
            'proposed_started_at' => $start,
            'proposed_completed_at' => $end,
            'proposed_break_minutes' => 0,
            'proposed_worked_minutes' => 120,
            'original_snapshot' => [
                'booking' => [
                    'status' => CareBooking::STATUS_COMPLETED,
                    'scheduled_start_at' => $start->toIso8601String(),
                    'scheduled_end_at' => $end->toIso8601String(),
                    'started_at' => $start->toIso8601String(),
                    'completed_at' => $end->toIso8601String(),
                    'worked_minutes' => 120,
                    'check_in_lat' => 35.7796,
                    'check_in_lng' => -78.6382,
                    'check_out_lat' => null,
                    'check_out_lng' => null,
                ],
                'payment' => null,
            ],
            'financial_preview' => [
                'timezone' => config('app.timezone'),
                'worked_minutes' => 120,
                'target_charge_cents' => 6600,
                'caregiver_amount_cents' => 5940,
                'hourly_rate' => 30,
                'platform_fee_percent' => 10,
            ],
            'approved_by_user_id' => $family->id,
            'support_ticket_id' => $ticket?->id,
            'last_error' => match ($status) {
                CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED => 'Confirm the saved card to finish the approved correction.',
                CareBookingTimeCorrection::STATUS_ESCALATED => 'The response window expired before the correction was finalized.',
                default => null,
            },
            'submitted_at' => now()->subDay(),
            'approved_at' => now()->subHours(20),
            'payment_action_required_at' => $status === CareBookingTimeCorrection::STATUS_PAYMENT_ACTION_REQUIRED ? now()->subHours(19) : null,
            'escalated_at' => $status === CareBookingTimeCorrection::STATUS_ESCALATED ? now()->subHours(2) : null,
        ]);
    }

    /** @param list<int> $taskIds */
    private function seedRegularCare(User $family, User $caregiver, array $taskIds): void
    {
        $service = app(CarePlanService::class);
        $first = now()->addDay()->startOfDay();
        $activeSource = $this->makeCompletedSource($family, $caregiver, $taskIds, 'E2E regular care active source');
        $active = $service->sendOfferFromRequest($activeSource, $family, [
            'title' => 'Regular care for E2E Recipient',
            'schedule_days' => [$first->dayOfWeek, $first->copy()->addDays(2)->dayOfWeek],
            'schedule_start_time' => '09:00',
            'schedule_end_time' => '12:00',
            'starts_on' => $first->toDateString(),
            'care_notes' => 'Companionship, meal preparation, errands, and a friendly check-in.',
            'family_message' => 'Please follow the familiar morning routine.',
        ]);
        $active = $service->acceptOffer($active, $caregiver);
        if ($active->nextBooking?->payment) {
            $active->nextBooking->payment->forceFill([
                'status' => \App\Models\CareBookingPayment::STATUS_AUTHORIZATION_REQUIRED,
                'last_error' => 'Card confirmation is needed for this visit.',
                'failed_at' => now(),
            ])->save();
            app(CarePlanHealthService::class)->reconcile($active->fresh());
        }

        $offerSource = $this->makeCompletedSource($family, $caregiver, $taskIds, 'E2E direct regular care offer source');
        $service->sendOfferFromRequest($offerSource, $family, [
            'title' => 'Evening companionship for E2E Recipient',
            'schedule_days' => [$first->copy()->addDay()->dayOfWeek],
            'schedule_start_time' => '17:00',
            'schedule_end_time' => '19:00',
            'starts_on' => $first->copy()->addDay()->toDateString(),
            'care_notes' => 'Evening companionship and a light meal.',
            'family_message' => 'Would this weekly evening work for you?',
        ]);
    }

    /** @param list<int> $taskIds */
    private function makeCompletedSource(User $family, User $caregiver, array $taskIds, string $title): CareRequest
    {
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => $title,
            'additional_info' => 'Completed visit used to establish regular care.',
            'scope_of_work' => 'Companionship and meal preparation.',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'budget_min' => 30,
            'budget_max' => 30,
            'requested_start_at' => now()->subWeek()->setTime(9, 0),
            'requested_end_at' => now()->subWeek()->setTime(12, 0),
            'address_line1' => '123 E2E Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
            'first_hire_at' => now()->subWeek(),
        ]);
        $request->recipient()->create([
            'full_name' => 'E2E Recipient',
            'relationship_to_family' => 'Mother',
            'care_notes' => 'Prefers calm, clearly explained support.',
        ]);
        $request->tasks()->sync(collect($taskIds)->mapWithKeys(fn (int $id) => [$id => ['task_note' => null]])->all());
        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => 30,
            'cover_note' => 'Completed source visit.',
        ]);
        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_REVIEWED,
            'scheduled_start_at' => now()->subWeek()->setTime(9, 0),
            'scheduled_end_at' => now()->subWeek()->setTime(12, 0),
            'completed_at' => now()->subWeek()->setTime(12, 0),
            'timesheet_submitted_at' => now()->subWeek()->setTime(12, 5),
            'worked_minutes' => 180,
            'family_confirmed_at' => now()->subWeek()->setTime(12, 15),
            'family_terms_accepted_at' => now()->subWeek(),
            'caregiver_terms_accepted_at' => now()->subWeek(),
        ]);
        $booking->forceFill([
            'agreement_snapshot' => app(BookingTrustService::class)->buildAgreementSnapshot($request->fresh(['recipient', 'tasks']), $application),
        ])->save();

        return $request->fresh(['recipient', 'tasks', 'booking', 'applications.caregiver.caregiverProfile']);
    }

    private function seedCatalog(): void
    {
        foreach ([
            'Companionship',
            'Meal preparation',
            'Light housekeeping',
            'Transportation',
            'Medication reminders',
            'Errands',
            'Daily living assistance',
        ] as $skill) {
            Skill::query()->firstOrCreate(['name' => $skill]);
            CareTask::query()->firstOrCreate(['name' => $skill]);
        }

        foreach ([
            'English',
            'Spanish',
            'French',
            'Chinese (Mandarin)',
            'Vietnamese',
            'Arabic',
        ] as $language) {
            Language::query()->firstOrCreate(['name' => $language]);
        }
    }

    private function makeMarketplaceReadyProfile(User $user, string $slug): CaregiverProfile
    {
        return CaregiverProfile::query()->create([
            'user_id' => $user->id,
            'slug' => $slug,
            'status' => 'active',
            'bio' => str_repeat('Experienced non-medical caregiver focused on respectful, reliable support. ', 2),
            'hourly_rate' => 29.00,
            'years_experience' => 6,
            'service_area_zip' => '27601',
            'service_radius_miles' => 12,
            'is_accepting_new_clients' => true,
            'identity_verification_status' => 'approved',
            'identity_verified_at' => now(),
            'background_check_verified_at' => now(),
            'top_caregiver' => true,
            'average_rating' => 4.9,
            'reviews_count' => 14,
        ]);
    }

    private function makeDraftOnboardingProfile(User $user, string $slug): CaregiverProfile
    {
        return CaregiverProfile::query()->create([
            'user_id' => $user->id,
            'slug' => $slug,
            'status' => 'draft',
            'bio' => str_repeat('Compassionate caregiver with practical non-medical home support experience. ', 2),
            'hourly_rate' => 27.00,
            'years_experience' => 3,
            'care_background_schema_version' => CaregiverBackgroundService::SCHEMA_VERSION,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
            'is_accepting_new_clients' => true,
            'identity_verification_status' => 'approved',
            'identity_verified_at' => now(),
        ]);
    }

    private function makeUnderReviewProfile(User $user, string $slug): CaregiverProfile
    {
        return CaregiverProfile::query()->create([
            'user_id' => $user->id,
            'slug' => $slug,
            'status' => 'under_review',
            'review_submitted_at' => now()->subHour(),
            'bio' => str_repeat('Reliable caregiver profile pending admin moderation review. ', 2),
            'hourly_rate' => 26.00,
            'years_experience' => 4,
            'service_area_zip' => '27601',
            'service_radius_miles' => 12,
            'is_accepting_new_clients' => true,
            'identity_verification_status' => 'approved',
            'identity_verified_at' => now(),
        ]);
    }

    private function attachCatalogToProfile(CaregiverProfile $profile): void
    {
        $skillIds = Skill::query()
            ->whereIn('name', ['Companionship', 'Meal preparation'])
            ->pluck('id')
            ->all();
        $languageIds = Language::query()
            ->whereIn('name', ['English'])
            ->pluck('id')
            ->all();

        $profile->skills()->sync($skillIds);
        $profile->languages()->sync($languageIds);

        foreach (range(0, 6) as $day) {
            $profile->availabilities()->create([
                'day_of_week' => $day,
                'start_time' => '08:00',
                'end_time' => '17:00',
            ]);
        }
    }
}
