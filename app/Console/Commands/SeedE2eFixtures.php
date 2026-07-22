<?php

namespace App\Console\Commands;

use App\Models\CareBooking;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareTask;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\Booking\BookingTrustService;
use App\Services\RegularCare\CarePlanHealthService;
use App\Services\RegularCare\CarePlanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

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

        User::query()->create([
            'name' => 'E2E Admin',
            'email' => 'test@test.com',
            'role' => 'family',
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

        $readyProfile = $this->makeMarketplaceReadyProfile($readyCaregiver, 'e2e-ready-caregiver');
        $newProfile = $this->makeDraftOnboardingProfile($newCaregiver, 'e2e-new-caregiver');
        $underReviewProfile = $this->makeUnderReviewProfile($underReviewCaregiver, 'e2e-under-review-caregiver');

        $this->attachCatalogToProfile($readyProfile);
        $this->attachCatalogToProfile($newProfile);
        $this->attachCatalogToProfile($underReviewProfile);

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

        $this->seedRegularCare($family, $readyCaregiver, $taskIds);

        $this->line('E2E fixtures ready');
        $this->table(['Account', 'Email', 'Password'], [
            ['Family', 'family.e2e@example.com', 'password'],
            ['Admin', 'test@test.com', 'password'],
            ['Caregiver (ready)', 'caregiver.ready.e2e@example.com', 'password'],
            ['Caregiver (new)', 'caregiver.new.e2e@example.com', 'password'],
            ['Caregiver (under review)', 'caregiver.review.e2e@example.com', 'password'],
        ]);
        $this->line('Primary request ID: '.$request->id);

        return self::SUCCESS;
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
