<?php

namespace Tests\Feature\CustomerCare;

use App\Models\CareBooking;
use App\Models\CarePlan;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\FamilyAccountMember;
use App\Models\MarketplaceNotificationDelivery;
use App\Models\User;
use App\Notifications\MarketplaceEventNotification;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerCareSilenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_channels_and_family_members_are_silent_but_caregivers_are_not(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role' => 'family']);
        $member = User::factory()->create(['role' => 'family']);
        $family = app(FamilyAccountContext::class)->account($owner);
        FamilyAccountMember::create(['family_account_id' => $family->id, 'user_id' => $member->id, 'access_level' => 'member', 'status' => 'active', 'joined_at' => now()]);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $request = CareRequest::create(['address_line1' => '100 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601'] + ['family_user_id' => $owner->id, 'family_account_id' => $family->id, 'staff_managed' => true, 'title' => 'Managed care', 'request_type' => 'one_time', 'status' => 'open']);
        $application = CareRequestApplication::create(['care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'applied']);
        app(MarketplaceNotificationService::class)->notify([$owner, $caregiver], MarketplaceEvent::NEW_APPLICANT,
            'New applicant', 'There is an update', subject: $application,
            channelOverrides: ['email' => true, 'in_app' => true, 'sms' => true, 'push' => true]);
        $this->assertDatabaseMissing('marketplace_notification_deliveries', ['user_id' => $owner->id]);
        $this->assertDatabaseMissing('marketplace_notification_deliveries', ['user_id' => $member->id]);
        Notification::assertNotSentTo([$owner, $member], MarketplaceEventNotification::class);
        Notification::assertSentTo($caregiver, MarketplaceEventNotification::class);
        $this->assertDatabaseHas('marketplace_notification_deliveries', ['user_id' => $caregiver->id]);
    }

    public function test_unmanaged_request_notifications_remain_unchanged(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role' => 'family']);
        $family = app(FamilyAccountContext::class)->account($owner);
        $request = CareRequest::create(['address_line1' => '100 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601'] + ['family_user_id' => $owner->id, 'family_account_id' => $family->id, 'title' => 'Normal care', 'status' => 'open', 'request_type' => 'one_time']);
        app(MarketplaceNotificationService::class)->notify($owner, MarketplaceEvent::NEW_APPLICANT, 'New applicant', 'Review this update', subject: $request);
        Notification::assertSentTo($owner, MarketplaceEventNotification::class);
    }

    public function test_already_reserved_email_is_suppressed_after_staff_takeover(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role' => 'family']);
        $family = app(FamilyAccountContext::class)->account($owner);
        $request = CareRequest::create(['address_line1' => '100 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601'] + ['family_user_id' => $owner->id, 'family_account_id' => $family->id, 'title' => 'Managed care', 'status' => 'open', 'request_type' => 'one_time']);
        $delivery = MarketplaceNotificationDelivery::create(['user_id' => $owner->id, 'event_key' => MarketplaceEvent::SHIFT_REMINDER_24H, 'channel' => 'email', 'status' => 'pending',
            'notifiable_type' => CareRequest::class, 'notifiable_id' => $request->id, 'payload' => ['email' => ['title' => 'Reminder', 'body' => 'Visit tomorrow']]]);
        $request->update(['staff_managed' => true]);
        app(MarketplaceNotificationService::class)->sendReservedEmail($owner, $delivery);
        $this->assertSame('suppressed', $delivery->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_queued_notification_rechecks_silence_for_every_channel(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $request = CareRequest::create(['address_line1' => '100 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601'] + ['family_user_id' => $owner->id, 'staff_managed' => true, 'title' => 'Managed care', 'status' => 'open', 'request_type' => 'one_time']);
        $notice = new MarketplaceEventNotification(MarketplaceEvent::SHIFT_REMINDER_24H, 'Reminder', 'Visit tomorrow', null, ['mail', 'database'], ['care_request_id' => $request->id]);
        $this->assertFalse($notice->shouldSend($owner, 'mail'));
        $this->assertFalse($notice->shouldSend($owner, 'database'));
    }

    public function test_legacy_queued_email_can_resolve_its_managed_request_from_delivery_tracking(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $request = CareRequest::create(['address_line1' => '100 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'family_user_id' => $owner->id, 'staff_managed' => true, 'title' => 'Managed care', 'status' => 'open', 'request_type' => 'one_time']);
        $delivery = MarketplaceNotificationDelivery::create(['user_id' => $owner->id, 'event_key' => MarketplaceEvent::SHIFT_REMINDER_24H,
            'channel' => 'email', 'status' => 'queued', 'notifiable_type' => CareRequest::class, 'notifiable_id' => $request->id]);
        $notice = new MarketplaceEventNotification(MarketplaceEvent::SHIFT_REMINDER_24H, 'Reminder', 'Visit tomorrow', null,
            ['mail'], ['tracking' => ['delivery_id' => $delivery->id]]);
        $this->assertFalse($notice->shouldSend($owner, 'mail'));
        $this->assertSame('suppressed', $delivery->fresh()->status);
    }

    public function test_generated_recurring_visits_inherit_silence_from_source(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role' => 'family']);
        $family = app(FamilyAccountContext::class)->account($owner);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $source = CareRequest::create(['address_line1' => '100 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601'] + ['family_user_id' => $owner->id, 'family_account_id' => $family->id, 'staff_managed' => true, 'title' => 'Recurring care', 'status' => 'filled', 'request_type' => 'recurring']);
        $plan = CarePlan::create(['family_user_id' => $owner->id, 'family_account_id' => $family->id, 'caregiver_user_id' => $caregiver->id,
            'source_care_request_id' => $source->id, 'title' => 'Recurring care', 'status' => 'active', 'starts_on' => now()->toDateString(), 'timezone' => 'America/New_York', 'hourly_rate' => 30, 'schedule_days' => [1], 'schedule_start_time' => '10:00', 'schedule_end_time' => '12:00']);
        $child = CareRequest::create(['address_line1' => '100 Main St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601'] + ['family_user_id' => $owner->id, 'family_account_id' => $family->id, 'care_plan_id' => $plan->id, 'is_system_generated' => true, 'title' => 'Next visit', 'status' => 'filled', 'request_type' => 'one_time']);
        $booking = CareBooking::create(['care_request_id' => $child->id, 'care_plan_id' => $plan->id, 'family_user_id' => $owner->id, 'family_account_id' => $family->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'scheduled']);
        app(MarketplaceNotificationService::class)->notify($owner, MarketplaceEvent::SHIFT_REMINDER_24H, 'Reminder', 'Visit tomorrow', subject: $booking);
        Notification::assertNothingSent();
        $this->assertDatabaseMissing('marketplace_notification_deliveries', ['user_id' => $owner->id]);
    }
}
