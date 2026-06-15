<?php

namespace Tests\Feature\Caregiver;

use App\Models\CareBooking;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CaregiverPayout;
use App\Models\CaregiverPayoutItem;
use App\Models\CaregiverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaregiverEarningsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_caregiver_can_view_earnings_summary_shifts_and_payouts(): void
    {
        [$caregiver, $family] = $this->seedCaregiverAndFamily();

        $eligibleBooking = $this->createBooking(
            family: $family,
            caregiver: $caregiver,
            title: 'Morning companion support',
            status: CareBooking::STATUS_COMPLETED,
            rate: 28.00,
            workedMinutes: 180,
            familyConfirmed: true
        );

        $this->createBooking(
            family: $family,
            caregiver: $caregiver,
            title: 'Afternoon check-in',
            status: CareBooking::STATUS_COMPLETED,
            rate: 28.00,
            workedMinutes: 120,
            familyConfirmed: false
        );

        $paidBooking = $this->createBooking(
            family: $family,
            caregiver: $caregiver,
            title: 'Weekend routine',
            status: CareBooking::STATUS_REVIEWED,
            rate: 28.00,
            workedMinutes: 150,
            familyConfirmed: true
        );

        $this->createBooking(
            family: $family,
            caregiver: $caregiver,
            title: 'Live shift in progress',
            status: CareBooking::STATUS_IN_PROGRESS,
            rate: 30.00,
            workedMinutes: null,
            familyConfirmed: false,
            startedAtMinutesAgo: 60
        );

        $payout = CaregiverPayout::query()->create([
            'caregiver_user_id' => $caregiver->id,
            'period_start_on' => now()->subWeek()->toDateString(),
            'period_end_on' => now()->subDays(2)->toDateString(),
            'status' => CaregiverPayout::STATUS_PAID,
            'currency' => 'USD',
            'gross_amount' => 70.00,
            'adjustments_amount' => 0,
            'net_amount' => 70.00,
            'scheduled_for' => now()->subDay(),
            'paid_at' => now()->subDay(),
        ]);

        CaregiverPayoutItem::query()->create([
            'caregiver_payout_id' => $payout->id,
            'caregiver_user_id' => $caregiver->id,
            'care_booking_id' => $paidBooking->id,
            'status' => CaregiverPayoutItem::STATUS_PAID,
            'currency' => 'USD',
            'amount' => 70.00,
            'included_at' => now()->subDay(),
            'paid_at' => now()->subDay(),
        ]);

        $overview = $this->actingAs($caregiver)->get('/caregiver/earnings');
        $overview->assertOk();
        $overview->assertSee('This week gross');
        $overview->assertSee('$240.00');
        $overview->assertSee('$84.00');
        $overview->assertSee('$86.00');
        $overview->assertSee('Best next action');
        $overview->assertSee('Open active visit');

        $shifts = $this->actingAs($caregiver)->get('/caregiver/earnings?tab=shifts');
        $shifts->assertOk();
        $shifts->assertSee('Morning companion support');
        $shifts->assertSee('Eligible');

        $payouts = $this->actingAs($caregiver)->get('/caregiver/earnings?tab=payouts');
        $payouts->assertOk();
        $payouts->assertSee('Payout history');
        $payouts->assertSee('PAID');
        $payouts->assertSee('$70.00');

        $this->assertDatabaseHas('caregiver_payout_items', [
            'care_booking_id' => $paidBooking->id,
            'status' => CaregiverPayoutItem::STATUS_PAID,
        ]);
        $this->assertDatabaseMissing('caregiver_payout_items', [
            'care_booking_id' => $eligibleBooking->id,
        ]);
    }

    public function test_family_cannot_access_caregiver_earnings_dashboard(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($family)->get('/caregiver/earnings');

        $response->assertForbidden();
    }

    public function test_caregiver_navigation_contains_my_earnings_link(): void
    {
        [$caregiver] = $this->seedCaregiverAndFamily();

        $response = $this->actingAs($caregiver)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('My Earnings');
        $response->assertSee('/caregiver/earnings', false);
    }

    /**
     * @return array{0:User,1:User}
     */
    private function seedCaregiverAndFamily(): array
    {
        $family = User::factory()->create([
            'role' => 'family',
            'email_verified_at' => now(),
        ]);

        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email_verified_at' => now(),
        ]);

        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'bio' => str_repeat('Experienced caregiver ', 5),
            'platform_hourly_rate' => 28,
            'years_experience' => 6,
            'service_area_zip' => '27601',
            'service_radius_miles' => 12,
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
            'identity_verified_at' => now(),
            'identity_verification_status' => 'approved',
        ]);

        return [$caregiver, $family];
    }

    private function createBooking(
        User $family,
        User $caregiver,
        string $title,
        string $status,
        float $rate,
        ?int $workedMinutes,
        bool $familyConfirmed,
        int $startedAtMinutesAgo = 180
    ): CareBooking {
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => $title,
            'status' => CareRequest::STATUS_FILLED,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->subHours(2),
            'requested_end_at' => now()->subHour(),
            'address_line1' => '100 Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);

        $request->recipient()->create([
            'full_name' => 'Recipient',
            'relationship_to_family' => 'Parent',
        ]);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_HIRED,
            'proposed_rate' => $rate,
            'cover_note' => str_repeat('Available and ready. ', 4),
        ]);

        return CareBooking::query()->create([
            'care_request_id' => $request->id,
            'care_request_application_id' => $application->id,
            'family_user_id' => $family->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => $status,
            'scheduled_start_at' => now()->subHours(2),
            'scheduled_end_at' => now()->subHour(),
            'started_at' => now()->subMinutes($startedAtMinutesAgo),
            'completed_at' => in_array($status, [CareBooking::STATUS_COMPLETED, CareBooking::STATUS_REVIEWED], true) ? now()->subMinutes(5) : null,
            'worked_minutes' => $workedMinutes,
            'family_confirmed_at' => $familyConfirmed ? now()->subMinutes(3) : null,
            'family_terms_accepted_at' => now()->subHours(4),
            'caregiver_terms_accepted_at' => now()->subHours(4),
        ]);
    }
}
