<?php

namespace Tests\Feature;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestMessage;
use App\Models\FamilyAccountMember;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\MarketplaceEventNotification;
use App\Services\FamilyAccounts\FamilyAccountAccessService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\FamilyAccounts\FamilyAccountInvitationService;
use App\Services\FamilyAccounts\FamilyAccountOwnershipService;
use App\Services\Notifications\MarketplaceNotificationService;
use App\Services\Payments\FamilyBillingService;
use App\Services\Payments\StripeClient;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FamilyAccountSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_sees_shared_requests_and_new_records_keep_owner_compatibility_with_actor(): void
    {
        [$owner, $member] = $this->ownerAndMember();
        $request = $this->requestFor($owner, 'Shared morning visit');
        $otherOwner = User::factory()->create(['role' => 'family']);
        $otherRequest = $this->requestFor($otherOwner, 'Other household visit');

        $this->actingAs($member)
            ->get(route('family.requests.show', $request))
            ->assertOk();
        $this->actingAs($member)
            ->get(route('family.requests.show', $otherRequest))
            ->assertNotFound();

        $createdByMember = CareRequest::query()->create([
            'family_account_id' => $otherRequest->family_account_id,
            'family_user_id' => $member->id,
            'created_by_user_id' => $member->id,
            'title' => 'Member-created visit',
            'status' => CareRequest::STATUS_OPEN,
            'address_line1' => '100 Shared Way',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);

        $this->assertSame($owner->id, $createdByMember->family_user_id);
        $this->assertSame($member->id, $createdByMember->created_by_user_id);
        $this->assertSame($request->family_account_id, $createdByMember->family_account_id);
    }

    public function test_family_message_reads_are_independent_for_each_member(): void
    {
        [$owner, $member] = $this->ownerAndMember();
        $secondMember = User::factory()->create(['role' => 'family', 'email' => 'second@example.com']);
        $this->accept($owner, $secondMember);
        $request = $this->requestFor($owner);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_SHORTLISTED,
        ]);
        $conversation = CareRequestConversation::findOrCreateForApplication($application->load('careRequest'), $owner->id);
        $message = CareRequestMessage::query()->create([
            'care_request_conversation_id' => $conversation->id,
            'sender_user_id' => $caregiver->id,
            'body' => 'I can help with this visit.',
        ]);
        $conversation->forceFill([
            'last_message_at' => $message->created_at,
            'last_message_sender_id' => $caregiver->id,
        ])->save();

        $conversation->markRead($member);

        $this->assertNotNull($conversation->fresh()->lastReadAtFor($member));
        $this->assertNull($conversation->fresh()->lastReadAtFor($secondMember));
        $this->assertTrue($conversation->isParticipant($secondMember));
    }

    public function test_shared_care_notifications_fan_out_to_active_family_members(): void
    {
        Notification::fake();
        [$owner, $member] = $this->ownerAndMember();
        $request = $this->requestFor($owner);

        app(MarketplaceNotificationService::class)->notify(
            recipients: $owner,
            eventKey: MarketplaceEvent::MESSAGE_RECEIVED,
            title: 'New care message',
            body: 'A caregiver replied.',
            url: route('family.requests.show', $request),
            subject: $request,
            dedupeKey: 'family-sharing-test-'.$request->id,
        );

        Notification::assertSentTo($owner, MarketplaceEventNotification::class);
        Notification::assertSentTo($member, MarketplaceEventNotification::class);
    }

    public function test_child_record_notifications_resolve_the_family_account_and_fan_out(): void
    {
        Notification::fake();
        [$owner, $member] = $this->ownerAndMember();
        $request = $this->requestFor($owner);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $application = CareRequestApplication::query()->create([
            'care_request_id' => $request->id,
            'caregiver_user_id' => $caregiver->id,
            'status' => CareRequestApplication::STATUS_APPLIED,
        ]);

        app(MarketplaceNotificationService::class)->notify(
            recipients: $owner,
            eventKey: MarketplaceEvent::APPLICATION_SUBMITTED,
            title: 'A caregiver applied',
            body: 'Review the application.',
            subject: $application,
            dedupeKey: 'family-child-notification-'.$application->id,
        );

        Notification::assertSentTo($owner, MarketplaceEventNotification::class);
        Notification::assertSentTo($member, MarketplaceEventNotification::class);
    }

    public function test_removed_members_are_not_notification_recipients(): void
    {
        Notification::fake();
        [$owner, $member] = $this->ownerAndMember();
        $account = app(FamilyAccountContext::class)->account($owner);
        $membership = $account->activeMemberships()->where('user_id', $member->id)->firstOrFail();
        app(FamilyAccountAccessService::class)->remove($owner, $membership);
        $request = $this->requestFor($owner);

        app(MarketplaceNotificationService::class)->notify(
            recipients: $owner,
            eventKey: MarketplaceEvent::MESSAGE_RECEIVED,
            title: 'New care message',
            body: 'A caregiver replied.',
            subject: $request,
            dedupeKey: 'family-removed-notification-'.$request->id,
        );

        Notification::assertSentTo($owner, MarketplaceEventNotification::class);
        Notification::assertNotSentTo($member, MarketplaceEventNotification::class);
    }

    public function test_billing_customer_is_shared_but_only_owner_can_change_card(): void
    {
        config(['services.stripe.bypass' => true]);
        [$owner, $member] = $this->ownerAndMember();
        $stripe = app(StripeClient::class);

        $customerId = $stripe->ensureFamilyCustomer($member);
        $account = app(FamilyAccountContext::class)->account($member)->fresh();

        $this->assertSame($customerId, $account->stripe_customer_id);
        $this->assertSame($customerId, $owner->fresh()->stripe_customer_id);
        $this->assertTrue(app(FamilyBillingService::class)->summaryFor($member)['ready']);

        try {
            $stripe->createFamilySetupCheckoutSession($member, '/success', '/cancel');
            $this->fail('A member changed the family payment method.');
        } catch (PaymentException $exception) {
            $this->assertStringContainsString('account owner', $exception->userMessage);
        }

        $this->assertSame('/success', $stripe->createFamilySetupCheckoutSession($owner, '/success', '/cancel'));
    }

    public function test_shared_support_is_visible_with_per_member_reads_and_billing_support_is_private(): void
    {
        [$owner, $member] = $this->ownerAndMember();
        $secondMember = User::factory()->create(['role' => 'family', 'email' => 'support.second@example.com']);
        $this->accept($owner, $secondMember);
        $account = app(FamilyAccountContext::class)->account($owner);
        $admin = User::factory()->create(['role' => 'admin']);
        $shared = SupportTicket::query()->create([
            'family_account_id' => $account->id,
            'family_visibility' => 'shared_care',
            'opener_user_id' => $owner->id,
            'category' => 'general',
            'subject' => 'Help with tomorrow visit',
            'description' => 'We need help changing the visit time.',
            'last_public_message_at' => now(),
            'last_public_message_sender_id' => $admin->id,
        ]);
        $private = SupportTicket::query()->create([
            'family_account_id' => $account->id,
            'family_visibility' => 'owner_only',
            'opener_user_id' => $owner->id,
            'category' => 'billing',
            'subject' => 'Private billing question',
            'description' => 'Please help with the payment method.',
        ]);
        $sharedMessage = SupportTicketMessage::query()->create([
            'support_ticket_id' => $shared->id,
            'sender_user_id' => $admin->id,
            'kind' => SupportTicketMessage::KIND_PUBLIC,
            'body' => 'We can help change the visit time.',
        ]);
        $privateMessage = SupportTicketMessage::query()->create([
            'support_ticket_id' => $private->id,
            'sender_user_id' => $admin->id,
            'kind' => SupportTicketMessage::KIND_PUBLIC,
            'body' => 'We can help with the payment method.',
        ]);

        $this->assertTrue($member->can('view', $shared));
        $this->assertFalse($member->can('view', $private));
        $this->assertTrue($owner->can('view', $private));
        $this->assertTrue(SupportTicketMessage::query()->visibleTo($member)->whereKey($sharedMessage->id)->exists());
        $this->assertFalse(SupportTicketMessage::query()->visibleTo($member)->whereKey($privateMessage->id)->exists());
        $this->assertTrue(SupportTicketMessage::query()->visibleTo($owner)->whereKey($privateMessage->id)->exists());
        $this->assertTrue($shared->isUnreadFor($member));
        $this->assertTrue($shared->isUnreadFor($secondMember));

        $shared->markReadFor($member);
        $this->assertFalse($shared->fresh()->isUnreadFor($member));
        $this->assertTrue($shared->fresh()->isUnreadFor($secondMember));
    }

    public function test_admin_ownership_transfer_remaps_legacy_owner_and_keeps_actor_history(): void
    {
        [$owner, $member] = $this->ownerAndMember();
        $account = app(FamilyAccountContext::class)->account($owner);
        $account->forceFill(['stripe_customer_id' => 'cus_transfer_test'])->save();
        $owner->forceFill(['stripe_customer_id' => 'cus_transfer_test'])->save();
        $request = $this->requestFor($owner);
        $admin = User::factory()->create(['role' => 'admin']);
        $destination = $account->activeMemberships()->where('user_id', $member->id)->firstOrFail();
        $privateTicket = SupportTicket::query()->create([
            'family_account_id' => $account->id,
            'family_visibility' => 'owner_only',
            'opener_user_id' => $owner->id,
            'category' => 'billing',
            'subject' => 'Private payment method question',
            'description' => 'Please help update the account payment method.',
        ]);

        app(FamilyAccountOwnershipService::class)->transfer(
            $admin,
            $account,
            $destination,
            'Both family members verified by support call.',
        );

        $this->assertSame($member->id, $account->fresh()->owner_user_id);
        $this->assertSame($member->id, $request->fresh()->family_user_id);
        $this->assertSame('cus_transfer_test', $member->fresh()->stripe_customer_id);
        $this->assertNull($owner->fresh()->stripe_customer_id);
        $this->assertFalse($owner->can('view', $privateTicket));
        $this->assertTrue($member->can('view', $privateTicket));
        $this->assertDatabaseHas('family_account_members', [
            'family_account_id' => $account->id,
            'user_id' => $owner->id,
            'access_level' => 'member',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('family_account_activity_logs', [
            'family_account_id' => $account->id,
            'actor_user_id' => $admin->id,
            'action' => 'ownership_transferred',
            'subject_user_id' => $member->id,
        ]);
        $this->assertSame(0, Artisan::call('homecare:verify-family-accounts'));
    }

    public function test_ownership_transfer_does_not_change_local_state_when_billing_contact_sync_fails(): void
    {
        [$owner, $member] = $this->ownerAndMember();
        $account = app(FamilyAccountContext::class)->account($owner);
        $account->forceFill(['stripe_customer_id' => 'cus_transfer_failure'])->save();
        $admin = User::factory()->create(['role' => 'admin']);
        $destination = $account->activeMemberships()->where('user_id', $member->id)->firstOrFail();
        $stripe = \Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('updateFamilyCustomerOwner')
            ->once()
            ->andThrow(new PaymentException('Billing contact could not be updated.'));

        try {
            (new FamilyAccountOwnershipService($stripe))->transfer(
                $admin,
                $account,
                $destination,
                'Both family members verified by support call.',
            );
            $this->fail('Ownership changed despite a failed billing-contact update.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertStringContainsString('Billing contact', $exception->errors()['transfer'][0]);
        }

        $this->assertSame($owner->id, $account->fresh()->owner_user_id);
        $this->assertSame(FamilyAccountMember::ACCESS_OWNER, $account->memberships()->where('user_id', $owner->id)->firstOrFail()->access_level);
        $this->assertSame(FamilyAccountMember::ACCESS_MEMBER, $destination->fresh()->access_level);
    }

    /** @return array{User,User} */
    private function ownerAndMember(): array
    {
        $owner = User::factory()->create(['role' => 'family']);
        $member = User::factory()->create(['role' => 'family', 'email' => 'member@example.com']);
        $this->accept($owner, $member);

        return [$owner, $member];
    }

    private function accept(User $owner, User $member): void
    {
        $issued = app(FamilyAccountInvitationService::class)->send($owner, $member->email, '127.0.0.1');
        app(FamilyAccountInvitationService::class)->accept($member, $issued['token']);
    }

    private function requestFor(User $owner, string $title = 'Shared care request'): CareRequest
    {
        $ownership = app(FamilyAccountContext::class)->ownershipAttributes($owner);

        return CareRequest::query()->create([
            ...$ownership,
            'created_by_user_id' => $owner->id,
            'title' => $title,
            'status' => CareRequest::STATUS_OPEN,
            'address_line1' => '100 Family Lane',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
    }
}
