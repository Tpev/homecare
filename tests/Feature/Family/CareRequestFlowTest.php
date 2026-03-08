<?php

namespace Tests\Feature\Family;

use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Family\CreateCareRequestWizard;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareTask;
use App\Models\CaregiverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CareRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_can_publish_care_request_with_recipient_and_third_party_contact(): void
    {
        $family = User::factory()->create([
            'role' => 'family',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);

        $taskA = CareTask::query()->create(['name' => 'Companionship']);
        $taskB = CareTask::query()->create(['name' => 'Meal preparation']);

        $startAt = now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');
        $endAt = now()->addDay()->setTime(13, 0)->format('Y-m-d\TH:i');

        Livewire::actingAs($family)
            ->test(CreateCareRequestWizard::class)
            ->set('title', 'Need caregiver for Monday afternoon support')
            ->set('additional_info', 'Looking for non-medical help and supervision.')
            ->set('requested_start_at', $startAt)
            ->set('requested_end_at', $endAt)
            ->set('address_line1', '123 Main St')
            ->set('city', 'Raleigh')
            ->set('state', 'NC')
            ->set('zip', '27601')
            ->set('selectedTasks', [$taskA->id, $taskB->id])
            ->set('taskNotes.'.$taskA->id, 'Prefers board games and walks.')
            ->call('nextStep')
            ->set('recipient_full_name', 'Jane Doe')
            ->set('recipient_relationship_to_family', 'Mother')
            ->call('nextStep')
            ->set('includeThirdPartyContact', true)
            ->set('third_party_full_name', 'John Doe')
            ->set('third_party_relationship_to_recipient', 'Son')
            ->set('third_party_phone', '+1 919 555 0101')
            ->set('third_party_email', 'john@example.com')
            ->call('nextStep')
            ->call('publish');

        $careRequest = CareRequest::query()->first();

        $this->assertNotNull($careRequest);
        $this->assertDatabaseHas('care_requests', [
            'id' => $careRequest->id,
            'family_user_id' => $family->id,
            'status' => CareRequest::STATUS_OPEN,
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);
        $this->assertDatabaseHas('care_request_recipients', [
            'care_request_id' => $careRequest->id,
            'full_name' => 'Jane Doe',
        ]);
        $this->assertDatabaseHas('care_request_third_party_contacts', [
            'care_request_id' => $careRequest->id,
            'full_name' => 'John Doe',
        ]);
        $this->assertDatabaseHas('care_request_task', [
            'care_request_id' => $careRequest->id,
            'care_task_id' => $taskA->id,
        ]);
    }

    public function test_active_caregiver_can_apply_to_open_request(): void
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create(['user_id' => $caregiver->id, 'status' => 'active']);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Morning care needed',
            'status' => CareRequest::STATUS_OPEN,
            'requested_start_at' => now()->addDay()->setTime(8, 0),
            'requested_end_at' => now()->addDay()->setTime(12, 0),
            'address_line1' => '456 Oak Ave',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27603',
        ]);
        $request->recipient()->create([
            'full_name' => 'Recipient Name',
            'relationship_to_family' => 'Father',
        ]);

        Livewire::actingAs($caregiver)
            ->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->set('proposed_rate', 28.50)
            ->set('cover_note', str_repeat('I have relevant home care experience. ', 3))
            ->call('submit');

        $this->assertDatabaseHas('care_request_applications', [
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 28.50,
        ]);
    }

    public function test_family_can_hire_applicant_and_request_is_marked_filled(): void
    {
        $family = User::factory()->create(['role' => 'family']);

        $caregiverA = User::factory()->create(['role' => 'caregiver']);
        $caregiverB = User::factory()->create(['role' => 'caregiver']);
        CaregiverProfile::query()->create(['user_id' => $caregiverA->id, 'status' => 'active']);
        CaregiverProfile::query()->create(['user_id' => $caregiverB->id, 'status' => 'active']);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Evening support needed',
            'status' => CareRequest::STATUS_OPEN,
            'requested_start_at' => now()->addDays(2)->setTime(16, 0),
            'requested_end_at' => now()->addDays(2)->setTime(20, 0),
            'address_line1' => '789 Pine St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27604',
        ]);
        $request->recipient()->create([
            'full_name' => 'Senior Name',
            'relationship_to_family' => 'Grandmother',
        ]);

        $applicationA = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiverA->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 27,
            'cover_note' => 'Can start this week.',
        ]);
        $applicationB = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiverB->id,
            'status' => CareRequestApplication::STATUS_SHORTLISTED,
            'proposed_rate' => 29,
            'cover_note' => 'Flexible evenings.',
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $applicationB->id);

        $this->assertDatabaseHas('care_requests', [
            'id' => $request->id,
            'status' => CareRequest::STATUS_FILLED,
        ]);
        $this->assertDatabaseHas('care_request_applications', [
            'id' => $applicationB->id,
            'status' => CareRequestApplication::STATUS_HIRED,
        ]);
        $this->assertDatabaseHas('care_request_applications', [
            'id' => $applicationA->id,
            'status' => CareRequestApplication::STATUS_NOT_SELECTED,
        ]);
        $this->assertDatabaseHas('care_request_conversations', [
            'care_request_id' => $request->id,
            'care_request_application_id' => $applicationB->id,
            'caregiver_user_id' => $caregiverB->id,
            'family_user_id' => $family->id,
        ]);
    }

    public function test_non_family_user_cannot_access_family_routes(): void
    {
        $caregiver = User::factory()->create(['role' => 'caregiver']);

        $response = $this->actingAs($caregiver)->get('/family/requests');

        $response->assertForbidden();
    }
}
