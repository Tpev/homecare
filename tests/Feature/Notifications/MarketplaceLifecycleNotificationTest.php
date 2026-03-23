<?php

namespace Tests\Feature\Notifications;

use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareTask;
use App\Models\CaregiverProfile;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Notifications\MarketplaceEventNotification;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class MarketplaceLifecycleNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_sends_notifications_to_family_and_caregiver(): void
    {
        Notification::fake();

        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createMarketplaceReadyCaregiver();
        $request = $this->createOpenRequest($family);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('inviteSuggestedCaregiver', $caregiver->id);

        Notification::assertSentTo(
            $caregiver,
            MarketplaceEventNotification::class,
            fn (MarketplaceEventNotification $notification, array $channels): bool =>
                in_array('database', $channels, true)
                && $this->eventKeyFor($notification, $caregiver) === MarketplaceEvent::MATCHING_REQUEST_REMINDER
        );

        Notification::assertSentTo(
            $family,
            MarketplaceEventNotification::class,
            fn (MarketplaceEventNotification $notification, array $channels): bool =>
                in_array('database', $channels, true)
                && $this->eventKeyFor($notification, $family) === MarketplaceEvent::INVITATION_SENT
        );
    }

    public function test_apply_sends_notifications_to_family_and_caregiver(): void
    {
        Notification::fake();

        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createMarketplaceReadyCaregiver();
        $request = $this->createOpenRequest($family);

        Livewire::actingAs($caregiver)
            ->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->set('cover_note', str_repeat('I can support this care plan consistently and communicate clearly. ', 2))
            ->call('submit');

        Notification::assertSentTo(
            $family,
            MarketplaceEventNotification::class,
            fn (MarketplaceEventNotification $notification, array $channels): bool =>
                in_array('database', $channels, true)
                && $this->eventKeyFor($notification, $family) === MarketplaceEvent::NEW_APPLICANT
        );

        Notification::assertSentTo(
            $caregiver,
            MarketplaceEventNotification::class,
            fn (MarketplaceEventNotification $notification, array $channels): bool =>
                in_array('database', $channels, true)
                && $this->eventKeyFor($notification, $caregiver) === MarketplaceEvent::APPLICATION_SUBMITTED
        );
    }

    public function test_hire_sends_notifications_to_family_and_caregiver(): void
    {
        Notification::fake();

        $family = User::factory()->create(['role' => 'family']);
        $caregiver = $this->createMarketplaceReadyCaregiver();
        $request = $this->createOpenRequest($family);

        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
            'proposed_rate' => 29.00,
            'cover_note' => 'Ready to start quickly and support this family.',
        ]);

        Livewire::actingAs($family)
            ->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('hire', $application->id);

        Notification::assertSentTo(
            $caregiver,
            MarketplaceEventNotification::class,
            fn (MarketplaceEventNotification $notification, array $channels): bool =>
                in_array('database', $channels, true)
                && $this->eventKeyFor($notification, $caregiver) === MarketplaceEvent::CAREGIVER_HIRED
        );

        Notification::assertSentTo(
            $family,
            MarketplaceEventNotification::class,
            fn (MarketplaceEventNotification $notification, array $channels): bool =>
                in_array('database', $channels, true)
                && $this->eventKeyFor($notification, $family) === MarketplaceEvent::HIRE_CONFIRMED
        );
    }

    private function createMarketplaceReadyCaregiver(): User
    {
        $caregiver = User::factory()->create(['role' => 'caregiver']);

        $profile = CaregiverProfile::query()->create([
            'user_id' => $caregiver->id,
            'status' => 'active',
            'bio' => str_repeat('Experienced non-medical caregiver support. ', 4),
            'platform_hourly_rate' => 29.00,
            'years_experience' => 5,
            'service_area_zip' => '27601',
            'service_radius_miles' => 15,
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
            'identity_verification_status' => 'approved',
            'identity_verified_at' => now(),
        ]);

        $skill = Skill::query()->create(['name' => 'Companionship '.$caregiver->id]);
        $language = Language::query()->create(['name' => 'English '.$caregiver->id]);

        $profile->skills()->sync([$skill->id]);
        $profile->languages()->sync([$language->id]);
        $profile->availabilities()->create([
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);

        return $caregiver;
    }

    private function createOpenRequest(User $family): CareRequest
    {
        $request = CareRequest::query()->create([
            'family_user_id' => $family->id,
            'title' => 'Need daytime care support',
            'status' => CareRequest::STATUS_OPEN,
            'request_type' => CareRequest::TYPE_ONE_TIME,
            'requested_start_at' => now()->addDay()->setTime(9, 0),
            'requested_end_at' => now()->addDay()->setTime(13, 0),
            'address_line1' => '100 Main St',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);

        $task = CareTask::query()->create(['name' => 'Companionship '.$family->id]);
        $request->tasks()->sync([$task->id => ['task_note' => 'Morning companionship and supervision']]);
        $request->recipient()->create([
            'full_name' => 'Recipient Person',
            'relationship_to_family' => 'Mother',
            'care_notes' => 'Needs non-medical support and reminders.',
        ]);

        return $request;
    }

    private function eventKeyFor(MarketplaceEventNotification $notification, User $user): string
    {
        return (string) ($notification->toArray($user)['event_key'] ?? '');
    }
}

