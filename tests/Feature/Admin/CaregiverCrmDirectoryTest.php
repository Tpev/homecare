<?php

namespace Tests\Feature\Admin;

use App\Models\CareBooking;
use App\Models\CaregiverCrmActivity;
use App\Models\CaregiverCrmRecord;
use App\Models\CaregiverProfile;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\ContinuousCoverageEvent;
use App\Models\ContinuousCoveragePlan;
use App\Models\ContinuousCoverageReplacementCase;
use App\Models\ContinuousCoverageRosterMember;
use App\Models\ContinuousCoverageShift;
use App\Models\ContinuousCoverageShiftOffer;
use App\Models\User;
use App\Services\CaregiverCrm\CaregiverCrmDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CaregiverCrmDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $family;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Queue::fake();
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(12, 0));
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->family = User::factory()->create(['role' => 'family']);
        $this->actingAs($this->admin);
    }

    public function test_only_canonical_work_eligible_caregivers_are_listed_without_creating_crm_records(): void
    {
        $approved = $this->caregiver('Approved without availability', ['is_accepting_new_clients' => false]);
        $legacy = $this->caregiver('Legacy approved', ['screening_initial_approved_at' => null, 'screening_legacy_access' => true]);
        $ineligible = [
            $this->caregiver('Unapproved active', ['screening_initial_approved_at' => null]),
            $this->caregiver('Suspended', ['screening_suspended_at' => now()]),
            $this->caregiver('Pending', ['status' => 'pending']),
            $this->caregiver('Draft', ['status' => 'draft']),
            $this->caregiver('Rejected', ['status' => 'rejected']),
            $this->caregiver('Wrong role', [], ['role' => 'family']),
        ];
        config(['marketplace.caregiver_discovery_excluded_slugs' => [$approved->caregiverProfile->slug]]);
        $directory = app(CaregiverCrmDirectory::class);
        $board = $directory->board();

        $this->assertSame(['new' => 2, 'applied' => 0, 'hired' => 0], $board['counts']);
        $this->assertEqualsCanonicalizing([$approved->id, $legacy->id], $board['columns']['new']->pluck('user_id')->all());
        $this->assertSame(['total' => 2, 'called' => 0, 'due' => 0, 'unassigned' => 2], $board['stats']);
        foreach ($ineligible as $caregiver) {
            $this->assertNull($directory->find($caregiver->id));
        }
        $row = $directory->find($approved->id);
        $this->assertSame(0, $row['version']);
        $this->assertSame('normal', $row['priority']);
        $this->assertNull($row['phone_spoken_at']);
        $this->assertDatabaseCount('caregiver_crm_records', 0);
        $this->assertDatabaseCount('caregiver_crm_activities', 0);
        $approved->caregiverProfile->update(['screening_suspended_at' => now()]);
        $this->assertNull($directory->find($approved->id));
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_application_history_survives_rejection_withdrawal_and_request_cancellation(): void
    {
        $caregiver = $this->caregiver('Previously applied');
        foreach (['applied', 'shortlisted', 'rejected', 'withdrawn', 'not_selected'] as $status) {
            $application = $this->application($caregiver, $this->request(['status' => 'cancelled']), $status);
            $application->forceFill(['created_at' => '2026-09-01 09:00:00'])->save();
        }
        $row = app(CaregiverCrmDirectory::class)->find($caregiver->id);

        $this->assertSame('applied', $row['stage']);
        $this->assertSame(5, $row['applications_count']);
        $this->assertSame(0, $row['hires_count']);
        $this->assertSame('2026-09-01 09:00:00', $row['first_applied_at']);
    }

    public function test_retained_booking_proves_hire_after_cancellation_without_counting_multiple_sources_twice(): void
    {
        $caregiver = $this->caregiver('Hired then cancelled');
        $request = $this->request(['status' => 'cancelled', 'first_hire_at' => '2026-01-01 08:00:00']);
        $application = $this->application($caregiver, $request, 'hired');
        $booking = $this->booking($caregiver, $request, $application);
        $booking->forceFill(['status' => 'cancelled', 'created_at' => '2026-09-15 10:00:00'])->save();
        $application->update(['status' => 'not_selected']);
        $this->plan($caregiver, ['source_care_request_id' => $request->id, 'activated_at' => '2026-09-15 10:00:00']);
        $other = $this->caregiver('Other applicant');
        $this->application($other, $request, 'rejected');

        $directory = app(CaregiverCrmDirectory::class);
        $row = $directory->find($caregiver->id);
        $this->assertSame('hired', $row['stage']);
        $this->assertSame(1, $row['applications_count']);
        $this->assertSame(1, $row['hires_count']);
        $this->assertSame('2026-09-15 10:00:00', $row['first_hired_at']);
        $this->assertSame('applied', $directory->find($other->id)['stage']);
        $this->assertNull($directory->find($other->id)['first_hired_at']);
    }

    public function test_hired_application_without_timestamp_is_hired_without_inventing_a_date(): void
    {
        $caregiver = $this->caregiver('Legacy hire');
        $this->application($caregiver, $this->request(['first_hire_at' => '2025-01-01 00:00:00']), 'hired');
        $row = app(CaregiverCrmDirectory::class)->find($caregiver->id);

        $this->assertSame('hired', $row['stage']);
        $this->assertSame(1, $row['hires_count']);
        $this->assertNull($row['first_hired_at']);
    }

    public function test_generated_and_draft_rows_do_not_create_application_or_hire_milestones(): void
    {
        $caregiver = $this->caregiver('No genuine engagement');
        foreach ([
            ['status' => 'draft'],
            ['is_system_generated' => true, 'origin' => 'welcome_visit'],
            ['is_system_generated' => true, 'origin' => 'shared_regular_care'],
            ['is_system_generated' => true],
        ] as $attributes) {
            $request = $this->request($attributes);
            $application = $this->application($caregiver, $request, 'hired');
            $this->booking($caregiver, $request, $application);
        }
        $row = app(CaregiverCrmDirectory::class)->find($caregiver->id);

        $this->assertSame('new', $row['stage']);
        $this->assertSame(0, $row['applications_count']);
        $this->assertSame(0, $row['hires_count']);
    }

    public function test_activated_direct_plans_remain_hired_after_ending_but_pending_offers_do_not(): void
    {
        $hired = $this->caregiver('Accepted direct plan');
        $pending = $this->caregiver('Pending direct offer');
        $this->plan($hired, ['status' => 'ended', 'activated_at' => '2026-08-01 12:00:00', 'ended_at' => now()]);
        $this->plan($pending, ['status' => 'pending_caregiver', 'activated_at' => null, 'accepted_at' => null]);
        $directory = app(CaregiverCrmDirectory::class);

        $this->assertSame('hired', $directory->find($hired->id)['stage']);
        $this->assertSame(0, $directory->find($hired->id)['applications_count']);
        $this->assertSame(1, $directory->find($hired->id)['hires_count']);
        $this->assertSame('2026-08-01 12:00:00', $directory->find($hired->id)['first_hired_at']);
        $this->assertSame('new', $directory->find($pending->id)['stage']);
    }

    public function test_shared_plan_original_application_and_generated_occurrences_are_one_hire(): void
    {
        $caregiver = $this->caregiver('Shared care');
        $original = $this->request();
        $this->application($caregiver, $original, 'hired');
        $arrangementId = DB::table('care_arrangements')->insertGetId([
            'source_care_request_id' => $original->id, 'family_user_id' => $this->family->id,
            'title' => 'Shared care', 'schedule_slots' => '[]', 'starts_on' => '2026-10-01',
            'timezone' => 'America/New_York',
        ]);
        $child = $this->request(['is_system_generated' => true, 'origin' => 'shared_regular_care']);
        $this->application($caregiver, $child, 'hired');
        $this->plan($caregiver, ['source_care_request_id' => $child->id, 'care_arrangement_id' => $arrangementId]);
        $generated = $this->request(['is_system_generated' => true]);
        $this->booking($caregiver, $generated, $this->application($caregiver, $generated, 'hired'));
        $row = app(CaregiverCrmDirectory::class)->find($caregiver->id);

        $this->assertSame('hired', $row['stage']);
        $this->assertSame(1, $row['applications_count']);
        $this->assertSame(1, $row['hires_count']);
    }

    public function test_coverage_application_event_preserves_history_and_invitation_or_membership_is_not_hire(): void
    {
        $plan = $this->coveragePlan();
        $applicant = $this->caregiver('Coverage applicant');
        $invited = $this->caregiver('Invited team member');
        $active = $this->caregiver('Accepted team without work');
        $this->roster($plan, $applicant, 'removed');
        $this->roster($plan, $invited, 'family_approved');
        $this->roster($plan, $active, 'active');
        ContinuousCoverageEvent::create([
            'continuous_coverage_plan_id' => $plan->id, 'actor_user_id' => $applicant->id,
            'event_type' => 'coverage_application_received', 'happened_at' => '2026-09-04 10:00:00',
        ]);
        $directory = app(CaregiverCrmDirectory::class);

        $this->assertSame('applied', $directory->find($applicant->id)['stage']);
        $this->assertSame(1, $directory->find($applicant->id)['applications_count']);
        $this->assertSame('2026-09-04 10:00:00', $directory->find($applicant->id)['first_applied_at']);
        $this->assertSame('new', $directory->find($invited->id)['stage']);
        $this->assertSame('new', $directory->find($active->id)['stage']);
    }

    public function test_confirmed_coverage_shifts_count_once_per_plan_and_unconfirmed_work_is_not_hired(): void
    {
        $plan = $this->coveragePlan();
        $hired = $this->caregiver('Coverage hired');
        $unconfirmed = $this->caregiver('Awaiting family');
        $this->shift($plan, $hired, ['status' => 'cancelled']);
        $this->shift($plan, $hired);
        $this->shift($plan, $unconfirmed, ['status' => 'awaiting_family_confirmation', 'family_confirmed_at' => null, 'confirmed_at' => null]);
        $directory = app(CaregiverCrmDirectory::class);

        $this->assertSame('hired', $directory->find($hired->id)['stage']);
        $this->assertSame(1, $directory->find($hired->id)['hires_count']);
        $this->assertSame('new', $directory->find($unconfirmed->id)['stage']);
    }

    public function test_coverage_hire_history_survives_reassignment_for_original_and_confirmed_replacement(): void
    {
        $plan = $this->coveragePlan();
        $original = $this->caregiver('Original coverage caregiver');
        $replacement = $this->caregiver('Replacement caregiver');
        $unselected = $this->caregiver('Unconfirmed replacement');
        $shift = $this->shift($plan, null, ['confirmed_at' => null, 'family_confirmed_at' => null, 'caregiver_accepted_at' => null]);
        ContinuousCoverageEvent::create([
            'continuous_coverage_plan_id' => $plan->id, 'continuous_coverage_shift_id' => $shift->id,
            'actor_user_id' => $original->id, 'event_type' => 'shift_confirmed', 'happened_at' => '2026-09-01 10:00:00',
        ]);
        $case = ContinuousCoverageReplacementCase::create([
            'continuous_coverage_shift_id' => $shift->id, 'original_caregiver_user_id' => $original->id,
            'status' => 'resolved', 'opened_at' => now()->subDays(4), 'resolved_at' => now()->subDays(3),
        ]);
        $offer = ContinuousCoverageShiftOffer::create([
            'replacement_case_id' => $case->id, 'continuous_coverage_shift_id' => $shift->id,
            'roster_member_id' => $this->roster($plan, $replacement, 'active')->id,
            'caregiver_user_id' => $replacement->id, 'status' => 'accepted', 'responded_at' => now()->subDays(3),
        ]);
        $case->update(['winning_offer_id' => $offer->id]);
        ContinuousCoverageShiftOffer::create([
            'replacement_case_id' => $case->id, 'continuous_coverage_shift_id' => $shift->id,
            'roster_member_id' => $this->roster($plan, $unselected, 'active')->id,
            'caregiver_user_id' => $unselected->id, 'status' => 'not_selected',
        ]);
        $directory = app(CaregiverCrmDirectory::class);

        $this->assertSame('hired', $directory->find($original->id)['stage']);
        $this->assertSame('2026-09-01 10:00:00', $directory->find($original->id)['first_hired_at']);
        $this->assertSame(1, $directory->find($original->id)['hires_count']);
        $this->assertSame('hired', $directory->find($replacement->id)['stage']);
        $this->assertSame('new', $directory->find($unselected->id)['stage']);
    }

    public function test_spoken_tag_requires_a_past_connected_call_and_filters_follow_the_same_rule(): void
    {
        $spoken = $this->caregiver('Spoken');
        $attempted = $this->caregiver('Attempted');
        $email = $this->caregiver('Email only');
        $future = $this->caregiver('Future entry');
        $this->activity($spoken, 'call', 'connected', now()->subDay());
        $latest = $this->activity($spoken, 'note', null, now());
        $this->activity($attempted, 'call', 'no_answer', now());
        $this->activity($attempted, 'call', 'voicemail', now());
        $this->activity($email, 'email', 'connected', now());
        $this->activity($future, 'call', 'connected', now()->addMinute());
        $directory = app(CaregiverCrmDirectory::class);

        $this->assertSame(now()->subDay()->format('Y-m-d H:i:s'), $directory->find($spoken->id)['phone_spoken_at']);
        $this->assertSame($latest->occurred_at->format('Y-m-d H:i:s'), $directory->find($spoken->id)['last_activity_at']);
        $this->assertSame(1, $directory->board(['contact' => 'called'])['stats']['total']);
        $this->assertSame(3, $directory->board(['contact' => 'not_called'])['stats']['total']);
        $this->assertSame(1, $directory->board()['stats']['called']);
    }

    public function test_search_assignment_and_follow_up_filters_are_consistent_with_summary_and_details(): void
    {
        $due = $this->caregiver('Target due', [], ['email' => 'due@example.test', 'phone' => '5551234000']);
        $upcoming = $this->caregiver('Target upcoming', ['service_area_zip' => '90210']);
        $none = $this->caregiver('Target unassigned');
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $record = CaregiverCrmRecord::create([
            'caregiver_user_id' => $due->id, 'assigned_admin_id' => $this->admin->id,
            'priority' => 'urgent', 'notes' => '<b>Internal notes</b>', 'next_action' => 'Discuss requests',
            'next_follow_up_at' => now()->subMinute(), 'version' => 3,
        ]);
        CaregiverCrmRecord::create([
            'caregiver_user_id' => $upcoming->id, 'assigned_admin_id' => $otherAdmin->id,
            'next_follow_up_at' => now()->addDay(),
        ]);
        $directory = app(CaregiverCrmDirectory::class);

        $this->assertSame($due->id, $directory->board(['search' => 'due@example'])['columns']['new']->sole()['user_id']);
        $this->assertSame($due->id, $directory->board(['search' => '5551234'])['columns']['new']->sole()['user_id']);
        $this->assertSame($upcoming->id, $directory->board(['search' => '90210'])['columns']['new']->sole()['user_id']);
        $this->assertSame($due->id, $directory->board(['assigned' => 'me', 'follow_up' => 'due'])['columns']['new']->sole()['user_id']);
        $this->assertSame($upcoming->id, $directory->board(['follow_up' => 'upcoming'])['columns']['new']->sole()['user_id']);
        $this->assertSame($none->id, $directory->board(['assigned' => 'unassigned', 'follow_up' => 'none'])['columns']['new']->sole()['user_id']);
        $this->assertSame(['total' => 3, 'called' => 0, 'due' => 1, 'unassigned' => 1], $directory->board()['stats']);
        $row = $directory->find($due->id);
        $this->assertSame($record->notes, $row['notes']);
        $this->assertSame('Discuss requests', $row['next_action']);
        $this->assertSame($this->admin->name, $row['assigned_name']);
        $this->assertSame(3, $row['version']);
    }

    public function test_independent_paging_is_bounded_and_all_caregivers_remain_accessible(): void
    {
        for ($index = 0; $index < 105; $index++) {
            $this->caregiver(sprintf('Caregiver %03d', $index));
        }
        $applicant = $this->caregiver('Applied caregiver');
        $this->application($applicant, $this->request(), 'applied');
        $directory = app(CaregiverCrmDirectory::class);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $first = $directory->board();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $next = $directory->board([], [], ['new' => 8]);
        $last = $directory->board([], [], ['new' => PHP_INT_MAX]);

        $this->assertCount(4, $queries);
        $this->assertSame(['new' => 105, 'applied' => 1, 'hired' => 0], $first['counts']);
        $this->assertCount(8, $first['columns']['new']);
        $this->assertCount(1, $first['columns']['applied']);
        $this->assertCount(8, $next['columns']['new']);
        $this->assertEmpty($first['columns']['new']->pluck('user_id')->intersect($next['columns']['new']->pluck('user_id')));
        $this->assertSame(104, $last['offsets']['new']);
        $this->assertSame('Caregiver 104', $last['columns']['new']->sole()['name']);
        $this->assertCount(100, $directory->board([], ['new' => 10000])['columns']['new']);
        $this->assertSame(0, $directory->board([], [], ['new' => -10])['offsets']['new']);
        $this->assertDatabaseCount('caregiver_crm_records', 0);
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_directory_is_admin_only(?string $role): void
    {
        if ($role === null) {
            auth()->logout();
        } else {
            $this->actingAs(User::factory()->create(['role' => $role]));
        }
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(0);
        app(CaregiverCrmDirectory::class)->board();
    }

    public static function unauthorizedRoles(): array
    {
        return [[null], ['family'], ['caregiver'], ['sales'], ['sdr']];
    }

    private function caregiver(string $name, array $profile = [], array $user = []): User
    {
        $caregiver = User::factory()->create(['name' => $name, 'role' => 'caregiver', ...$user]);
        CaregiverProfile::create([
            'user_id' => $caregiver->id, 'status' => 'active', 'screening_initial_approved_at' => now(),
            'screening_legacy_access' => false, 'service_area_zip' => '27601', ...$profile,
        ]);

        return $caregiver;
    }

    private function request(array $attributes = []): CareRequest
    {
        return CareRequest::create([
            'family_user_id' => $this->family->id, 'title' => 'Care request', 'status' => 'open',
            'address_line1' => '10 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            ...$attributes,
        ]);
    }

    private function application(User $caregiver, CareRequest $request, string $status): CareRequestApplication
    {
        return CareRequestApplication::create([
            'care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id, 'status' => $status,
        ]);
    }

    private function booking(User $caregiver, CareRequest $request, CareRequestApplication $application): CareBooking
    {
        return CareBooking::create([
            'care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $this->family->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'scheduled',
        ]);
    }

    private function plan(User $caregiver, array $attributes = []): CarePlan
    {
        return CarePlan::create([
            'family_user_id' => $this->family->id, 'caregiver_user_id' => $caregiver->id,
            'title' => 'Regular care', 'status' => 'active', 'schedule_days' => [1],
            'schedule_start_time' => '09:00', 'schedule_end_time' => '11:00', 'starts_on' => '2026-10-01',
            'hourly_rate' => 30, 'accepted_at' => now(), 'activated_at' => now(), ...$attributes,
        ]);
    }

    private function coveragePlan(): ContinuousCoveragePlan
    {
        return ContinuousCoveragePlan::create([
            'family_user_id' => $this->family->id, 'title' => 'Coverage', 'starts_on' => '2026-10-01',
            'recipient_snapshot' => [], 'address_snapshot' => [], 'hourly_rate' => 30,
        ]);
    }

    private function roster(ContinuousCoveragePlan $plan, User $caregiver, string $status): ContinuousCoverageRosterMember
    {
        return ContinuousCoverageRosterMember::create([
            'continuous_coverage_plan_id' => $plan->id, 'caregiver_user_id' => $caregiver->id, 'status' => $status,
        ]);
    }

    private function shift(ContinuousCoveragePlan $plan, ?User $caregiver, array $attributes = []): ContinuousCoverageShift
    {
        return ContinuousCoverageShift::create([
            'continuous_coverage_plan_id' => $plan->id, 'assigned_caregiver_user_id' => $caregiver?->id,
            'occurrence_key' => fake()->unique()->uuid(), 'status' => 'confirmed',
            'scheduled_start_at' => now()->addDay(), 'scheduled_end_at' => now()->addDay()->addHours(4),
            'scheduled_minutes' => 240, 'caregiver_accepted_at' => now()->subDay(),
            'family_confirmed_at' => now()->subDay(), 'confirmed_at' => now()->subDay(), ...$attributes,
        ]);
    }

    private function activity(User $caregiver, string $type, ?string $outcome, mixed $at): CaregiverCrmActivity
    {
        return CaregiverCrmActivity::create([
            'caregiver_user_id' => $caregiver->id, 'actor_user_id' => $this->admin->id,
            'type' => $type, 'call_outcome' => $outcome, 'body' => 'Contact history', 'occurred_at' => $at,
        ]);
    }
}
