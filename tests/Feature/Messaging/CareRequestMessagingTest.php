<?php

namespace Tests\Feature\Messaging;

use App\Livewire\Messaging\Inbox;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestMessage;
use App\Models\CaregiverProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CareRequestMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_can_open_conversation_for_shortlisted_applicant_and_send_message(): void
    {
        [$family, $caregiver, $request] = $this->seedRequestContext(CareRequestApplication::STATUS_SHORTLISTED);

        $application = CareRequestApplication::query()->where('care_request_id', $request->id)->firstOrFail();
        $conversation = CareRequestConversation::findOrCreateForApplication($application, $family->id);

        Livewire::actingAs($family)
            ->test(Inbox::class, ['conversation' => $conversation->id])
            ->set('messageBody', 'Hello, can you confirm arrival 15 minutes early?')
            ->call('sendMessage')
            ->assertSet('messageBody', '');

        $this->assertDatabaseHas('care_request_messages', [
            'care_request_conversation_id' => $conversation->id,
            'sender_user_id' => $family->id,
            'body' => 'Hello, can you confirm arrival 15 minutes early?',
        ]);

        $this->assertDatabaseHas('care_request_conversations', [
            'id' => $conversation->id,
            'last_message_sender_id' => $family->id,
        ]);
    }

    public function test_caregiver_cannot_send_message_when_application_not_shortlisted_or_hired(): void
    {
        [$family, $caregiver, $request] = $this->seedRequestContext(CareRequestApplication::STATUS_APPLIED);

        $application = CareRequestApplication::query()->where('care_request_id', $request->id)->firstOrFail();
        $conversation = CareRequestConversation::findOrCreateForApplication($application, $family->id);
        $this->assertFalse($caregiver->can('sendMessage', $conversation));

        try {
            Livewire::actingAs($caregiver)
                ->test(Inbox::class, ['conversation' => $conversation->id])
                ->set('messageBody', 'Trying to message before shortlist')
                ->call('sendMessage');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertDatabaseMissing('care_request_messages', [
            'care_request_conversation_id' => $conversation->id,
            'sender_user_id' => $caregiver->id,
            'body' => 'Trying to message before shortlist',
        ]);
    }

    public function test_opening_conversation_marks_it_as_read_for_current_user(): void
    {
        [$family, $caregiver, $request] = $this->seedRequestContext(CareRequestApplication::STATUS_SHORTLISTED);
        $application = CareRequestApplication::query()->where('care_request_id', $request->id)->firstOrFail();
        $conversation = CareRequestConversation::findOrCreateForApplication($application, $caregiver->id);

        CareRequestMessage::query()->create([
            'care_request_conversation_id' => $conversation->id,
            'sender_user_id' => $caregiver->id,
            'body' => 'Hi, I am available this week.',
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
            'last_message_sender_id' => $caregiver->id,
            'family_last_read_at' => null,
            'caregiver_last_read_at' => now(),
        ])->save();

        Livewire::actingAs($family)
            ->test(Inbox::class, ['conversation' => $conversation->id])
            ->call('refreshThread');

        $this->assertNotNull($conversation->fresh()->family_last_read_at);
    }

    private function seedRequestContext(string $applicationStatus): array
    {
        $family = User::factory()->create([
            'role' => 'family',
            'city' => 'Raleigh',
            'state' => 'NC',
        ]);

        $caregiver = User::factory()->create([
            'role' => 'caregiver',
            'city' => 'Raleigh',
            'state' => 'NC',
            'onboarding_completed_at' => now(),
        ]);
        CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'hourly_rate' => 26,
            'service_area_zip' => '27601',
            'service_radius_miles' => 10,
        ]);

        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Morning support needed',
            'status' => CareRequest::STATUS_OPEN,
            'requested_start_at' => now()->addDay()->setTime(9, 0),
            'requested_end_at' => now()->addDay()->setTime(12, 0),
            'address_line1' => '123 Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $request->recipient()->create([
            'full_name' => 'Recipient Test',
            'relationship_to_family' => 'Mother',
        ]);

        CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => $applicationStatus,
            'proposed_rate' => 28,
            'cover_note' => 'Experienced caregiver ready to help.',
        ]);

        return [$family, $caregiver, $request];
    }
}
