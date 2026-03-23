<?php

namespace Tests\Feature\Marketplace;

use App\Jobs\InjectPrelaunchStaffApplication;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CaregiverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PrelaunchFamilyAutoApplicantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prelaunch_auto_applicants_are_queued_with_expected_delays(): void
    {
        config([
            'marketplace.family_prelaunch_auto_applicants.enabled' => true,
            'marketplace.family_prelaunch_auto_applicants.emails' => [
                'carolinepetrinipoli@gmail.com',
                'charlespetrinipoli@gmail.com',
            ],
            'marketplace.family_prelaunch_auto_applicants.delays_minutes' => [10, 15],
        ]);

        Queue::fake();

        $family = User::factory()->create(['role' => 'family']);

        CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Need support this afternoon',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay()->setTime(14, 0),
            'requested_end_at' => now()->addDay()->setTime(18, 0),
            'address_line1' => '100 Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);

        Queue::assertPushed(InjectPrelaunchStaffApplication::class, 2);
        Queue::assertPushed(
            InjectPrelaunchStaffApplication::class,
            fn (InjectPrelaunchStaffApplication $job): bool => $job->caregiverEmail === 'carolinepetrinipoli@gmail.com'
                && $job->delayMinutes === 10
        );
        Queue::assertPushed(
            InjectPrelaunchStaffApplication::class,
            fn (InjectPrelaunchStaffApplication $job): bool => $job->caregiverEmail === 'charlespetrinipoli@gmail.com'
                && $job->delayMinutes === 15
        );
    }

    public function test_prelaunch_auto_applicant_job_creates_applied_record_once(): void
    {
        config([
            'marketplace.family_prelaunch_auto_applicants.enabled' => true,
            'marketplace.family_prelaunch_auto_applicants.cover_note' => 'Staff caregiver support response.',
        ]);

        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'email' => 'charlespetrinipoli@gmail.com',
        ]);

        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'slug' => 'charles-'.$caregiver->id,
            'platform_hourly_rate' => 28.00,
        ]);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Weekday support',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay()->setTime(9, 0),
            'requested_end_at' => now()->addDay()->setTime(12, 0),
            'address_line1' => '200 Broad St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);

        $job = new InjectPrelaunchStaffApplication($request->id, 'charlespetrinipoli@gmail.com', 15);
        $job->handle();
        $job->handle();

        $this->assertDatabaseHas('care_request_applications', [
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 28.00,
            'cover_note' => 'Staff caregiver support response.',
        ]);

        $this->assertSame(
            1,
            CareRequestApplication::query()
                ->where('care_request_id', $request->id)
                ->where('caregiver_user_id', $caregiver->id)
                ->count()
        );
    }
}

