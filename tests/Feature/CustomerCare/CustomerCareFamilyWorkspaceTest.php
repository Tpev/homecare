<?php

namespace Tests\Feature\CustomerCare;

use App\Livewire\CustomerCare\ManageCareRequest;
use App\Models\CaregiverProfile;
use App\Models\CareRequestApplication;
use App\Models\CareTask;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use App\Services\CareHours\ManualCareHourCreditService;
use App\Services\CustomerCare\ManagedCareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerCareFamilyWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['marketplace.caregiver_prelaunch_mode' => false]);
    }

    public function test_staff_and_family_render_the_same_request_view_and_caregiver_cards(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        $caregiver = $this->caregiver();
        foreach ([$actor, $customer] as $viewer) {
            $class = $viewer->role === 'family' ? \App\Livewire\Family\ManageCareRequest::class : ManageCareRequest::class;
            Livewire::actingAs($viewer)->test($class, ['careRequest' => $request->id])
                ->assertViewIs('livewire.family.manage-care-request')
                ->call('setCaregiverView', 'search')
                ->assertSee('Care &amp; schedule details', false)
                ->assertSee('Search &amp; invite', false)
                ->assertSee('data-testid="discovery-row"', false)
                ->assertSee($caregiver->name)
                ->assertSee('View profile')
                ->set('caregiverSearch', 'unmatched-person')
                ->assertSee('No caregivers found')
                ->call('clearCaregiverSearch')->assertSee($caregiver->name);
        }
    }

    public function test_invitation_uses_family_context_and_real_staff_author_without_customer_notification(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        $caregiver = $this->caregiver();
        Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setCaregiverView', 'search')
            ->call('beginCaregiverInvitation', $caregiver->id)
            ->set('caregiverInviteMessage', 'The care team would like to invite you.')
            ->call('sendCaregiverInvitation')->assertHasNoErrors()
            ->call('setCaregiverView', 'invited')->assertSee('Message caregiver');
        $this->assertDatabaseHas('care_request_invitations', ['care_request_id' => $request->id,
            'family_user_id' => $customer->id, 'invited_by_user_id' => $actor->id, 'caregiver_user_id' => $caregiver->id]);
        $this->assertDatabaseHas('customer_care_actions', ['care_request_id' => $request->id, 'actor_user_id' => $actor->id, 'action' => 'caregiver_invited']);
        Notification::assertNotSentTo($customer, \App\Notifications\MarketplaceEventNotification::class);
    }

    public function test_shared_hiring_and_hours_approval_use_only_prepaid_funds_and_staff_attribution(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        $wallet = app(ManualCareHourCreditService::class)->credit($actor, $customer, $this->creditInput());
        $caregiver = $this->caregiver();
        $application = $request->applications()->create(['caregiver_user_id' => $caregiver->id, 'status' => 'applied', 'proposed_rate' => 28]);
        $component = Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id]);
        $component->call('reviewHire', $application->id)->assertSee('Confirm hire')->assertDontSee('Usual card payment')
            ->set('careHourAccountId', null)->assertSet('careHourAccountId', $wallet->id)
            ->call('confirmReviewedHire')->assertHasNoErrors();
        $booking = $request->fresh()->booking;
        $this->assertNotNull($booking);
        $this->assertNull($booking->family_terms_accepted_at);
        $this->assertSame(120, $wallet->balance()['reserved_minutes']);
        $this->travel(3)->days();
        $component->call('reviewCompletion')
            ->set('actualStartAt', now()->subHours(2)->format('Y-m-d\TH:i'))
            ->set('actualEndAt', now()->format('Y-m-d\TH:i'))
            ->set('actualBreakMinutes', 15)
            ->call('confirmReviewedCompletion')->assertHasNoErrors()
            ->call('reviewCompletion')->call('confirmReviewedCompletion')->assertHasNoErrors();
        $this->assertSame(105, $wallet->balance()['used_minutes']);
        $this->assertSame($actor->id, $booking->fresh()->family_confirmed_by_user_id);
        $this->assertDatabaseCount('care_booking_payments', 0);
        Notification::assertNotSentTo($customer, \App\Notifications\MarketplaceEventNotification::class);
    }

    public function test_staff_cannot_call_family_card_or_customer_review_actions(): void
    {
        [$actor, , $request] = $this->requestFixture();
        $component = Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id]);
        foreach (['startPaymentAuthorization', 'submitReview', 'failStripeAuthorization'] as $method) {
            $component->call($method)->assertHasErrors('customer_care');
        }
        $component->call('finalizeStripeAuthorization', 'pi_untrusted')->assertHasErrors('customer_care');
        $this->assertDatabaseCount('care_reviews', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_changed_offers_and_insufficient_hours_cannot_be_confirmed(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        app(ManualCareHourCreditService::class)->credit($actor, $customer, array_replace($this->creditInput(), ['hours' => 0.25]));
        $application = $request->applications()->create(['caregiver_user_id' => $this->caregiver()->id, 'status' => 'applied', 'proposed_rate' => 28]);
        $component = Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $application->id);
        $application->increment('offer_version');
        $component->call('confirmReviewedHire')->assertHasErrors('hire')
            ->call('reviewHire', $application->id)->call('confirmReviewedHire')->assertHasErrors('hire');
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertSame('applied', $application->fresh()->status);
        $this->assertDatabaseCount('care_booking_payments', 0);
        Notification::assertNotSentTo($customer, \App\Notifications\MarketplaceEventNotification::class);
    }

    public function test_no_show_returns_prepaid_hours_without_customer_card_actions(): void
    {
        [$actor, $customer, $request] = $this->requestFixture();
        $wallet = app(ManualCareHourCreditService::class)->credit($actor, $customer, $this->creditInput());
        app(ManagedCareService::class)->hire($actor, $request, $this->caregiver()->id, 'Caregiver confirmed by the care team.');
        $component = Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id]);
        $component->call('markNoShow')->assertHasErrors('customer_care');
        $this->travel(3)->days();
        $component->call('markNoShow')->assertHasNoErrors();
        $booking = $request->fresh()->booking;
        $this->assertTrue($booking->no_show_flag);
        $this->assertSame($actor->id, $booking->cancelled_by_user_id);
        $this->assertSame(300, $wallet->balance()['available_minutes']);
        $this->assertDatabaseCount('care_booking_payments', 0);
        Notification::assertNotSentTo($customer, \App\Notifications\MarketplaceEventNotification::class);
    }

    public function test_livewire_rechecks_revoked_staff_and_archived_or_unmanaged_requests(): void
    {
        [$actor, , $request] = $this->requestFixture();
        $component = Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id]);
        User::whereKey($actor->id)->update(['access_revoked_at' => now()]);
        $component->call('setCaregiverView', 'search')->assertForbidden();
        User::whereKey($actor->id)->update(['access_revoked_at' => null]);
        $component = Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id]);
        $request->update(['staff_archived_at' => now()]);
        $component->call('withdrawRequest')->assertForbidden();
        $request->update(['staff_archived_at' => null, 'staff_managed' => false]);
        Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])->assertForbidden();
    }

    public function test_other_account_applicants_and_conversations_cannot_be_selected(): void
    {
        [$actor, , $request] = $this->requestFixture();
        [, , $otherRequest] = $this->requestFixture();
        $caregiver = $this->caregiver();
        $application = $otherRequest->applications()->create(['caregiver_user_id' => $caregiver->id, 'status' => 'applied', 'proposed_rate' => 28]);
        try {
            Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])
                ->call('reviewHire', $application->id);
            $this->fail('An unrelated family application must not be available.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            $this->assertSame(CareRequestApplication::class, $exception->getModel());
        }
        Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('messageCaregiver', $caregiver->id)->assertNotFound();
        $this->assertDatabaseCount('care_request_conversations', 0);
    }

    public function test_staff_roles_can_open_the_page_but_customers_cannot_use_the_staff_component(): void
    {
        [, , $request] = $this->requestFixture();
        foreach (['admin', 'sdr', 'customer_success'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('customer-care.show', $request))->assertOk()
                ->assertSeeLivewire(ManageCareRequest::class)->assertSee('Remove request');
        }
        foreach (['family', 'caregiver', 'sales'] as $role) {
            Livewire::actingAs(User::factory()->create(['role' => $role]))
                ->test(ManageCareRequest::class, ['careRequest' => $request->id])->assertForbidden();
        }
    }

    private function requestFixture(): array
    {
        $actor = User::factory()->create(['role' => 'sdr']);
        $customer = User::factory()->create(['role' => 'family']);
        $request = app(ManagedCareService::class)->save($actor, $customer, $this->requestInput());

        return [$actor, $customer, $request];
    }

    private function requestInput(): array
    {
        return ['request_key' => (string) Str::uuid(), 'title' => 'Afternoon companionship', 'scope_of_work' => 'Companionship and meal preparation.',
            'recipient_name' => 'Jamie', 'address_line1' => '100 Main Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'request_type' => 'one_time', 'requested_start_at' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'requested_end_at' => now()->addDays(2)->setTime(12, 0)->toDateTimeString(), 'status' => 'open',
            'tasks' => [CareTask::firstOrCreate(['name' => 'Companionship'])->id]];
    }

    private function creditInput(): array
    {
        return ['request_key' => (string) Str::uuid(), 'hours' => 5, 'amount' => '155.00', 'reference' => 'BANK-001', 'reason' => 'Offline bank transfer received.'];
    }

    private function caregiver(): User
    {
        $user = User::factory()->create(['role' => 'caregiver', 'city' => 'Raleigh', 'state' => 'NC']);
        $profile = CaregiverProfile::create(['user_id' => $user->id, 'slug' => 'staff-care-'.$user->id,
            'status' => 'active', 'screening_initial_approved_at' => now(), 'bio' => 'Experienced caregiver offering companionship and practical help.',
            'platform_hourly_rate' => 28, 'years_experience' => 5, 'service_area_zip' => '27601', 'service_radius_miles' => 10,
            'is_accepting_new_clients' => true, 'identity_verified_at' => now()]);
        $profile->skills()->sync([Skill::firstOrCreate(['name' => 'Companionship'])->id]);
        $profile->languages()->sync([Language::firstOrCreate(['name' => 'English'])->id]);
        $profile->availabilities()->create(['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '12:00']);

        return $user;
    }
}
