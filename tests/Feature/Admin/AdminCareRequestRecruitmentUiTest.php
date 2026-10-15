<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendCareRequestRecruitment;
use App\Livewire\Admin\CareRequestShow;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestRecruitment;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCareRequestRecruitmentUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.caregiver_prelaunch_mode' => false]);
        Queue::fake();
    }

    public function test_admin_searches_selects_and_queues_emails_without_preview_or_family_invitation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createRequest();
        $caregiver = $this->createCaregiver('Sarah Selected');
        $this->createCaregiver('Other Caregiver');

        Livewire::actingAs($admin)->test(CareRequestShow::class, ['careRequest' => $request])
            ->assertSee('Invite caregivers to apply')
            ->assertDontSee('Sarah Selected')
            ->call('openRecruitmentPanel')
            ->assertSet('showRecruitmentPanel', true)
            ->set('recruitmentSearch', $caregiver->email)
            ->assertSee('Sarah Selected')
            ->assertDontSee('Other Caregiver')
            ->call('selectRecruitmentPage')
            ->assertSet('selectedRecruitmentCaregivers', [(string) $caregiver->id])
            ->assertSee('Send invitations (1)')
            ->assertDontSee('Preview email')
            ->call('sendRecruitmentInvitations')
            ->assertHasNoErrors()
            ->assertSet('selectedRecruitmentCaregivers', [])
            ->assertSee('1 invitation queued for email from Lolo.')
            ->assertSee('Recent Lolo outreach')
            ->assertSee('Queued');

        Queue::assertPushed(SendCareRequestRecruitment::class, 1);
        $this->assertDatabaseHas('care_request_recruitments', [
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'invited_by_user_id' => $admin->id,
            'status' => 'queued',
        ]);
        $this->assertDatabaseCount('care_request_applications', 0);
        $this->assertDatabaseCount('care_request_invitations', 0);
    }

    public function test_bulk_selection_caps_at_one_hundred_and_keeps_selections_across_pages_and_searches(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createRequest();
        foreach (range(1, 102) as $number) {
            $this->createCaregiver(sprintf('Caregiver %03d', $number));
        }

        $component = Livewire::actingAs($admin)->test(CareRequestShow::class, ['careRequest' => $request])
            ->call('openRecruitmentPanel')
            ->assertSee('Caregiver 025')
            ->assertDontSee('Caregiver 026')
            ->call('selectRecruitmentPage')
            ->assertCount('selectedRecruitmentCaregivers', 25)
            ->call('setPage', 2, 'recruitmentPage')
            ->assertSee('Caregiver 026')
            ->call('selectRecruitmentPage')
            ->assertCount('selectedRecruitmentCaregivers', 50)
            ->set('recruitmentSearch', 'Caregiver 102')
            ->assertSet('paginators.recruitmentPage', 1)
            ->assertCount('selectedRecruitmentCaregivers', 50)
            ->assertSee('Caregiver 102')
            ->call('selectRecruitmentPage')
            ->assertCount('selectedRecruitmentCaregivers', 51)
            ->set('recruitmentSearch', '')
            ->call('selectMatchingRecruitmentCaregivers')
            ->assertCount('selectedRecruitmentCaregivers', 100)
            ->assertSee('Send invitations (100)')
            ->call('clearRecruitmentSelection')
            ->assertSet('selectedRecruitmentCaregivers', []);

        $component->set('recruitmentSearch', '27601')
            ->call('selectMatchingRecruitmentCaregivers')
            ->assertCount('selectedRecruitmentCaregivers', 100);
        Queue::assertNothingPushed();
    }

    public function test_empty_malformed_and_oversized_selections_cannot_send(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createRequest();

        $component = Livewire::actingAs($admin)->test(CareRequestShow::class, ['careRequest' => $request])
            ->call('sendRecruitmentInvitations')
            ->assertHasErrors(['selectedRecruitmentCaregivers' => 'required'])
            ->set('selectedRecruitmentCaregivers', ['invalid'])
            ->call('sendRecruitmentInvitations')
            ->assertHasErrors(['selectedRecruitmentCaregivers.0' => 'integer'])
            ->set('selectedRecruitmentCaregivers', range(1, 101))
            ->call('sendRecruitmentInvitations')
            ->assertHasErrors(['selectedRecruitmentCaregivers' => 'max']);

        $component->set('selectedRecruitmentCaregivers', ['1', '1'])
            ->call('sendRecruitmentInvitations')
            ->assertHasErrors(['selectedRecruitmentCaregivers.0' => 'distinct']);
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('care_request_recruitments', 0);
    }

    public function test_sending_revalidates_tampered_user_ids_and_changed_request_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createRequest();
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createCaregiver('Selected before closing');

        $component = Livewire::actingAs($admin)->test(CareRequestShow::class, ['careRequest' => $request])
            ->call('openRecruitmentPanel')
            ->set('selectedRecruitmentCaregivers', [$family->id])
            ->call('sendRecruitmentInvitations')
            ->assertSee('0 invitations queued for email from Lolo.')
            ->assertSee('1 were not queued.');

        $request->update(['status' => CareRequest::STATUS_CANCELLED]);
        $component->set('selectedRecruitmentCaregivers', [$caregiver->id])
            ->call('sendRecruitmentInvitations')
            ->assertHasErrors(['recruitment'])
            ->assertSee('This job is no longer accepting applications.');

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('care_request_recruitments', 0);
    }

    public function test_private_job_explains_why_recruitment_is_unavailable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createRequest(['is_private' => true]);

        Livewire::actingAs($admin)->test(CareRequestShow::class, ['careRequest' => $request])
            ->assertDontSee('Invite caregivers to apply')
            ->assertSee('Private jobs require an invitation from the family.')
            ->call('openRecruitmentPanel')
            ->assertDontSee('Send invitations (');
    }

    public function test_non_admin_cannot_mount_or_use_an_already_open_recruitment_component(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createRequest();
        $caregiver = $this->createCaregiver('Protected Caregiver');

        Livewire::actingAs($family)->test(CareRequestShow::class, ['careRequest' => $request])
            ->assertForbidden();

        $component = Livewire::actingAs($admin)->test(CareRequestShow::class, ['careRequest' => $request])
            ->call('openRecruitmentPanel')
            ->set('selectedRecruitmentCaregivers', [$caregiver->id]);
        $this->actingAs($family);
        $component->call('sendRecruitmentInvitations')->assertForbidden();

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('care_request_recruitments', 0);
    }

    public function test_outreach_history_is_bounded_scoped_and_polls_only_while_pending(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = $this->createRequest();
        $otherRequest = $this->createRequest();
        foreach (range(1, 12) as $number) {
            $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => 'Historical caregiver '.$number]);
            CareRequestRecruitment::query()->create([
                'care_request_id' => $request->id,
                'caregiver_user_id' => $caregiver->id,
                'invited_by_user_id' => $admin->id,
                'status' => $number === 12 ? 'queued' : 'sent',
            ]);
        }
        CareRequestRecruitment::query()->create([
            'care_request_id' => $otherRequest->id,
            'caregiver_user_id' => User::factory()->create(['name' => 'Other job caregiver'])->id,
            'invited_by_user_id' => $admin->id,
            'status' => 'queued',
        ]);

        $component = Livewire::actingAs($admin)->test(CareRequestShow::class, ['careRequest' => $request])
            ->assertSee('12 total')
            ->assertSee('Showing the latest 10 invitations.')
            ->assertSee('Historical caregiver 12')
            ->assertDontSee('Historical caregiver 2')
            ->assertDontSee('Other job caregiver')
            ->assertSee('wire:poll.10s="refreshRecruitment"', false);

        CareRequestRecruitment::query()->where('care_request_id', $request->id)->update(['status' => 'sent']);
        $component->call('refreshRecruitment')
            ->assertDontSee('wire:poll.10s="refreshRecruitment"', false)
            ->assertSee('12 sent');
    }

    private function createRequest(array $overrides = []): CareRequest
    {
        return CareRequest::query()->create(array_merge([
            'family_user_id' => User::factory()->create(['role' => 'family'])->id,
            'title' => 'Morning companionship',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDays(2),
            'requested_end_at' => now()->addDays(2)->addHours(3),
            'address_line1' => '123 Main Street',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ], $overrides));
    }

    private function createCaregiver(string $name): User
    {
        $caregiver = User::factory()->create(['role' => 'caregiver', 'name' => $name, 'city' => 'Raleigh', 'state' => 'NC']);
        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'bio' => 'Experienced non-medical caregiver.',
            'years_experience' => 4,
            'service_area_zip' => '27601',
            'service_radius_miles' => 20,
            'is_accepting_new_clients' => true,
            'identity_verified_at' => now(),
            'screening_initial_approved_at' => now(),
            'platform_hourly_rate' => 30,
        ]);
        $profile->skills()->attach(Skill::query()->firstOrCreate(['name' => 'Companionship'])->id);
        $profile->languages()->attach(Language::query()->firstOrCreate(['name' => 'English'])->id);
        $profile->availabilities()->create(['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '18:00']);

        return $caregiver;
    }
}
