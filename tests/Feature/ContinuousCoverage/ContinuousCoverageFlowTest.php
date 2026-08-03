<?php

namespace Tests\Feature\ContinuousCoverage;

use App\Livewire\Caregiver\ContinuousCoverageIndex as CaregiverCoverageIndex;
use App\Livewire\Caregiver\ShiftsIndex as CaregiverShiftsIndex;
use App\Livewire\Dashboard\Home as DashboardHome;
use App\Livewire\Family\ContinuousCoverageCreate;
use App\Livewire\Family\ContinuousCoverageShow;
use App\Models\CareBooking;
use App\Models\CareBookingPayment;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\ContinuousCoverageLaneRequest;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageReplacementCase;
use App\Models\ContinuousCoverageRosterMember;
use App\Models\ContinuousCoverageShift;
use App\Models\ContinuousCoverageShiftOffer;
use App\Models\ContinuousCoverageShiftTemplate;
use App\Models\FamilyCaregiverFavorite;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\ContinuousCoverage\ContinuousCoverageAccess;
use App\Services\ContinuousCoverage\ContinuousCoverageBookingAdapter;
use App\Services\ContinuousCoverage\ContinuousCoverageHandoffService;
use App\Services\ContinuousCoverage\ContinuousCoverageLaneRequestService;
use App\Services\ContinuousCoverage\ContinuousCoverageNotificationService;
use App\Services\ContinuousCoverage\ContinuousCoverageOperationsService;
use App\Services\ContinuousCoverage\ContinuousCoveragePricingService;
use App\Services\ContinuousCoverage\ContinuousCoverageReplacementService;
use App\Services\ContinuousCoverage\ContinuousCoverageRosterService;
use App\Services\ContinuousCoverage\ContinuousCoverageScheduleService;
use App\Services\Payments\BookingPaymentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ContinuousCoverageFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config()->set('marketplace.continuous_coverage.enabled', true);
        config()->set('marketplace.continuous_coverage.pilot_emails', []);
        config()->set('marketplace.continuous_coverage.generation_weeks', 2);
        config()->set('marketplace.continuous_coverage.booking_horizon_hours', 48);
        config()->set('marketplace.payments.bypass', true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_feature_flag_hides_routes_and_performs_no_work(): void
    {
        $family = $this->family();
        config()->set('marketplace.continuous_coverage.enabled', false);

        $this->actingAs($family)->get(route('family.continuous-coverage.index'))->assertNotFound();
        $counts = app(ContinuousCoverageOperationsService::class)->process();

        $this->assertSame(['plans' => 0, 'shifts_created' => 0, 'bookings_linked' => 0, 'payments_prepared' => 0, 'failures' => 0], $counts);
    }

    public function test_initial_migration_recovers_an_empty_interrupted_mysql_install_and_uses_short_constraint_names(): void
    {
        $migrationPath = database_path('migrations/2026_08_02_090000_create_continuous_coverage_tables.php');
        $migration = require $migrationPath;
        Schema::dropIfExists('continuous_coverage_lane_requests');
        $migration->down();

        Schema::create('continuous_coverage_plans', fn (Blueprint $table) => $table->id());
        Schema::create('continuous_coverage_roster_members', fn (Blueprint $table) => $table->id());

        $migration->up();

        foreach ([
            'continuous_coverage_plans',
            'continuous_coverage_roster_members',
            'continuous_coverage_shift_templates',
            'continuous_coverage_shifts',
            'continuous_coverage_replacement_cases',
            'continuous_coverage_shift_offers',
            'continuous_coverage_handoffs',
            'continuous_coverage_events',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table.' was not restored.');
        }

        $source = file_get_contents($migrationPath);
        preg_match_all("/->foreign\\([^,]+, '([^']+)'\\)/", (string) $source, $matches);
        $this->assertCount(23, $matches[1]);
        foreach ($matches[1] as $constraintName) {
            $this->assertLessThanOrEqual(64, strlen($constraintName), $constraintName.' exceeds MySQL\'s identifier limit.');
        }
        $this->assertStringNotContainsString('->constrained(', (string) $source);
    }

    public function test_initial_migration_never_removes_an_interrupted_install_that_contains_data(): void
    {
        $migration = require database_path('migrations/2026_08_02_090000_create_continuous_coverage_tables.php');
        Schema::dropIfExists('continuous_coverage_lane_requests');
        $migration->down();
        Schema::create('continuous_coverage_plans', fn (Blueprint $table) => $table->id());
        DB::table('continuous_coverage_plans')->insert(['id' => 1]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no tables were removed');

        $migration->up();
    }

    public function test_lane_request_migration_is_additive_and_uses_mysql_safe_constraint_names(): void
    {
        $migrationPath = database_path('migrations/2026_08_03_090000_create_continuous_coverage_lane_requests_table.php');
        $source = file_get_contents($migrationPath);

        $this->assertTrue(Schema::hasTable('continuous_coverage_lane_requests'));
        $this->assertTrue(Schema::hasColumns('continuous_coverage_lane_requests', [
            'continuous_coverage_plan_id',
            'shift_template_id',
            'roster_member_id',
            'caregiver_user_id',
            'responded_by_user_id',
            'batch_uuid',
            'status',
            'requested_at',
            'responded_at',
        ]));
        preg_match_all("/->foreign\\([^,]+, '([^']+)'\\)/", (string) $source, $matches);
        $this->assertCount(5, $matches[1]);
        foreach ($matches[1] as $constraintName) {
            $this->assertLessThanOrEqual(64, strlen($constraintName), $constraintName.' exceeds MySQL\'s identifier limit.');
        }
        $this->assertStringNotContainsString('->constrained(', (string) $source);
        $this->assertStringNotContainsString('dropIfExists(\'continuous_coverage_plans\')', (string) $source);
    }

    public function test_pilot_access_does_not_enroll_or_expose_other_users(): void
    {
        $pilot = $this->family(['email' => 'pilot@example.com']);
        $other = $this->family(['email' => 'other@example.com']);
        config()->set('marketplace.continuous_coverage.pilot_emails', ['pilot@example.com']);

        $this->actingAs($pilot)->get(route('family.continuous-coverage.index'))->assertOk();
        $this->actingAs($other)->get(route('family.continuous-coverage.index'))->assertNotFound();
        $this->assertDatabaseCount('continuous_coverage_plans', 0);
    }

    public function test_notification_service_refuses_a_plan_outside_the_current_pilot(): void
    {
        $family = $this->family(['email' => 'former-pilot@example.com']);
        $caregiver = $this->caregiver(['email' => 'former-pilot-caregiver@example.com']);
        $shift = $this->confirmedShift($family, $caregiver);
        $before = $caregiver->notificationDeliveries()->count();
        config()->set('marketplace.continuous_coverage.pilot_emails', ['different-pilot@example.com']);

        app(ContinuousCoverageNotificationService::class)->shiftReminder($shift);

        $this->assertSame($before, $caregiver->notificationDeliveries()->count());
    }

    public function test_around_the_clock_twelve_and_eight_hour_schedules_are_gap_free(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-02 12:00:00', 'America/New_York'));
        $family = $this->family();
        $service = app(ContinuousCoverageScheduleService::class);
        $twelve = $service->createPlan($family, $this->planData([
            'title' => 'Twelve hour coverage',
            'starts_on' => '2026-08-03',
            'shift_length_minutes' => 720,
        ]));
        $eight = $service->createPlan($family, $this->planData([
            'title' => 'Eight hour coverage',
            'starts_on' => '2026-08-03',
            'shift_length_minutes' => 480,
        ]));

        $this->assertSame(14, $twelve->templates()->count());
        $this->assertSame(21, $eight->templates()->count());
        $this->assertSame(14, $this->firstWeekShifts($twelve)->count());
        $this->assertSame(21, $this->firstWeekShifts($eight)->count());
        $this->assertContiguous($this->firstWeekShifts($twelve));
        $this->assertContiguous($this->firstWeekShifts($eight));
        $this->assertSame(10080, $this->firstWeekShifts($twelve)->sum('scheduled_minutes'));
        $this->assertSame(10080, $this->firstWeekShifts($eight)->sum('scheduled_minutes'));
    }

    public function test_family_can_preview_and_validate_a_custom_shift_length(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-02 12:00:00', 'America/New_York'));
        $family = $this->family();

        $component = Livewire::actingAs($family)
            ->test(ContinuousCoverageCreate::class)
            ->set('step', 2)
            ->set('shiftLengthChoice', 'custom')
            ->set('customShiftLengthHours', '5')
            ->call('nextStep')
            ->assertHasErrors(['customShiftLengthHours'])
            ->assertSet('step', 2);

        $component
            ->set('customShiftLengthHours', '4')
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('shiftLengthMinutes', 240)
            ->assertSet('step', 3);

        $component
            ->set('step', 2)
            ->set('customShiftLengthHours', 'not-a-number')
            ->set('coveragePattern', ContinuousCoveragePlan::PATTERN_OVERNIGHT)
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('shiftLengthMinutes', 720)
            ->assertSet('step', 3);
    }

    public function test_four_hour_custom_schedule_is_gap_free_and_can_be_future_dated(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-02 12:00:00', 'America/New_York'));
        $family = $this->family();
        $plan = $this->plan($family, ['starts_on' => '2026-08-03', 'shift_length_minutes' => 240]);
        $firstWeek = $this->firstWeekShifts($plan);

        $this->assertSame(42, $plan->templates()->count());
        $this->assertCount(42, $firstWeek);
        $this->assertSame(10080, $firstWeek->sum('scheduled_minutes'));
        $this->assertContiguous($firstWeek);

        Livewire::actingAs($family)
            ->test(ContinuousCoverageShow::class, ['coveragePlan' => $plan->id])
            ->set('tab', 'settings')
            ->set('scheduleEffectiveOn', '2026-08-10')
            ->set('scheduleShiftLengthChoice', 'custom')
            ->set('scheduleCustomShiftLengthHours', '2')
            ->call('saveFutureSchedule')
            ->assertHasNoErrors()
            ->assertSet('scheduleShiftLengthMinutes', 120);

        $this->assertSame(120, $plan->fresh()->shift_length_minutes);
        $this->assertSame(84, $plan->templates()->where('schedule_version', 2)->count());
    }

    public function test_custom_overnight_schedule_preserves_local_wall_clock_across_dst(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'America/New_York'));
        $plan = app(ContinuousCoverageScheduleService::class)->createPlan($this->family(), $this->planData([
            'title' => 'Overnight coverage',
            'starts_on' => '2026-10-26',
            'coverage_pattern' => ContinuousCoveragePlan::PATTERN_OVERNIGHT,
            'coverage_start_time' => '19:00',
            'coverage_end_time' => '07:00',
        ]));

        $shift = $plan->shifts()
            ->whereDate('scheduled_start_at', '2026-10-31')
            ->orderBy('scheduled_start_at')
            ->firstOrFail();
        $this->assertSame('19:00', $shift->scheduled_start_at->copy()->setTimezone('America/New_York')->format('H:i'));
        $this->assertSame('07:00', $shift->scheduled_end_at->copy()->setTimezone('America/New_York')->format('H:i'));
        $this->assertSame(720, $shift->scheduled_minutes);
    }

    public function test_around_the_clock_week_remains_168_scheduled_hours_across_dst(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'America/New_York'));
        $plan = $this->plan($this->family(), ['starts_on' => '2026-10-19']);
        $from = Carbon::parse('2026-10-26 00:00:00', $plan->timezone)->setTimezone(config('app.timezone'));

        $summary = app(ContinuousCoverageScheduleService::class)->coverageSummary(
            $plan,
            $from,
            Carbon::parse('2026-11-02 00:00:00', $plan->timezone)->setTimezone(config('app.timezone')),
        );

        $this->assertSame(10080, $summary['required_minutes']);
    }

    public function test_family_approval_and_caregiver_acceptance_are_separate_and_required_for_lanes(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $plan = $this->plan($family);
        $template = $plan->templates()->firstOrFail();
        $roster = app(ContinuousCoverageRosterService::class);

        $member = $roster->familyApprove($plan, $family, $caregiver);
        $this->assertSame(ContinuousCoverageRosterMember::STATUS_FAMILY_APPROVED, $member->status);
        $this->assertNull($member->caregiver_accepted_at);
        $this->expectException(ValidationException::class);
        $roster->offerLane($template, $member, $family);
    }

    public function test_family_can_find_review_and_invite_a_caregiver_from_the_care_team_modal(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver([
            'name' => 'Charles Helpful',
            'email' => 'private-charles@example.test',
            'city' => 'Durham',
            'state' => 'NC',
        ]);
        $caregiver->caregiverProfile->update([
            'slug' => 'charles-helpful-'.$caregiver->id,
            'bio' => 'Experienced caregiver focused on companionship and meal support.',
            'years_experience' => 7,
            'is_accepting_new_clients' => true,
            'identity_verified_at' => now(),
            'background_check_verified_at' => now(),
            'average_rating' => 4.9,
            'reviews_count' => 12,
        ]);
        FamilyCaregiverFavorite::query()->create([
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
        ]);
        $plan = $this->plan($family);

        Livewire::actingAs($family)
            ->test(ContinuousCoverageShow::class, ['coveragePlan' => $plan->id])
            ->set('tab', 'team')
            ->assertSee('Find & invite caregiver', false)
            ->assertDontSee('Search by caregiver name')
            ->call('openCaregiverSearchModal')
            ->assertSet('showCaregiverSearchModal', true)
            ->assertSee('Add a caregiver to this care team')
            ->assertSee('Saved caregivers')
            ->assertSee('Charles Helpful')
            ->set('caregiverSearch', 'Char')
            ->assertSee('Search results')
            ->assertSee('7 years of experience')
            ->assertSee('4.9 stars')
            ->assertSee('Identity verified')
            ->assertSee('Background check')
            ->assertSee('View profile')
            ->assertDontSee('private-charles@example.test')
            ->call('selectCaregiverForRoster', $caregiver->id)
            ->assertSet('selectedCaregiverId', $caregiver->id)
            ->assertSee('Choose invitation preferences')
            ->assertSee('They do not assign a shift')
            ->set('inviteRole', ContinuousCoverageRosterMember::ROLE_PRIMARY)
            ->set('inviteEligibleDays', [1, 3, 5])
            ->set('inviteEligibleShiftTypes', ['daytime'])
            ->call('approveCaregiver', $caregiver->id)
            ->assertHasNoErrors()
            ->assertSet('showCaregiverSearchModal', false)
            ->assertSee('Charles Helpful was approved and invited to join your care team.');

        $this->assertDatabaseHas('continuous_coverage_roster_members', [
            'continuous_coverage_plan_id' => $plan->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => ContinuousCoverageRosterMember::STATUS_FAMILY_APPROVED,
            'role' => ContinuousCoverageRosterMember::ROLE_PRIMARY,
        ]);
    }

    public function test_care_team_modal_explains_existing_and_unavailable_caregivers_without_duplicate_invites(): void
    {
        $family = $this->family();
        $active = $this->caregiver(['name' => 'Morgan Existing', 'city' => 'Durham']);
        $active->caregiverProfile->update(['is_accepting_new_clients' => true]);
        $unavailable = $this->caregiver(['name' => 'Morgan Unavailable', 'city' => 'Durham']);
        $unavailable->caregiverProfile->update(['is_accepting_new_clients' => false]);
        $plan = $this->plan($family);
        app(ContinuousCoverageRosterService::class)->familyApprove($plan, $family, $active);

        Livewire::actingAs($family)
            ->test(ContinuousCoverageShow::class, ['coveragePlan' => $plan->id])
            ->set('tab', 'team')
            ->call('openCaregiverSearchModal')
            ->set('caregiverSearch', 'Morgan')
            ->assertSee('Care-team invitation sent')
            ->assertSee('Not accepting new clients')
            ->call('selectCaregiverForRoster', $active->id)
            ->assertSet('selectedCaregiverId', null)
            ->assertSet('caregiverSearchFeedback', 'This caregiver already has an invitation waiting for their response.')
            ->call('selectCaregiverForRoster', $unavailable->id)
            ->assertSet('selectedCaregiverId', null)
            ->assertSet('caregiverSearchFeedback', 'This caregiver is not accepting new clients right now.');

        $this->assertSame(1, $plan->rosterMembers()->where('caregiver_user_id', $active->id)->count());
        $this->assertFalse($plan->rosterMembers()->where('caregiver_user_id', $unavailable->id)->exists());
    }

    public function test_care_team_modal_browses_platform_caregivers_by_service_area_then_quality(): void
    {
        $family = $this->family();
        $nearby = $this->caregiver(['name' => 'Alex Nearby', 'city' => 'Durham', 'state' => 'NC']);
        $this->makeCaregiverBrowsable($nearby, [
            'slug' => 'alex-nearby-'.$nearby->id,
            'service_area_zip' => '27701',
            'average_rating' => 4.2,
            'reviews_count' => 3,
            'completed_bookings_count' => 9,
            'reliability_score' => 96,
        ]);
        $sameState = $this->caregiver(['name' => 'Bailey Same State', 'city' => 'Raleigh', 'state' => 'NC']);
        $this->makeCaregiverBrowsable($sameState, [
            'slug' => 'bailey-same-state-'.$sameState->id,
            'service_area_zip' => '27601',
            'average_rating' => 5,
            'reviews_count' => 25,
        ]);
        $fartherAway = $this->caregiver(['name' => 'Casey Farther Away', 'city' => 'Austin', 'state' => 'TX']);
        $this->makeCaregiverBrowsable($fartherAway, [
            'slug' => 'casey-farther-away-'.$fartherAway->id,
            'service_area_zip' => '78701',
            'average_rating' => 5,
            'reviews_count' => 40,
        ]);
        $unavailable = $this->caregiver(['name' => 'Dana Not Accepting', 'city' => 'Durham', 'state' => 'NC']);
        $this->makeCaregiverBrowsable($unavailable, [
            'service_area_zip' => '27701',
            'is_accepting_new_clients' => false,
        ]);
        $plan = $this->plan($family);

        Livewire::actingAs($family)
            ->test(ContinuousCoverageShow::class, ['coveragePlan' => $plan->id])
            ->set('tab', 'team')
            ->call('openCaregiverSearchModal')
            ->assertSee('Caregivers near Durham')
            ->assertSee('ordered by service-area match, then ratings, reviews, and completed care')
            ->assertSeeInOrder(['Alex Nearby', 'Bailey Same State', 'Casey Farther Away'])
            ->assertSee('9 completed care visits')
            ->assertSee('96% reliability')
            ->assertDontSee('Dana Not Accepting');
    }

    public function test_accepted_lane_assigns_only_the_approved_caregiver_and_creates_no_early_booking(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $plan = $this->plan($family, ['starts_on' => now()->addWeek()->toDateString()]);
        $template = $plan->templates()->firstOrFail();
        $roster = app(ContinuousCoverageRosterService::class);

        $member = $roster->familyApprove($plan, $family, $caregiver);
        $member = $roster->caregiverAccept($member, $caregiver);
        $roster->offerLane($template, $member, $family);
        $roster->acceptLane($template->fresh(), $caregiver);

        $assigned = $template->shifts()->where('scheduled_start_at', '>=', now())->get();
        $this->assertNotEmpty($assigned);
        $this->assertTrue($assigned->every(fn ($shift) => (int) $shift->assigned_caregiver_user_id === (int) $caregiver->id));
        $this->assertTrue($assigned->every(fn ($shift) => $shift->status === ContinuousCoverageShift::STATUS_CONFIRMED));
        $this->assertDatabaseCount('care_bookings', 0);
    }

    public function test_an_active_recurring_lane_cannot_be_overwritten_by_another_offer(): void
    {
        $family = $this->family();
        $firstCaregiver = $this->caregiver(['email' => 'lane-first@example.com']);
        $secondCaregiver = $this->caregiver(['email' => 'lane-second@example.com']);
        $plan = $this->plan($family);
        $roster = app(ContinuousCoverageRosterService::class);
        $firstMember = $roster->caregiverAccept($roster->familyApprove($plan, $family, $firstCaregiver), $firstCaregiver);
        $secondMember = $roster->caregiverAccept($roster->familyApprove($plan, $family, $secondCaregiver), $secondCaregiver);
        $template = $plan->templates()->firstOrFail();
        $roster->offerLane($template, $firstMember, $family);
        $roster->acceptLane($template->fresh(), $firstCaregiver);

        try {
            $roster->offerLane($template->fresh(), $secondMember, $family);
            $this->fail('An accepted recurring lane was overwritten by a new offer.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lane', $exception->errors());
        }

        $this->assertSame(ContinuousCoverageShiftTemplate::STATUS_ACTIVE, $template->fresh()->status);
        $this->assertSame($firstMember->id, $template->fresh()->roster_member_id);
        $this->assertSame(1, $plan->events()->where('event_type', 'recurring_lane_offered')->count());
    }

    public function test_booking_adapter_is_idempotent_and_does_not_turn_the_shift_into_regular_care(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $shift = $this->confirmedShift($family, $caregiver);
        $adapter = app(ContinuousCoverageBookingAdapter::class);

        $first = $adapter->linkConfirmedShift($shift);
        $second = $adapter->linkConfirmedShift($shift->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('care_bookings', 1);
        $this->assertDatabaseCount('care_requests', 1);
        $this->assertNull($first->care_plan_id);
        $this->assertSame('coverage', $first->plan_visit_kind);
        $this->assertTrue($first->careRequest->is_system_generated);
        $this->assertSame(CareRequest::TYPE_ONE_TIME, $first->careRequest->request_type);
    }

    public function test_operations_prepare_one_existing_payment_record_and_are_safe_to_retry(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $shift = $this->confirmedShift($family, $caregiver);
        $operations = app(ContinuousCoverageOperationsService::class);

        $first = $operations->process();
        $shift->refresh();
        $this->assertNotNull($shift->care_booking_id);
        $this->assertGreaterThanOrEqual(1, $first['bookings_linked']);
        $this->assertGreaterThanOrEqual(1, $first['payments_prepared']);
        $this->assertSame(1, CareBooking::query()->where('occurrence_key', 'like', 'continuous-coverage:'.$shift->id.':%')->count());
        $this->assertSame(1, CareBookingPayment::query()->where('care_booking_id', $shift->care_booking_id)->count());

        $second = $operations->process();
        $this->assertSame(0, $second['bookings_linked']);
        $this->assertSame(0, $second['payments_prepared']);
        $this->assertSame(1, CareBooking::query()->where('occurrence_key', 'like', 'continuous-coverage:'.$shift->id.':%')->count());
        $this->assertSame(1, CareBookingPayment::query()->where('care_booking_id', $shift->care_booking_id)->count());
        $this->assertDatabaseHas('continuous_coverage_events', [
            'continuous_coverage_shift_id' => $shift->id,
            'event_type' => 'payment_prepared',
        ]);
    }

    public function test_replacement_offer_only_goes_to_active_family_approved_backups(): void
    {
        $family = $this->family();
        $primary = $this->caregiver(['email' => 'primary@example.com']);
        $backup = $this->caregiver(['email' => 'backup@example.com']);
        $outsider = $this->caregiver(['email' => 'outsider@example.com']);
        $shift = $this->confirmedShift($family, $primary, [
            'replacement_confirmation_mode' => ContinuousCoveragePlan::CONFIRM_FAMILY,
        ]);
        $roster = app(ContinuousCoverageRosterService::class);
        $backupMember = $roster->familyApprove($shift->plan, $family, $backup, ContinuousCoverageRosterMember::ROLE_BACKUP, true);
        $roster->caregiverAccept($backupMember, $backup);

        $case = app(ContinuousCoverageReplacementService::class)->release($shift, $primary, 'A personal conflict came up.');

        $this->assertDatabaseHas('continuous_coverage_shift_offers', ['replacement_case_id' => $case->id, 'caregiver_user_id' => $backup->id]);
        $this->assertDatabaseMissing('continuous_coverage_shift_offers', ['replacement_case_id' => $case->id, 'caregiver_user_id' => $outsider->id]);
        $this->assertSame(ContinuousCoverageShift::STATUS_REPLACEMENT_NEEDED, $shift->fresh()->status);
    }

    public function test_declining_an_automatic_backup_offer_never_confirms_or_creates_a_booking(): void
    {
        $family = $this->family();
        $admin = User::factory()->create(['role' => 'admin']);
        $primary = $this->caregiver(['email' => 'decline-primary@example.com']);
        $backup = $this->caregiver(['email' => 'decline-backup@example.com']);
        $shift = $this->confirmedShift($family, $primary, [
            'replacement_confirmation_mode' => ContinuousCoveragePlan::CONFIRM_APPROVED_BACKUP,
        ]);
        $roster = app(ContinuousCoverageRosterService::class);
        $roster->caregiverAccept($roster->familyApprove($shift->plan, $family, $backup), $backup);
        $replacements = app(ContinuousCoverageReplacementService::class);
        $case = $replacements->release($shift, $primary, 'The primary caregiver cannot attend this visit.');
        $offer = $case->offers()->where('caregiver_user_id', $backup->id)->firstOrFail();

        $replacements->decline($offer, $backup);

        $this->assertSame(ContinuousCoverageShiftOffer::STATUS_DECLINED, $offer->fresh()->status);
        $this->assertSame(ContinuousCoverageReplacementCase::STATUS_UNRESOLVED, $case->fresh()->status);
        $this->assertSame(ContinuousCoverageShift::STATUS_REPLACEMENT_NEEDED, $shift->fresh()->status);
        $this->assertNull($shift->fresh()->care_booking_id);
        $this->assertSame(0, $family->notificationDeliveries()
            ->where('event_key', 'continuous_coverage_replacement_accepted')
            ->count());
        $this->assertDatabaseHas('continuous_coverage_events', [
            'continuous_coverage_shift_id' => $shift->id,
            'event_type' => 'replacement_offer_declined',
        ]);
        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $admin->id,
            'event_key' => 'continuous_coverage_gap_unresolved',
            'channel' => 'in_app',
        ]);
        $this->assertDatabaseMissing('marketplace_notification_deliveries', [
            'user_id' => $admin->id,
            'event_key' => 'continuous_coverage_gap_unresolved',
            'channel' => 'email',
        ]);
    }

    public function test_only_one_backup_can_win_and_family_confirmation_is_required_when_configured(): void
    {
        $family = $this->family();
        $primary = $this->caregiver(['email' => 'primary2@example.com']);
        $backupA = $this->caregiver(['email' => 'backup-a@example.com']);
        $backupB = $this->caregiver(['email' => 'backup-b@example.com']);
        $shift = $this->confirmedShift($family, $primary, [
            'replacement_confirmation_mode' => ContinuousCoveragePlan::CONFIRM_FAMILY,
        ]);
        $roster = app(ContinuousCoverageRosterService::class);
        foreach ([$backupA, $backupB] as $backup) {
            $member = $roster->familyApprove($shift->plan, $family, $backup, ContinuousCoverageRosterMember::ROLE_BACKUP, true);
            $roster->caregiverAccept($member, $backup);
        }
        $case = app(ContinuousCoverageReplacementService::class)->release($shift, $primary, 'Unable to attend this future shift.');
        $offerA = $case->offers()->where('caregiver_user_id', $backupA->id)->firstOrFail();
        $offerB = $case->offers()->where('caregiver_user_id', $backupB->id)->firstOrFail();
        $service = app(ContinuousCoverageReplacementService::class);
        $service->respond($offerA, $backupA, true);

        $this->assertSame(ContinuousCoverageReplacementCase::STATUS_AWAITING_FAMILY, $case->fresh()->status);
        $this->assertSame(ContinuousCoverageShift::STATUS_AWAITING_FAMILY, $shift->fresh()->status);
        $this->assertSame(ContinuousCoverageShiftOffer::STATUS_CLOSED, $offerB->fresh()->status);
        try {
            $service->respond($offerB->fresh(), $backupB, true);
            $this->fail('A closed competing offer was accepted.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $confirmed = $service->familyConfirm($case->fresh(), $family);
        $this->assertSame($backupA->id, $confirmed->assigned_caregiver_user_id);
        $this->assertSame(ContinuousCoverageShift::STATUS_CONFIRMED, $confirmed->status);
        $this->assertSame(1, $case->offers()->where('status', ContinuousCoverageShiftOffer::STATUS_ACCEPTED)->count());
    }

    public function test_family_can_choose_another_approved_backup_without_leaving_the_shift_stuck(): void
    {
        $family = $this->family();
        $primary = $this->caregiver(['email' => 'choose-primary@example.com']);
        $backupA = $this->caregiver(['email' => 'choose-a@example.com']);
        $backupB = $this->caregiver(['email' => 'choose-b@example.com']);
        $shift = $this->confirmedShift($family, $primary);
        $roster = app(ContinuousCoverageRosterService::class);
        foreach ([$backupA, $backupB] as $backup) {
            $roster->caregiverAccept($roster->familyApprove($shift->plan, $family, $backup), $backup);
        }
        $replacements = app(ContinuousCoverageReplacementService::class);
        $case = $replacements->release($shift, $primary, 'Unable to attend this future visit.');
        $offerA = $case->offers()->where('caregiver_user_id', $backupA->id)->firstOrFail();
        $offerB = $case->offers()->where('caregiver_user_id', $backupB->id)->firstOrFail();
        $replacements->respond($offerA, $backupA, true);

        $replacements->familyDecline($case->fresh(), $family);

        $this->assertSame(ContinuousCoverageShiftOffer::STATUS_NOT_SELECTED, $offerA->fresh()->status);
        $this->assertSame(ContinuousCoverageShiftOffer::STATUS_PENDING, $offerB->fresh()->status);
        $this->assertSame(ContinuousCoverageReplacementCase::STATUS_OPEN, $case->fresh()->status);
        $this->assertSame(ContinuousCoverageShift::STATUS_REPLACEMENT_NEEDED, $shift->fresh()->status);

        $replacements->respond($offerB->fresh(), $backupB, true);
        $confirmed = $replacements->familyConfirm($case->fresh(), $family);
        $this->assertSame($backupB->id, $confirmed->assigned_caregiver_user_id);
        $this->assertDatabaseHas('continuous_coverage_events', [
            'continuous_coverage_shift_id' => $shift->id,
            'event_type' => 'replacement_family_declined',
        ]);
    }

    public function test_a_replacement_caregiver_can_release_later_without_reusing_closed_offers(): void
    {
        $family = $this->family();
        $primary = $this->caregiver(['email' => 'repeat-primary@example.com']);
        $firstBackup = $this->caregiver(['email' => 'repeat-first@example.com']);
        $secondBackup = $this->caregiver(['email' => 'repeat-second@example.com']);
        $shift = $this->confirmedShift($family, $primary);
        $roster = app(ContinuousCoverageRosterService::class);
        foreach ([$firstBackup, $secondBackup] as $backup) {
            $roster->caregiverAccept($roster->familyApprove($shift->plan, $family, $backup), $backup);
        }

        $replacements = app(ContinuousCoverageReplacementService::class);
        $firstCase = $replacements->release($shift, $primary, 'The primary caregiver cannot attend this visit.');
        $firstOffer = $firstCase->offers()->where('caregiver_user_id', $firstBackup->id)->firstOrFail();
        $replacements->respond($firstOffer, $firstBackup, true);
        $replacements->familyConfirm($firstCase->fresh(), $family);

        $secondCase = $replacements->release(
            $shift->fresh(),
            $firstBackup,
            'The first replacement is no longer able to attend.',
        );

        $this->assertNotSame($firstCase->id, $secondCase->id);
        $this->assertSame(2, $shift->replacementCases()->count());
        $this->assertSame($secondCase->id, $shift->fresh()->replacementCase->id);
        $this->assertDatabaseHas('continuous_coverage_shift_offers', [
            'replacement_case_id' => $secondCase->id,
            'caregiver_user_id' => $secondBackup->id,
            'status' => ContinuousCoverageShiftOffer::STATUS_PENDING,
        ]);
        $this->assertSame(2, ContinuousCoverageShiftOffer::query()
            ->where('continuous_coverage_shift_id', $shift->id)
            ->where('caregiver_user_id', $secondBackup->id)
            ->count());
        Livewire::actingAs($family)
            ->test(ContinuousCoverageShow::class, ['coveragePlan' => $shift->continuous_coverage_plan_id])
            ->call('openShift', $shift->id)
            ->assertSee('The primary caregiver cannot attend this visit.')
            ->assertSee('The first replacement is no longer able to attend.');
    }

    public function test_handoff_notes_are_limited_to_the_assigned_caregiver_and_follow_the_shift(): void
    {
        $family = $this->family();
        $primary = $this->caregiver(['email' => 'handoff-primary@example.com']);
        $backup = $this->caregiver(['email' => 'handoff-backup@example.com']);
        $outsider = $this->caregiver(['email' => 'handoff-outsider@example.com']);
        $shift = $this->confirmedShift($family, $primary);
        $handoffs = app(ContinuousCoverageHandoffService::class);
        $handoff = $handoffs->record($shift, $primary, 'Medication list is in the blue folder.');

        try {
            $handoffs->record($shift, $outsider, 'This should not be saved.');
            $this->fail('An unassigned caregiver added a handoff note.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('shift', $exception->errors());
        }

        $roster = app(ContinuousCoverageRosterService::class);
        $roster->caregiverAccept($roster->familyApprove($shift->plan, $family, $backup), $backup);
        $replacements = app(ContinuousCoverageReplacementService::class);
        $case = $replacements->release($shift, $primary, 'The primary caregiver cannot attend this visit.');
        $offer = $case->offers()->where('caregiver_user_id', $backup->id)->firstOrFail();
        $replacements->respond($offer, $backup, true);
        $replacements->familyConfirm($case->fresh(), $family);

        $this->assertSame($handoff->id, $shift->handoffs()->firstOrFail()->id);
        Livewire::actingAs($backup)
            ->test(CaregiverCoverageIndex::class)
            ->set('tab', 'schedule')
            ->assertSee('Medication list is in the blue folder.');
    }

    public function test_family_and_caregiver_surfaces_enforce_ownership(): void
    {
        $owner = $this->family(['email' => 'owner@example.com']);
        $other = $this->family(['email' => 'other-owner@example.com']);
        $caregiver = $this->caregiver();
        $plan = $this->plan($owner);

        $this->actingAs($owner)->get(route('family.continuous-coverage.show', $plan))->assertOk();
        $this->actingAs($other)->get(route('family.continuous-coverage.show', $plan))->assertNotFound();
        $this->actingAs($caregiver)->get(route('caregiver.continuous-coverage.index'))->assertOk();
        Livewire::actingAs($other)->test(ContinuousCoverageShow::class, ['coveragePlan' => $plan->id])->assertNotFound();
        Livewire::actingAs($caregiver)->test(CaregiverCoverageIndex::class)->assertOk();
    }

    public function test_plan_policy_does_not_expose_private_plan_to_applicants_or_unaccepted_invitees(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $plan = $this->plan($family, ['marketplace_applications_enabled' => true]);
        $roster = app(ContinuousCoverageRosterService::class);

        $application = $roster->apply($plan, $caregiver);
        $this->assertFalse($caregiver->can('view', $plan));

        $approved = $roster->familyApprove($plan, $family, $caregiver);
        $this->assertFalse($caregiver->can('view', $plan));

        $active = $roster->caregiverAccept($approved, $caregiver);
        $this->assertTrue($caregiver->can('view', $plan));

        $roster->remove($active, $family);
        $this->assertFalse($caregiver->can('view', $plan));
        $this->assertSame($application->id, $approved->id);
    }

    public function test_removed_caregiver_keeps_only_their_own_released_shift_history(): void
    {
        $family = $this->family(['email' => 'history-pilot@example.com']);
        $caregiver = $this->caregiver(['email' => 'former-team-member@example.com']);
        $shift = $this->confirmedShift($family, $caregiver);
        $booking = app(ContinuousCoverageBookingAdapter::class)->linkConfirmedShift($shift);
        $replacement = app(ContinuousCoverageReplacementService::class)->release(
            $shift->fresh(),
            $caregiver,
            'I can no longer attend this future shift.',
        );
        $member = $shift->plan->rosterMembers()
            ->where('caregiver_user_id', $caregiver->id)
            ->firstOrFail();
        app(ContinuousCoverageRosterService::class)->remove($member, $family);
        config()->set('marketplace.continuous_coverage.pilot_emails', [$family->email]);

        $this->assertFalse(Gate::forUser($caregiver)->allows('view', $shift->plan));
        $this->assertTrue(Gate::forUser($caregiver)->allows('view', $shift->fresh()));
        $this->actingAs($caregiver)
            ->get(route('caregiver.continuous-coverage.index', ['tab' => 'history']))
            ->assertOk()
            ->assertSee('Released / replaced')
            ->assertSee('Open visit history');
        $this->assertSame($booking->id, data_get($replacement->shift->fresh()->metadata, 'released_booking_ids.0'));
    }

    public function test_creating_coverage_does_not_modify_existing_request_or_booking(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Existing ordinary request',
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'address_line1' => '10 Existing St',
            'city' => 'Durham',
            'state' => 'NC',
            'zip' => '27701',
        ]);
        $booking = CareBooking::query()->create([
            'care_request_id' => $request->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBooking::STATUS_SCHEDULED,
            'scheduled_start_at' => now()->addDay(),
            'scheduled_end_at' => now()->addDay()->addHours(2),
        ]);
        $beforeRequest = $request->refresh()->getRawOriginal();
        $beforeBooking = $booking->refresh()->getRawOriginal();

        $this->plan($family);

        $this->assertSame($beforeRequest, $request->fresh()->getRawOriginal());
        $this->assertSame($beforeBooking, $booking->fresh()->getRawOriginal());
    }

    public function test_coverage_plan_uses_canonical_family_pricing_instead_of_client_input(): void
    {
        $family = $this->family();

        $plan = $this->plan($family, ['hourly_rate' => 1]);

        $this->assertSame('30.00', $plan->hourly_rate);
    }

    public function test_caregiver_estimates_reuse_the_canonical_booking_quote(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $plan = $this->plan($family);
        $pricing = app(ContinuousCoveragePricingService::class);
        $quote = $pricing->quoteForPlan($plan, $caregiver, 720);
        $expected = '$'.number_format($quote['caregiver_amount_cents'] / 100, 2).' estimated for 12 hours';

        $member = app(ContinuousCoverageRosterService::class)->familyApprove(
            $plan,
            $family,
            $caregiver,
        );

        Livewire::actingAs($caregiver)
            ->test(CaregiverCoverageIndex::class)
            ->set('tab', 'offers')
            ->assertSee($expected)
            ->assertDontSee('$30.00/hour');

        $payload = json_encode($caregiver->notificationDeliveries()
            ->where('event_key', 'continuous_coverage_team_invitation')
            ->pluck('payload')
            ->all());
        $this->assertStringContainsString('Estimated caregiver earnings', $payload);
        $this->assertStringContainsString(number_format($quote['caregiver_amount_cents'] / 100, 2), $payload);
        $this->assertSame(ContinuousCoverageRosterMember::STATUS_FAMILY_APPROVED, $member->status);
    }

    public function test_family_upcoming_estimate_reuses_the_canonical_booking_quote(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $shift = $this->confirmedShift($family, $caregiver);
        $quote = app(ContinuousCoveragePricingService::class)->quoteForShift($shift);

        Livewire::actingAs($family)
            ->test(ContinuousCoverageShow::class, ['coveragePlan' => $shift->continuous_coverage_plan_id])
            ->set('tab', 'billing')
            ->assertSee('$'.number_format($quote['total_charge_cents'] / 100, 2))
            ->assertSee('using the normal booking price');
    }

    public function test_six_hour_schedule_and_repeated_generation_are_gap_free_and_idempotent(): void
    {
        $plan = $this->plan($this->family(), ['shift_length_minutes' => 360]);
        $service = app(ContinuousCoverageScheduleService::class);

        $this->assertSame(28, $plan->templates()->count());
        $firstWeek = $this->firstWeekShifts($plan);
        $this->assertCount(28, $firstWeek);
        $this->assertSame('07:00', $firstWeek->first()->scheduled_start_at->copy()->setTimezone($plan->timezone)->format('H:i'));
        $this->assertSame(10080, $firstWeek->sum('scheduled_minutes'));
        $this->assertContiguous($firstWeek);

        $countBefore = $plan->shifts()->count();
        $created = $service->generate($plan, Carbon::parse($plan->starts_on, $plan->timezone)->addWeeks(2));
        $service->generate($plan, Carbon::parse($plan->starts_on, $plan->timezone)->addWeeks(2));

        $this->assertGreaterThanOrEqual(0, $created);
        $this->assertSame($plan->shifts()->count(), $plan->shifts()->distinct('occurrence_key')->count('occurrence_key'));
        $this->assertGreaterThanOrEqual($countBefore, $plan->shifts()->count());
    }

    public function test_weekly_coverage_summary_prorates_overnight_boundary_shifts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-02 10:00:00', 'America/New_York'));
        $plan = $this->plan($this->family(), ['starts_on' => '2026-08-03']);
        $fromLocal = Carbon::parse('2026-08-10 00:00:00', $plan->timezone);
        $priorOvernight = $plan->shifts()
            ->where('scheduled_start_at', '<', $fromLocal->copy()->setTimezone(config('app.timezone')))
            ->where('scheduled_end_at', '>', $fromLocal->copy()->setTimezone(config('app.timezone')))
            ->firstOrFail();
        $priorOvernight->forceFill(['status' => ContinuousCoverageShift::STATUS_CONFIRMED])->save();

        $from = $fromLocal->copy()->setTimezone(config('app.timezone'));
        $summary = app(ContinuousCoverageScheduleService::class)->coverageSummary(
            $plan,
            $from,
            $from->copy()->addWeek(),
        );

        $this->assertSame(10080, $summary['required_minutes']);
        $this->assertSame(420, $summary['covered_minutes']);
        $this->assertSame(9660, $summary['uncovered_minutes']);
    }

    public function test_coverage_summary_keeps_template_requirement_when_generation_is_incomplete(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-02 10:00:00', 'America/New_York'));
        $plan = $this->plan($this->family(), ['starts_on' => '2026-08-03']);
        $weekStart = Carbon::parse('2026-08-03 07:00:00', $plan->timezone)
            ->setTimezone(config('app.timezone'));
        $weekShifts = $plan->shifts()
            ->where('scheduled_start_at', '>=', $weekStart)
            ->where('scheduled_start_at', '<', $weekStart->copy()->addWeek())
            ->orderBy('scheduled_start_at')
            ->get();
        $missing = $weekShifts->first();
        $this->assertNotNull($missing);
        $plan->shifts()->whereKey($weekShifts->pluck('id'))->update([
            'status' => ContinuousCoverageShift::STATUS_CONFIRMED,
            'updated_at' => now(),
        ]);
        $missing->delete();

        $summary = app(ContinuousCoverageScheduleService::class)->coverageSummary(
            $plan,
            $weekStart,
            $weekStart->copy()->addWeek(),
        );

        $this->assertSame(10080, $summary['required_minutes']);
        $this->assertSame(720, $summary['uncovered_minutes']);
        $this->assertSame(0, $summary['overlap_minutes']);
    }

    public function test_weekly_schedule_analysis_detects_gaps_and_rejects_overlaps(): void
    {
        $service = app(ContinuousCoverageScheduleService::class);
        $analysis = $service->analyzeWeeklyWindows([
            ['day' => 1, 'start' => '07:00', 'end' => '19:00'],
        ], true);

        $this->assertTrue($analysis['has_gaps']);
        $this->assertFalse($analysis['has_overlaps']);
        $this->assertSame(9360, $analysis['uncovered_minutes']);

        try {
            $service->createPlan($this->family(), $this->planData([
                'coverage_pattern' => ContinuousCoveragePlan::PATTERN_CUSTOM,
                'custom_windows' => [
                    ['day' => 1, 'start' => '07:00', 'end' => '12:00'],
                    ['day' => 1, 'start' => '11:00', 'end' => '14:00'],
                ],
            ]));
            $this->fail('An overlapping custom schedule was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customWindows', $exception->errors());
        }
    }

    public function test_pilot_family_invitation_grants_only_the_involved_caregiver_access(): void
    {
        $family = $this->family(['email' => 'coverage-pilot@example.com']);
        $invited = $this->caregiver(['email' => 'invited-caregiver@example.com']);
        $outsider = $this->caregiver(['email' => 'outside-caregiver@example.com']);
        config()->set('marketplace.continuous_coverage.pilot_emails', ['coverage-pilot@example.com']);
        $plan = $this->plan($family);

        app(ContinuousCoverageRosterService::class)->familyApprove($plan, $family, $invited);

        $this->actingAs($invited)->get(route('caregiver.continuous-coverage.index'))->assertOk();
        $this->actingAs($outsider)->get(route('caregiver.continuous-coverage.index'))->assertNotFound();
    }

    public function test_disabled_feature_keeps_admin_recovery_but_blocks_new_booking_linkage(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $shift = $this->confirmedShift($family, $caregiver);
        $admin = User::factory()->create(['role' => 'admin']);
        config()->set('marketplace.continuous_coverage.enabled', false);

        $this->actingAs($admin)->get(route('admin.continuous-coverage.index'))->assertOk();
        $this->assertFalse(app(ContinuousCoverageAccess::class)->visibleInNavigation($admin));

        try {
            app(ContinuousCoverageBookingAdapter::class)->linkConfirmedShift($shift);
            $this->fail('A coverage booking was linked while the kill switch was off.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('care_bookings', 0);
        }
    }

    public function test_only_active_profiles_can_join_a_family_approved_roster(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $caregiver->caregiverProfile()->update(['status' => 'draft']);

        $this->expectException(ValidationException::class);
        app(ContinuousCoverageRosterService::class)->familyApprove($this->plan($family), $family, $caregiver);
    }

    public function test_paused_removed_and_unavailable_backups_receive_no_replacement_offer(): void
    {
        $family = $this->family();
        $primary = $this->caregiver(['email' => 'availability-primary@example.com']);
        $paused = $this->caregiver(['email' => 'paused-backup@example.com']);
        $removed = $this->caregiver(['email' => 'removed-backup@example.com']);
        $unavailable = $this->caregiver(['email' => 'unavailable-backup@example.com']);
        $available = $this->caregiver(['email' => 'available-backup@example.com']);
        $shift = $this->confirmedShift($family, $primary);
        $roster = app(ContinuousCoverageRosterService::class);

        $members = [];
        foreach ([$paused, $removed, $unavailable, $available] as $candidate) {
            $members[$candidate->id] = $roster->caregiverAccept(
                $roster->familyApprove($shift->plan, $family, $candidate, ContinuousCoverageRosterMember::ROLE_BACKUP, true),
                $candidate,
            );
        }
        $roster->pause($members[$paused->id], $family);
        $roster->remove($members[$removed->id], $family);

        $localStart = $shift->scheduled_start_at->copy()->setTimezone($shift->plan->timezone);
        $unavailable->caregiverProfile->availabilities()->create([
            'day_of_week' => $localStart->dayOfWeek,
            'start_time' => '00:00',
            'end_time' => '01:00',
        ]);
        $available->caregiverProfile->availabilities()->create([
            'day_of_week' => $localStart->dayOfWeek,
            'start_time' => $localStart->format('H:i'),
            'end_time' => $shift->scheduled_end_at->copy()->setTimezone($shift->plan->timezone)->format('H:i'),
        ]);

        $case = app(ContinuousCoverageReplacementService::class)->release($shift, $primary, 'Unable to cover this future shift.');

        $this->assertEqualsCanonicalizing([$available->id], $case->offers()->pluck('caregiver_user_id')->all());
    }

    public function test_lane_offer_expires_safely_and_returns_future_shifts_to_uncovered(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-02 10:00:00', 'America/New_York'));
        $family = $this->family();
        $caregiver = $this->caregiver();
        $plan = $this->plan($family, ['starts_on' => '2026-08-10']);
        $template = $plan->templates()->firstOrFail();
        $roster = app(ContinuousCoverageRosterService::class);
        $member = $roster->caregiverAccept($roster->familyApprove($plan, $family, $caregiver), $caregiver);
        $roster->offerLane($template, $member, $family);

        Carbon::setTestNow(Carbon::parse('2026-08-06 10:01:00', 'America/New_York'));
        app(ContinuousCoverageOperationsService::class)->process();

        $this->assertSame(ContinuousCoverageShiftTemplate::STATUS_EXPIRED, $template->fresh()->status);
        $this->assertFalse($template->shifts()->where('status', ContinuousCoverageShift::STATUS_OFFER_PENDING)->exists());
        $this->assertDatabaseHas('continuous_coverage_events', ['event_type' => 'recurring_lane_offer_expired']);
    }

    public function test_expired_replacement_offer_cannot_be_accepted_and_leaves_a_visible_gap(): void
    {
        $family = $this->family();
        $primary = $this->caregiver();
        $backup = $this->caregiver();
        $shift = $this->confirmedShift($family, $primary);
        $roster = app(ContinuousCoverageRosterService::class);
        $roster->caregiverAccept(
            $roster->familyApprove($shift->plan, $family, $backup, ContinuousCoverageRosterMember::ROLE_BACKUP, true),
            $backup,
        );
        $case = app(ContinuousCoverageReplacementService::class)->release(
            $shift,
            $primary,
            'Unable to attend this future visit.',
        );
        $offer = $case->offers()->where('caregiver_user_id', $backup->id)->firstOrFail();
        $offer->forceFill(['expires_at' => now()->subMinute()])->save();

        app(ContinuousCoverageOperationsService::class)->process();

        $this->assertSame(ContinuousCoverageShiftOffer::STATUS_EXPIRED, $offer->fresh()->status);
        $this->assertSame(ContinuousCoverageReplacementCase::STATUS_UNRESOLVED, $case->fresh()->status);
        $this->assertSame(ContinuousCoverageShift::STATUS_REPLACEMENT_NEEDED, $shift->fresh()->status);
        $this->assertDatabaseHas('continuous_coverage_events', [
            'continuous_coverage_shift_id' => $shift->id,
            'event_type' => 'replacement_offer_expired',
        ]);
        try {
            app(ContinuousCoverageReplacementService::class)->respond($offer->fresh(), $backup, true);
            $this->fail('An expired replacement offer was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('offer', $exception->errors());
        }
    }

    public function test_family_history_billing_and_shift_details_use_linked_booking_data(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $shift = $this->confirmedShift($family, $caregiver);
        $booking = app(ContinuousCoverageBookingAdapter::class)->linkConfirmedShift($shift);
        $booking->forceFill([
            'status' => CareBooking::STATUS_COMPLETED,
            'started_at' => $shift->scheduled_start_at,
            'completed_at' => $shift->scheduled_end_at,
            'worked_minutes' => $shift->scheduled_minutes,
        ])->save();
        CareBookingPayment::query()->create([
            'care_booking_id' => $booking->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBookingPayment::STATUS_CAPTURED,
            'currency' => 'usd',
            'amount_captured_cents' => 10000,
            'amount_refunded_cents' => 2000,
            'caregiver_amount_cents' => 7000,
        ]);
        $shift->forceFill(['status' => ContinuousCoverageShift::STATUS_COMPLETED, 'completed_at' => $booking->completed_at])->save();

        Livewire::actingAs($family)
            ->test(ContinuousCoverageShow::class, ['coveragePlan' => $shift->continuous_coverage_plan_id])
            ->set('tab', 'billing')
            ->set('billingPeriod', 'all')
            ->assertSee('$80.00')
            ->call('openShift', $shift->id)
            ->assertSee('Coverage shift #'.$shift->id)
            ->assertSee('123 Main St')
            ->assertSee('Approved actual time')
            ->set('tab', 'history')
            ->assertSee('View details');
    }

    public function test_team_invitation_redacts_recipient_and_exact_address_and_is_deduplicated(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $plan = $this->plan($family);
        $member = app(ContinuousCoverageRosterService::class)->familyApprove($plan, $family, $caregiver);
        $query = $caregiver->notificationDeliveries()->where('event_key', 'continuous_coverage_team_invitation');
        $before = $query->count();

        app(ContinuousCoverageNotificationService::class)->teamInvitation($member);
        app(ContinuousCoverageNotificationService::class)->teamInvitation($member);

        $this->assertSame($before, $query->count());
        $this->assertGreaterThan(0, $before);
        foreach ($query->get() as $delivery) {
            $payload = json_encode($delivery->payload);
            $this->assertStringNotContainsString('Barbara Example', $payload);
            $this->assertStringNotContainsString('123 Main St', $payload);
            $this->assertStringContainsString('Durham', $payload);
        }
    }

    public function test_effective_dated_schedule_change_preserves_old_shifts_and_builds_a_new_version(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-02 10:00:00', 'America/New_York'));
        $family = $this->family();
        $plan = $this->plan($family, ['starts_on' => '2026-08-03']);
        $effectiveOn = '2026-08-10';
        $beforeEffective = $plan->shifts()->where('scheduled_start_at', '<', Carbon::parse($effectiveOn, $plan->timezone)->setTimezone(config('app.timezone')))->firstOrFail();
        $beforeSnapshot = $beforeEffective->getRawOriginal();
        $supersededIds = $plan->shifts()->where('scheduled_start_at', '>=', Carbon::parse($effectiveOn, $plan->timezone)->setTimezone(config('app.timezone')))->pluck('id');

        app(ContinuousCoverageScheduleService::class)->replaceFutureSchedule($plan, $family, $effectiveOn, [
            'coverage_pattern' => ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK,
            'shift_length_minutes' => 480,
            'coverage_start_time' => '06:00',
            'coverage_end_time' => '06:00',
            'custom_windows' => [],
        ]);

        $this->assertSame($beforeSnapshot, $beforeEffective->fresh()->getRawOriginal());
        $this->assertTrue(ContinuousCoverageShift::query()->whereKey($supersededIds)->get()->every(
            fn (ContinuousCoverageShift $shift) => $shift->status === ContinuousCoverageShift::STATUS_CANCELLED
                && data_get($shift->metadata, 'superseded_by_schedule_version') === 2
        ));
        $this->assertSame(21, $plan->templates()->where('schedule_version', 2)->count());
        $this->assertTrue($plan->shifts()->whereHas('template', fn ($query) => $query->where('schedule_version', 2))->exists());
        $this->assertDatabaseHas('continuous_coverage_events', [
            'continuous_coverage_plan_id' => $plan->id,
            'event_type' => 'schedule_changed',
        ]);
        $from = Carbon::parse($effectiveOn.' 00:00:00', $plan->timezone)->setTimezone(config('app.timezone'));
        $summary = app(ContinuousCoverageScheduleService::class)->coverageSummary(
            $plan->fresh(),
            $from,
            $from->copy()->addWeek(),
        );
        $this->assertSame(60, $summary['overlap_minutes']);
    }

    public function test_schedule_change_refuses_to_rewrite_a_prepared_future_visit(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $shift = $this->confirmedShift($family, $caregiver);
        app(ContinuousCoverageBookingAdapter::class)->linkConfirmedShift($shift);
        $effectiveOn = $shift->scheduled_start_at->copy()->setTimezone($shift->plan->timezone)->toDateString();
        $templateCount = $shift->plan->templates()->count();

        try {
            app(ContinuousCoverageScheduleService::class)->replaceFutureSchedule($shift->plan, $family, $effectiveOn, [
                'coverage_pattern' => ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK,
                'shift_length_minutes' => 480,
                'coverage_start_time' => '06:00',
                'coverage_end_time' => '06:00',
                'custom_windows' => [],
            ]);
            $this->fail('A prepared future visit was rewritten by a schedule change.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('scheduleEffectiveOn', $exception->errors());
        }

        $this->assertSame($templateCount, $shift->plan->templates()->count());
        $this->assertNotNull($shift->fresh()->care_booking_id);
        $this->assertNotSame(ContinuousCoverageShift::STATUS_CANCELLED, $shift->fresh()->status);
    }

    public function test_an_earlier_future_schedule_change_supersedes_but_preserves_the_later_version(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-02 10:00:00', 'America/New_York'));
        $family = $this->family();
        $plan = $this->plan($family, ['starts_on' => '2026-08-03']);
        $schedule = app(ContinuousCoverageScheduleService::class);
        $schedule->replaceFutureSchedule($plan, $family, '2026-08-20', [
            'coverage_pattern' => ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK,
            'shift_length_minutes' => 480,
            'coverage_start_time' => '06:00',
            'coverage_end_time' => '06:00',
            'custom_windows' => [],
        ]);
        $versionTwoIds = $plan->templates()->where('schedule_version', 2)->pluck('id');

        $schedule->replaceFutureSchedule($plan->fresh(), $family, '2026-08-10', [
            'coverage_pattern' => ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK,
            'shift_length_minutes' => 360,
            'coverage_start_time' => '07:00',
            'coverage_end_time' => '07:00',
            'custom_windows' => [],
        ]);

        $this->assertCount(21, $versionTwoIds);
        $this->assertSame(21, $plan->templates()->whereKey($versionTwoIds)->where('status', ContinuousCoverageShiftTemplate::STATUS_SUPERSEDED)->count());
        $this->assertSame(28, $plan->templates()->where('schedule_version', 3)->where('status', ContinuousCoverageShiftTemplate::STATUS_UNCOVERED)->count());
        $this->assertFalse($plan->templates()->whereKey($versionTwoIds)->get()->contains(
            fn (ContinuousCoverageShiftTemplate $template) => $template->effective_until && $template->effective_until->lt($template->effective_from)
        ));
        $before = $plan->shifts()->count();
        $schedule->generate($plan->fresh(), Carbon::parse('2026-09-01', $plan->timezone));
        $this->assertSame(0, $plan->shifts()->whereHas('template', fn ($query) => $query->where('schedule_version', 2))->where('status', '!=', ContinuousCoverageShift::STATUS_CANCELLED)->count());
        $this->assertGreaterThanOrEqual($before, $plan->shifts()->count());
        $from = Carbon::parse('2026-08-10 00:00:00', $plan->timezone)->setTimezone(config('app.timezone'));
        $summary = $schedule->coverageSummary($plan->fresh(), $from, $from->copy()->addWeek());
        $this->assertSame(10080, $summary['required_minutes']);
    }

    public function test_family_can_manage_roster_eligibility_without_changing_existing_assignments(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $plan = $this->plan($family);
        $roster = app(ContinuousCoverageRosterService::class);
        $member = $roster->familyApprove(
            $plan,
            $family,
            $caregiver,
            ContinuousCoverageRosterMember::ROLE_PRIMARY,
            false,
            [1, 3],
            ['daytime', '8_hour'],
        );
        $member = $roster->caregiverAccept($member, $caregiver);
        $acceptedAt = $member->caregiver_accepted_at;
        $member = $roster->familyApprove(
            $plan,
            $family,
            $caregiver,
            ContinuousCoverageRosterMember::ROLE_PRIMARY,
            false,
            [1, 3],
            ['daytime', '8_hour'],
        );
        $this->assertSame(ContinuousCoverageRosterMember::STATUS_ACTIVE, $member->status);
        $this->assertTrue($member->caregiver_accepted_at->equalTo($acceptedAt));
        $assignedShift = $plan->shifts()->firstOrFail();
        $assignedShift->forceFill([
            'assigned_caregiver_user_id' => $caregiver->id,
            'status' => ContinuousCoverageShift::STATUS_CONFIRMED,
            'caregiver_accepted_at' => now(),
            'family_confirmed_at' => now(),
            'confirmed_at' => now(),
        ])->save();
        $beforeAssignment = $assignedShift->getRawOriginal();

        $updated = $roster->updatePreferences(
            $member,
            $family,
            ContinuousCoverageRosterMember::ROLE_BACKUP,
            true,
            [2, 4],
            ['overnight', '12_hour'],
        );

        $this->assertSame(ContinuousCoverageRosterMember::ROLE_BACKUP, $updated->role);
        $this->assertTrue($updated->replacement_opt_in);
        $this->assertSame([2, 4], $updated->eligible_days);
        $this->assertSame(['overnight', '12_hour'], $updated->eligible_shift_types);
        $this->assertSame($beforeAssignment, $assignedShift->fresh()->getRawOriginal());
        $this->assertDatabaseHas('continuous_coverage_events', [
            'continuous_coverage_plan_id' => $plan->id,
            'event_type' => 'caregiver_eligibility_updated',
        ]);

        $roster->pause($updated, $family);
        $this->assertSame(ContinuousCoverageRosterMember::STATUS_PAUSED, $updated->fresh()->status);
        $roster->resume($updated->fresh(), $family);
        $this->assertSame(ContinuousCoverageRosterMember::STATUS_ACTIVE, $updated->fresh()->status);
    }

    public function test_recurring_lane_must_match_family_approved_member_eligibility(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $plan = $this->plan($family);
        $template = $plan->templates()->where('day_of_week', 1)->firstOrFail();
        $roster = app(ContinuousCoverageRosterService::class);
        $member = $roster->caregiverAccept(
            $roster->familyApprove($plan, $family, $caregiver, eligibleDays: [2]),
            $caregiver,
        );

        try {
            $roster->offerLane($template, $member, $family);
            $this->fail('A recurring lane outside the approved days was offered.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('caregiver', $exception->errors());
        }

        $this->assertSame(ContinuousCoverageShiftTemplate::STATUS_UNCOVERED, $template->fresh()->status);
        Livewire::actingAs($family)
            ->test(ContinuousCoverageShow::class, ['coveragePlan' => $plan->id])
            ->set('tab', 'team')
            ->assertSee('No eligible caregiver for this lane');
    }

    public function test_family_cannot_confirm_a_stale_or_inactive_winning_replacement_offer(): void
    {
        $family = $this->family();
        $primary = $this->caregiver(['email' => 'stale-primary@example.com']);
        $backup = $this->caregiver(['email' => 'stale-backup@example.com']);
        $shift = $this->confirmedShift($family, $primary);
        $roster = app(ContinuousCoverageRosterService::class);
        $member = $roster->caregiverAccept(
            $roster->familyApprove($shift->plan, $family, $backup),
            $backup,
        );
        $replacements = app(ContinuousCoverageReplacementService::class);
        $case = $replacements->release($shift, $primary, 'Unable to attend this future visit.');
        $offer = $case->offers()->where('caregiver_user_id', $backup->id)->firstOrFail();
        $replacements->respond($offer, $backup, true);
        $roster->pause($member->fresh(), $family);

        try {
            $replacements->familyConfirm($case->fresh(), $family);
            $this->fail('A replacement for an inactive roster member was confirmed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('replacement', $exception->errors());
        }

        $this->assertSame(ContinuousCoverageShift::STATUS_AWAITING_FAMILY, $shift->fresh()->status);
        $this->assertNull($shift->fresh()->assigned_caregiver_user_id);
    }

    public function test_released_booking_cancellation_failure_is_preserved_for_admin_recovery(): void
    {
        $family = $this->family();
        $primary = $this->caregiver(['email' => 'payment-primary@example.com']);
        $backup = $this->caregiver(['email' => 'payment-backup@example.com']);
        $shift = $this->confirmedShift($family, $primary);
        $booking = app(ContinuousCoverageBookingAdapter::class)->linkConfirmedShift($shift);
        CareBookingPayment::query()->create([
            'care_booking_id' => $booking->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $primary->id,
            'status' => CareBookingPayment::STATUS_AUTHORIZED,
            'currency' => 'usd',
            'stripe_payment_intent_id' => 'pi_test_release_attention',
        ]);
        $roster = app(ContinuousCoverageRosterService::class);
        $roster->caregiverAccept($roster->familyApprove($shift->plan, $family, $backup), $backup);
        $payments = \Mockery::mock(app(BookingPaymentService::class));
        $payments->shouldReceive('cancelForBooking')
            ->once()
            ->andThrow(new \RuntimeException('Simulated local gateway failure.'));
        $this->app->instance(BookingPaymentService::class, $payments);

        $case = app(ContinuousCoverageReplacementService::class)->release(
            $shift->fresh(),
            $primary,
            'Unable to attend this future visit.',
        );

        $this->assertSame(CareBooking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame($booking->id, data_get($case->shift->fresh()->metadata, 'released_booking_payment_attention.care_booking_id'));
        $this->assertDatabaseHas('continuous_coverage_events', [
            'continuous_coverage_shift_id' => $shift->id,
            'event_type' => 'released_booking_payment_attention',
        ]);
        $this->assertDatabaseHas('care_booking_events', [
            'care_booking_id' => $booking->id,
            'event_type' => 'continuous_coverage_shift_released',
        ]);
    }

    public function test_marketplace_application_requires_family_opt_in_and_separate_approval_and_acceptance(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $plan = $this->plan($family);
        $roster = app(ContinuousCoverageRosterService::class);

        try {
            $roster->apply($plan, $caregiver);
            $this->fail('A caregiver applied while the family had applications disabled.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('application', $exception->errors());
        }

        $plan = $roster->setMarketplaceApplications($plan, $family, true);
        Livewire::actingAs($caregiver)
            ->test(CaregiverCoverageIndex::class)
            ->set('tab', 'offers')
            ->assertSee('Coverage plans accepting applications')
            ->assertSee('Durham')
            ->assertSee(app(ContinuousCoveragePricingService::class)->caregiverEarningsLabel(
                $plan,
                $caregiver,
                $plan->shift_length_minutes,
            ))
            ->assertDontSee('Barbara Example')
            ->assertDontSee('123 Main St')
            ->call('applyToPlan', $plan->id)
            ->assertSee('Applications waiting for family review');

        $member = $plan->rosterMembers()->where('caregiver_user_id', $caregiver->id)->firstOrFail();
        $this->assertSame(ContinuousCoverageRosterMember::STATUS_APPLIED, $member->status);
        $this->assertFalse($plan->shifts()->where('assigned_caregiver_user_id', $caregiver->id)->exists());
        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $family->id,
            'event_key' => 'continuous_coverage_application_received',
        ]);

        Livewire::actingAs($family)
            ->test(ContinuousCoverageShow::class, ['coveragePlan' => $plan->id])
            ->set('tab', 'team')
            ->assertSee('Caregiver applications')
            ->assertSee($caregiver->name)
            ->call('approveApplicant', $member->id);

        $member->refresh();
        $this->assertSame(ContinuousCoverageRosterMember::STATUS_FAMILY_APPROVED, $member->status);
        $this->assertNull($member->caregiver_accepted_at);
        $this->assertDatabaseHas('marketplace_notification_deliveries', [
            'user_id' => $caregiver->id,
            'event_key' => 'continuous_coverage_caregiver_approved',
        ]);

        $member = $roster->caregiverAccept($member, $caregiver);
        $this->assertTrue($member->isActive());
        $this->assertFalse($plan->shifts()->where('assigned_caregiver_user_id', $caregiver->id)->exists());

        $roster->setMarketplaceApplications($plan->fresh(), $family, false);
        $this->assertTrue($member->fresh()->isActive());
    }

    public function test_declined_coverage_application_never_becomes_a_membership_or_assignment(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $plan = $this->plan($family, ['marketplace_applications_enabled' => true]);
        $roster = app(ContinuousCoverageRosterService::class);
        $member = $roster->apply($plan, $caregiver);

        $roster->declineApplicant($member, $family);

        $this->assertSame(ContinuousCoverageRosterMember::STATUS_REMOVED, $member->fresh()->status);
        $this->assertNull($member->fresh()->family_approved_at);
        $this->assertNull($member->fresh()->caregiver_accepted_at);
        $this->assertFalse($plan->shifts()->where('assigned_caregiver_user_id', $caregiver->id)->exists());
        try {
            $roster->caregiverAccept($member->fresh(), $caregiver);
            $this->fail('A declined applicant joined the care team.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invitation', $exception->errors());
        }
    }

    public function test_split_weekly_availability_can_cover_an_overnight_replacement(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-02 10:00:00', 'America/New_York'));
        $family = $this->family();
        $primary = $this->caregiver();
        $backup = $this->caregiver();
        $plan = $this->plan($family, ['starts_on' => '2026-08-03']);
        $roster = app(ContinuousCoverageRosterService::class);

        $primaryMember = $roster->caregiverAccept(
            $roster->familyApprove($plan, $family, $primary, ContinuousCoverageRosterMember::ROLE_PRIMARY, false),
            $primary,
        );
        $template = $plan->templates()
            ->where('day_of_week', 1)
            ->where('starts_at', '19:00')
            ->firstOrFail();
        $roster->offerLane($template, $primaryMember, $family);
        $roster->acceptLane($template->fresh(), $primary);
        $shift = $template->shifts()->where('scheduled_start_at', '>', now())->firstOrFail();

        $backup->caregiverProfile->availabilities()->createMany([
            ['day_of_week' => 1, 'start_time' => '19:00', 'end_time' => '23:59'],
            ['day_of_week' => 2, 'start_time' => '00:00', 'end_time' => '07:00'],
        ]);
        $roster->caregiverAccept(
            $roster->familyApprove(
                $plan,
                $family,
                $backup,
                ContinuousCoverageRosterMember::ROLE_BACKUP,
                true,
                [1],
                ['overnight', '12_hour'],
            ),
            $backup,
        );

        $case = app(ContinuousCoverageReplacementService::class)->release(
            $shift,
            $primary,
            'Unable to cover this overnight visit.',
        );

        $this->assertTrue($case->offers()->where('caregiver_user_id', $backup->id)->exists());
    }

    public function test_family_can_retry_an_unresolved_gap_after_a_new_approved_backup_joins(): void
    {
        $family = $this->family();
        $primary = $this->caregiver();
        $backup = $this->caregiver();
        $shift = $this->confirmedShift($family, $primary);
        $replacements = app(ContinuousCoverageReplacementService::class);
        $case = $replacements->release($shift, $primary, 'Unable to attend this future visit.');
        $this->assertSame(ContinuousCoverageReplacementCase::STATUS_UNRESOLVED, $case->fresh()->status);

        $roster = app(ContinuousCoverageRosterService::class);
        $roster->caregiverAccept(
            $roster->familyApprove(
                $shift->plan,
                $family,
                $backup,
                ContinuousCoverageRosterMember::ROLE_BACKUP,
                true,
            ),
            $backup,
        );

        $offers = $replacements->retryMatching($case->fresh(), $family);
        $this->assertCount(1, $offers);
        $this->assertSame($backup->id, $offers->first()->caregiver_user_id);
        $this->assertSame(ContinuousCoverageReplacementCase::STATUS_OPEN, $case->fresh()->status);
        $this->assertCount(0, $replacements->retryMatching($case->fresh(), $family));
        $this->assertSame(1, $case->offers()->where('caregiver_user_id', $backup->id)->count());
    }

    public function test_shift_notifications_deep_link_and_keep_earnings_private_by_role(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $shift = $this->confirmedShift($family, $caregiver);
        $notifications = app(ContinuousCoverageNotificationService::class);

        $notifications->shiftConfirmed($shift);
        $notifications->shiftConfirmed($shift);

        $familyDeliveries = $family->notificationDeliveries()
            ->where('event_key', 'continuous_coverage_shift_confirmed')
            ->get();
        $caregiverDeliveries = $caregiver->notificationDeliveries()
            ->where('event_key', 'continuous_coverage_shift_confirmed')
            ->get();
        $this->assertNotEmpty($familyDeliveries);
        $this->assertNotEmpty($caregiverDeliveries);
        $this->assertSame(
            $familyDeliveries->pluck('channel')->unique()->count(),
            $familyDeliveries->count(),
            'Family notification delivery was duplicated per channel.',
        );
        $this->assertSame(
            $caregiverDeliveries->pluck('channel')->unique()->count(),
            $caregiverDeliveries->count(),
            'Caregiver notification delivery was duplicated per channel.',
        );

        $familyPayload = json_encode($familyDeliveries->pluck('payload')->all());
        $caregiverPayload = json_encode($caregiverDeliveries->pluck('payload')->all());
        $this->assertStringNotContainsString('Estimated caregiver earnings', $familyPayload);
        $this->assertStringNotContainsString('caregiver_amount_cents', $familyPayload);
        $this->assertStringNotContainsString('123 Main St', $familyPayload);
        $this->assertStringNotContainsString('123 Main St', $caregiverPayload);
        $this->assertStringContainsString('Estimated caregiver earnings', $caregiverPayload);
        $this->assertStringContainsString('coverage-shift-'.$shift->id, $caregiverPayload);
        $this->assertStringContainsString('selectedShift='.$shift->id, $familyPayload);
        $this->assertStringContainsString('tab=calendar', $familyPayload);
    }

    public function test_active_care_team_member_can_request_multiple_open_lanes_without_being_assigned(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-03 12:00:00', 'America/New_York'));
        $family = $this->family();
        $caregiver = $this->caregiver();
        $plan = $this->plan($family);
        $roster = app(ContinuousCoverageRosterService::class);
        $member = $roster->caregiverAccept(
            $roster->familyApprove($plan, $family, $caregiver, ContinuousCoverageRosterMember::ROLE_PRIMARY, true),
            $caregiver,
        );
        $lanes = $plan->templates()->orderBy('day_of_week')->orderBy('starts_at')->limit(2)->get();

        Livewire::actingAs($caregiver)
            ->test(CaregiverCoverageIndex::class)
            ->set('tab', 'offers')
            ->assertSee('Open recurring lanes')
            ->assertSee('The family reviews your request before any future visits are assigned.')
            ->assertSeeHtml('wire:model="laneRequestSelections.'.$plan->id.'.'.$lanes->first()->id.'"')
            ->assertSeeHtml('wire:model="laneRequestSelections.'.$plan->id.'.'.$lanes->last()->id.'"')
            ->set('laneRequestSelections.'.$plan->id, $lanes->mapWithKeys(fn ($lane): array => [$lane->id => true])->all())
            ->call('requestOpenLanes', $plan->id)
            ->assertHasNoErrors()
            ->assertSee('Your recurring lane requests')
            ->assertSee('Nothing is added to your schedule unless the family approves it.');

        $this->assertSame(2, ContinuousCoverageLaneRequest::query()
            ->where('roster_member_id', $member->id)
            ->where('status', ContinuousCoverageLaneRequest::STATUS_PENDING)
            ->count());
        $this->assertSame(2, $plan->templates()->whereKey($lanes->modelKeys())->where('status', ContinuousCoverageShiftTemplate::STATUS_UNCOVERED)->count());
        $this->assertSame(0, $plan->shifts()->whereIn('shift_template_id', $lanes->modelKeys())->whereNotNull('assigned_caregiver_user_id')->count());
        $this->assertGreaterThan(0, $family->notificationDeliveries()->where('event_key', 'continuous_coverage_lane_requested')->count());
    }

    public function test_family_approval_of_a_lane_request_confirms_future_shifts_and_keeps_bookings_deferred(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-03 12:00:00', 'America/New_York'));
        $family = $this->family();
        $caregiver = $this->caregiver();
        $plan = $this->plan($family);
        $roster = app(ContinuousCoverageRosterService::class);
        $member = $roster->caregiverAccept(
            $roster->familyApprove($plan, $family, $caregiver, ContinuousCoverageRosterMember::ROLE_PRIMARY, true),
            $caregiver,
        );
        $lane = $plan->templates()->orderBy('day_of_week')->orderBy('starts_at')->firstOrFail();
        $request = app(ContinuousCoverageLaneRequestService::class)->request($plan, $caregiver, [$lane->id])->firstOrFail();

        Livewire::actingAs($family)
            ->test(ContinuousCoverageShow::class, ['coveragePlan' => $plan->id])
            ->set('tab', 'team')
            ->assertSee('Recurring lanes awaiting your approval')
            ->assertSee($caregiver->name)
            ->call('approveLaneRequest', $request->id)
            ->assertHasNoErrors()
            ->assertSee('Future visits are now confirmed with this caregiver.');

        $this->assertSame(ContinuousCoverageLaneRequest::STATUS_APPROVED, $request->fresh()->status);
        $this->assertSame(ContinuousCoverageShiftTemplate::STATUS_ACTIVE, $lane->fresh()->status);
        $this->assertSame($member->id, $lane->fresh()->roster_member_id);
        $this->assertGreaterThan(0, $lane->shifts()->where('status', ContinuousCoverageShift::STATUS_CONFIRMED)->count());
        $this->assertSame(0, $lane->shifts()->whereNotNull('care_booking_id')->count());
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertGreaterThan(0, $caregiver->notificationDeliveries()->where('event_key', 'continuous_coverage_lane_request_approved')->count());
    }

    public function test_confirmed_future_coverage_is_visible_in_my_visits_before_booking_preparation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-03 12:00:00', 'America/New_York'));
        $family = $this->family();
        $caregiver = $this->caregiver();
        $plan = $this->plan($family, ['title' => 'Dad\'s care', 'starts_on' => '2026-08-07']);
        $roster = app(ContinuousCoverageRosterService::class);
        $member = $roster->caregiverAccept(
            $roster->familyApprove($plan, $family, $caregiver, ContinuousCoverageRosterMember::ROLE_PRIMARY, true),
            $caregiver,
        );
        $lane = $plan->templates()->where('day_of_week', 5)->orderBy('starts_at')->firstOrFail();
        $roster->offerLane($lane, $member, $family);
        $roster->acceptLane($lane->fresh(), $caregiver);
        $shift = $lane->shifts()->where('scheduled_start_at', '>=', now())->orderBy('scheduled_start_at')->firstOrFail();
        $confirmedShiftCount = $lane->shifts()
            ->where('scheduled_start_at', '>=', now())
            ->where('status', ContinuousCoverageShift::STATUS_CONFIRMED)
            ->count();

        $this->assertTrue($shift->scheduled_start_at->gt(now()->addHours(48)));
        $this->assertNull($shift->care_booking_id);
        $this->assertDatabaseCount('care_bookings', 0);

        Livewire::actingAs($caregiver)
            ->test(CaregiverShiftsIndex::class)
            ->assertViewHas('visitTimeline', fn ($timeline): bool => $timeline->first()['coverage_shift']?->id === $shift->id
                && $timeline->first()['kind'] === 'coverage'
                && $timeline->total() === $confirmedShiftCount)
            ->assertViewHas('nextVisit', fn (?array $visit): bool => $visit !== null && $visit['coverage_shift']?->id === $shift->id)
            ->assertViewHas('counts', fn (array $counts): bool => $counts['scheduled'] === $confirmedShiftCount)
            ->assertSee('Your visits, in one timeline.')
            ->assertSee('Dad\'s care')
            ->assertSee('No payment is processed this early.')
            ->assertSeeHtml('id="coverage-commitment-'.$shift->id.'"');

        Livewire::actingAs($caregiver)
            ->test(DashboardHome::class)
            ->assertViewHas('caregiverData', fn (array $data): bool => $data['next_visit']['coverage_shift']?->id === $shift->id
                && $data['quick_visits']->first()['coverage_shift']?->id === $shift->id)
            ->assertSee('Dad\'s care')
            ->assertSee('CONTINUOUS COVERAGE');
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);

        Livewire::actingAs($caregiver)
            ->test(CaregiverShiftsIndex::class)
            ->set('status', CareBooking::STATUS_COMPLETED)
            ->assertViewHas('visitTimeline', fn ($timeline): bool => $timeline->isEmpty())
            ->assertSee('No visits match this filter.');

        $booking = app(ContinuousCoverageBookingAdapter::class)->linkConfirmedShift($shift);
        Livewire::actingAs($caregiver)
            ->test(CaregiverShiftsIndex::class)
            ->assertViewHas('visitTimeline', function ($timeline) use ($booking, $shift, $confirmedShiftCount): bool {
                $items = $timeline->getCollection();

                return $timeline->total() === $confirmedShiftCount
                    && $items->contains(fn (array $visit): bool => $visit['booking']?->id === $booking->id)
                    && ! $items->contains(fn (array $visit): bool => $visit['coverage_shift']?->id === $shift->id)
                    && $items->pluck('scheduled_start_at')->values()->all() === $items->pluck('scheduled_start_at')->sort()->values()->all();
            })
            ->assertSee('Continuous coverage:')
            ->assertSee('Start visit');
    }

    public function test_approving_one_caregiver_request_does_not_allow_a_competing_request_to_overwrite_it(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-03 12:00:00', 'America/New_York'));
        $family = $this->family();
        $firstCaregiver = $this->caregiver(['email' => 'first-lane@example.com']);
        $secondCaregiver = $this->caregiver(['email' => 'second-lane@example.com']);
        $plan = $this->plan($family);
        $roster = app(ContinuousCoverageRosterService::class);
        foreach ([$firstCaregiver, $secondCaregiver] as $caregiver) {
            $roster->caregiverAccept(
                $roster->familyApprove($plan, $family, $caregiver, ContinuousCoverageRosterMember::ROLE_PRIMARY, true),
                $caregiver,
            );
        }
        $lane = $plan->templates()->firstOrFail();
        $requests = app(ContinuousCoverageLaneRequestService::class);
        $firstRequest = $requests->request($plan, $firstCaregiver, [$lane->id])->firstOrFail();
        $secondRequest = $requests->request($plan, $secondCaregiver, [$lane->id])->firstOrFail();

        $this->assertTrue($requests->approve($firstRequest, $family));
        $this->assertFalse($requests->approve($secondRequest, $family));

        $this->assertSame(ContinuousCoverageLaneRequest::STATUS_APPROVED, $firstRequest->fresh()->status);
        $this->assertSame(ContinuousCoverageLaneRequest::STATUS_NOT_SELECTED, $secondRequest->fresh()->status);
        $this->assertSame($firstCaregiver->id, $lane->fresh()->rosterMember->caregiver_user_id);
        $this->assertGreaterThan(0, $secondCaregiver->notificationDeliveries()->where('event_key', 'continuous_coverage_lane_request_not_selected')->count());
    }

    public function test_caregiver_can_withdraw_and_family_can_decline_lane_requests_without_schedule_changes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-03 12:00:00', 'America/New_York'));
        $family = $this->family();
        $caregiver = $this->caregiver();
        $plan = $this->plan($family);
        $roster = app(ContinuousCoverageRosterService::class);
        $roster->caregiverAccept(
            $roster->familyApprove($plan, $family, $caregiver, ContinuousCoverageRosterMember::ROLE_PRIMARY, true),
            $caregiver,
        );
        [$withdrawnLane, $declinedLane] = $plan->templates()->orderBy('id')->limit(2)->get()->all();
        $service = app(ContinuousCoverageLaneRequestService::class);
        $requests = $service->request($plan, $caregiver, [$withdrawnLane->id, $declinedLane->id]);

        $service->withdraw($requests->firstWhere('shift_template_id', $withdrawnLane->id), $caregiver);
        $service->decline($requests->firstWhere('shift_template_id', $declinedLane->id), $family);

        $this->assertSame(ContinuousCoverageLaneRequest::STATUS_WITHDRAWN, $requests->firstWhere('shift_template_id', $withdrawnLane->id)->fresh()->status);
        $this->assertSame(ContinuousCoverageLaneRequest::STATUS_DECLINED, $requests->firstWhere('shift_template_id', $declinedLane->id)->fresh()->status);
        $this->assertSame(ContinuousCoverageShiftTemplate::STATUS_UNCOVERED, $withdrawnLane->fresh()->status);
        $this->assertSame(ContinuousCoverageShiftTemplate::STATUS_UNCOVERED, $declinedLane->fresh()->status);
        $this->assertSame(0, $plan->shifts()->whereNotNull('assigned_caregiver_user_id')->count());
    }

    public function test_saved_profile_availability_is_advisory_for_open_lane_requests(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-03 12:00:00', 'America/New_York'));
        $family = $this->family();
        $caregiver = $this->caregiver();
        $caregiver->caregiverProfile->availabilities()->create([
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '09:00',
        ]);
        $plan = $this->plan($family);
        $roster = app(ContinuousCoverageRosterService::class);
        $roster->caregiverAccept(
            $roster->familyApprove($plan, $family, $caregiver, ContinuousCoverageRosterMember::ROLE_PRIMARY, true),
            $caregiver,
        );
        $lane = $plan->templates()->where('day_of_week', 2)->firstOrFail();
        $otherLane = $plan->templates()->where('id', '!=', $lane->id)->firstOrFail();

        Livewire::actingAs($caregiver)
            ->test(CaregiverCoverageIndex::class)
            ->set('tab', 'offers')
            ->assertSee('This is outside your saved profile availability.')
            ->set('laneRequestSelections.'.$plan->id.'.'.$lane->id, true)
            ->call('requestOpenLanes', $plan->id)
            ->assertHasNoErrors();

        $this->assertDatabaseCount('continuous_coverage_lane_requests', 1);
        $this->assertDatabaseHas('continuous_coverage_lane_requests', [
            'shift_template_id' => $lane->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => ContinuousCoverageLaneRequest::STATUS_PENDING,
        ]);
        $this->assertDatabaseMissing('continuous_coverage_lane_requests', [
            'shift_template_id' => $otherLane->id,
            'caregiver_user_id' => $caregiver->id,
        ]);
    }

    public function test_family_history_filters_disputed_missed_and_replaced_coverage_visits(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $plan = $this->plan($family);
        $roster = app(ContinuousCoverageRosterService::class);
        $member = $roster->caregiverAccept(
            $roster->familyApprove($plan, $family, $caregiver, ContinuousCoverageRosterMember::ROLE_PRIMARY, true),
            $caregiver,
        );
        $templates = $plan->templates()->limit(3)->get();
        $shifts = collect();
        foreach ($templates as $template) {
            $roster->offerLane($template, $member, $family);
            $roster->acceptLane($template->fresh(), $caregiver);
            $shift = $template->shifts()->orderBy('scheduled_start_at')->firstOrFail();
            $booking = app(ContinuousCoverageBookingAdapter::class)->linkConfirmedShift($shift);
            $shifts->push([$shift->fresh(), $booking]);
        }

        [$disputedShift, $disputedBooking] = $shifts[0];
        $disputedBooking->forceFill([
            'status' => CareBooking::STATUS_DISPUTED,
            'dispute_opened_at' => now(),
        ])->save();
        CareBookingPayment::query()->create([
            'care_booking_id' => $disputedBooking->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareBookingPayment::STATUS_CAPTURED,
            'currency' => 'usd',
            'amount_captured_cents' => 9000,
            'amount_refunded_cents' => 0,
            'caregiver_amount_cents' => 7000,
        ]);
        $disputedShift->forceFill(['status' => ContinuousCoverageShift::STATUS_COMPLETED, 'completed_at' => now()])->save();

        [$missedShift, $missedBooking] = $shifts[1];
        $missedBooking->forceFill([
            'status' => CareBooking::STATUS_CANCELLED,
            'no_show_flag' => true,
            'cancelled_at' => now(),
        ])->save();
        $missedShift->forceFill(['status' => ContinuousCoverageShift::STATUS_CANCELLED, 'cancelled_at' => now()])->save();

        [$replacedShift] = $shifts[2];
        $replacedShift->forceFill(['status' => ContinuousCoverageShift::STATUS_COMPLETED, 'completed_at' => now()])->save();
        ContinuousCoverageReplacementCase::query()->create([
            'continuous_coverage_shift_id' => $replacedShift->id,
            'original_caregiver_user_id' => $caregiver->id,
            'status' => ContinuousCoverageReplacementCase::STATUS_RESOLVED,
            'reason' => 'Replacement completed.',
            'opened_at' => now()->subDay(),
            'resolved_at' => now(),
        ]);

        foreach ([
            'disputed' => $disputedShift->id,
            'missed' => $missedShift->id,
            'replaced' => $replacedShift->id,
        ] as $status => $expectedShiftId) {
            Livewire::actingAs($family)
                ->test(ContinuousCoverageShow::class, ['coveragePlan' => $plan->id])
                ->set('tab', 'history')
                ->set('historyStatus', $status)
                ->assertViewHas('history', fn ($history): bool => $history->total() === 1
                    && (int) $history->first()->id === $expectedShiftId);
        }

        Livewire::actingAs($family)
            ->test(ContinuousCoverageShow::class, ['coveragePlan' => $plan->id])
            ->set('tab', 'history')
            ->set('historyBillingStatus', CareBookingPayment::STATUS_CAPTURED)
            ->assertViewHas('history', fn ($history): bool => $history->total() === 1
                && (int) $history->first()->id === $disputedShift->id);
    }

    public function test_disabled_feature_blocks_new_handoffs_releases_and_notifications(): void
    {
        $family = $this->family();
        $caregiver = $this->caregiver();
        $shift = $this->confirmedShift($family, $caregiver);
        $deliveryCount = $caregiver->notificationDeliveries()->count();
        config()->set('marketplace.continuous_coverage.enabled', false);

        app(ContinuousCoverageNotificationService::class)->shiftReminder($shift);
        $this->assertSame($deliveryCount, $caregiver->notificationDeliveries()->count());

        try {
            app(ContinuousCoverageReplacementService::class)->release(
                $shift,
                $caregiver,
                'Cannot attend this future coverage shift.',
            );
            $this->fail('A coverage shift was released while the kill switch was off.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('coverage', $exception->errors());
        }
        $this->assertSame(ContinuousCoverageShift::STATUS_CONFIRMED, $shift->fresh()->status);

        Livewire::actingAs($caregiver)
            ->test(CaregiverCoverageIndex::class)
            ->set('handoffNotes.'.$shift->id, 'A new handoff note')
            ->call('saveHandoff', $shift->id)
            ->assertHasErrors('coverage');
        $this->assertDatabaseMissing('continuous_coverage_handoffs', [
            'continuous_coverage_shift_id' => $shift->id,
            'notes' => 'A new handoff note',
        ]);
    }

    public function test_admin_recovery_screen_exposes_operational_state_without_message_contents(): void
    {
        $family = $this->family();
        $shift = $this->plan($family)->shifts()->firstOrFail();
        $shift->forceFill([
            'status' => ContinuousCoverageShift::STATUS_PAYMENT_ATTENTION,
            'metadata' => [
                'released_booking_payment_attention' => [
                    'care_booking_id' => 9876,
                    'payment_status' => 'authorized',
                ],
            ],
        ])->save();
        $family->notificationDeliveries()->create([
            'event_key' => 'continuous_coverage_payment_attention',
            'channel' => 'email',
            'status' => 'failed',
            'dedupe_key' => 'coverage-admin-test:email',
            'payload' => ['private_body' => 'Do not display this private message.'],
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\ContinuousCoverageIndex::class)
            ->assertSee('Released booking payment review')
            ->assertSee('Prior visit #9876')
            ->assertSee('Notification failures')
            ->assertSee($family->name)
            ->assertDontSee('Do not display this private message.');
    }

    private function family(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => 'family'], $attributes));
    }

    private function caregiver(array $attributes = []): User
    {
        $caregiver = User::factory()->create(array_merge(['role' => 'caregiver'], $attributes));
        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
        ]);

        return $caregiver;
    }

    private function makeCaregiverBrowsable(User $caregiver, array $profileAttributes = []): void
    {
        $profile = $caregiver->caregiverProfile;
        $profile->update(array_merge([
            'slug' => 'caregiver-'.$caregiver->id,
            'bio' => 'Experienced non-medical caregiver available for family care.',
            'platform_hourly_rate' => 30,
            'years_experience' => 5,
            'service_area_zip' => '27701',
            'service_radius_miles' => 20,
            'is_accepting_new_clients' => true,
        ], $profileAttributes));
        $skill = Skill::query()->create(['name' => 'Coverage skill '.$caregiver->id]);
        $language = Language::query()->create(['name' => 'Coverage language '.$caregiver->id]);
        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);
        $profile->availabilities()->create([
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '18:00',
        ]);
    }

    private function plan(User $family, array $overrides = []): ContinuousCoveragePlan
    {
        return app(ContinuousCoverageScheduleService::class)->createPlan($family, $this->planData($overrides));
    }

    private function confirmedShift(User $family, User $caregiver, array $overrides = []): ContinuousCoverageShift
    {
        $plan = $this->plan($family, array_merge(['starts_on' => now()->addDay()->toDateString()], $overrides));
        $roster = app(ContinuousCoverageRosterService::class);
        $member = $roster->familyApprove($plan, $family, $caregiver, ContinuousCoverageRosterMember::ROLE_PRIMARY, true);
        $member = $roster->caregiverAccept($member, $caregiver);
        $template = $plan->templates()->where('day_of_week', now($plan->timezone)->addDay()->dayOfWeek)->firstOrFail();
        $roster->offerLane($template, $member, $family);
        $roster->acceptLane($template->fresh(), $caregiver);

        return $template->shifts()->where('scheduled_start_at', '>=', now())->orderBy('scheduled_start_at')->firstOrFail();
    }

    private function planData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Around-the-clock care',
            'timezone' => 'America/New_York',
            'starts_on' => now('America/New_York')->addDay()->toDateString(),
            'ends_on' => null,
            'coverage_pattern' => ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK,
            'shift_length_minutes' => 720,
            'coverage_start_time' => '07:00',
            'coverage_end_time' => '07:00',
            'custom_windows' => [],
            'recipient_snapshot' => ['full_name' => 'Barbara Example', 'relationship_to_family' => 'Mother'],
            'address_snapshot' => ['address_line1' => '123 Main St', 'city' => 'Durham', 'state' => 'NC', 'zip' => '27701'],
            'task_snapshot' => [],
            'care_notes' => 'Companionship and meal support.',
            'hourly_rate' => 30,
            'replacement_confirmation_mode' => ContinuousCoveragePlan::CONFIRM_FAMILY,
        ], $overrides);
    }

    private function firstWeekShifts(ContinuousCoveragePlan $plan)
    {
        $startTime = $plan->coverage_pattern === ContinuousCoveragePlan::PATTERN_AROUND_THE_CLOCK
            ? (string) data_get($plan->metadata, 'coverage_start_time', '07:00')
            : '00:00';
        $start = Carbon::parse($plan->starts_on->toDateString().' '.$startTime, $plan->timezone)
            ->setTimezone(config('app.timezone'));
        $end = $start->copy()->addWeek();

        return $plan->shifts()->where('scheduled_start_at', '>=', $start)->where('scheduled_start_at', '<', $end)->orderBy('scheduled_start_at')->get();
    }

    private function assertContiguous($shifts): void
    {
        $this->assertGreaterThan(1, $shifts->count());
        for ($index = 1; $index < $shifts->count(); $index++) {
            $this->assertTrue(
                $shifts[$index - 1]->scheduled_end_at->equalTo($shifts[$index]->scheduled_start_at),
                'Coverage contains a gap or overlap between shift '.$shifts[$index - 1]->id.' and '.$shifts[$index]->id.'.'
            );
        }
    }
}
