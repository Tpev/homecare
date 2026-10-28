<?php

namespace Tests\Feature\CareHours;

use App\Livewire\Family\ManageCareRequest;
use App\Livewire\Family\RegularCareComposer;
use App\Models\CareBooking;
use App\Models\CareBookingChangeRequest;
use App\Models\CareHourAccount;
use App\Models\CareHourContributionVerification;
use App\Models\CareHourPaymentOperation;
use App\Models\CareHourPurchase;
use App\Models\CareRecipient;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\FamilyAccountMember;
use App\Models\User;
use App\Notifications\CareHourVerificationNotification;
use App\Services\CareHours\CareHourContributionService;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\CareHours\CareHourNavigation;
use App\Services\CareHours\CareHourPurchaseService;
use App\Services\CareHours\PrepaidBookingFundingService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CareHoursScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['care_hours.enabled' => true, 'care_hours.enrollment_enabled' => true, 'care_hours.sales_enabled' => true, 'care_hours.contributions_enabled' => true]);
        Notification::fake();
    }

    public function test_existing_card_billing_is_unchanged_when_feature_is_disabled_and_no_hours_exist(): void
    {
        config(['care_hours.enabled' => false]);
        $owner = User::factory()->create(['role' => 'family']);
        $this->assertFalse(app(CareHourNavigation::class)->visible($owner));
        $this->assertDatabaseCount('family_accounts', 0);
        $this->actingAs($owner)->get(route('family.billing.show'))->assertOk()
            ->assertSee('Billing &amp; Payments', false)->assertSee('Add a card once.')
            ->assertDontSee('Prepaid care hours')->assertSee('How charging works');
    }

    public function test_balances_remain_accessible_when_new_sales_and_discovery_are_paused(): void
    {
        [$owner, $account] = $this->account();
        $this->buy($account, $owner, 10);
        config(['care_hours.enabled' => false, 'care_hours.sales_enabled' => false]);
        $this->assertTrue(app(CareHourNavigation::class)->visible($owner));
        $this->actingAs($owner)->get(route('family.care-hours.show', $account))->assertOk()
            ->assertSee('11 h')->assertDontSee('Buy prepaid care')->assertDontSee('Purchase processing fee confirmed');
        $this->get(route('family.care-hours.packages', $account))->assertForbidden();
    }

    public function test_family_pages_are_account_scoped_and_contributor_contact_is_private(): void
    {
        [$owner, $account] = $this->account();
        [$other, $otherAccount] = $this->account('Another person');
        $this->buy($account, $owner, 5)->update(['buyer_email' => 'private-contributor@example.test']);
        $this->actingAs($other)->get(route('family.care-hours.show', $account))->assertForbidden();
        $this->get(route('family.care-hours.packages', $account))->assertForbidden();
        $this->get(route('admin.care-hours.show', $otherAccount))->assertForbidden();
        $this->actingAs($owner)->get(route('family.care-hours.show', $account))->assertOk()
            ->assertDontSee('private-contributor@example.test')->assertSee('Available')->assertSee('5 h');
    }

    public function test_purchase_requires_terms_and_uses_confirmed_quote_once_without_saving_a_card(): void
    {
        [$owner, $account] = $this->account();
        $quote = app(CareHourPurchaseService::class)->quote($account, 10);
        $payload = ['hours' => 10, 'quotes' => [10 => $quote['quote_key']], 'request_key' => (string) Str::uuid()];
        $this->actingAs($owner)->from(route('family.care-hours.packages', $account))
            ->post(route('family.care-hours.checkout', $account), $payload)->assertSessionHasErrors('terms');
        $this->assertDatabaseCount('care_hour_purchases', 0);
        $response = $this->post(route('family.care-hours.checkout', $account), $payload + ['terms' => 1]);
        $response->assertRedirect();
        $purchase = CareHourPurchase::sole();
        $this->get(route('family.care-hours.purchase', $purchase->public_id))->assertOk()->assertSee('11 h added for Margaret');
        $this->post(route('family.care-hours.checkout', $account), $payload + ['terms' => 1])->assertRedirect();
        $this->assertDatabaseCount('care_hour_purchases', 1);
        $this->assertSame(660, app(CareHourLedgerService::class)->balance($account)['available_minutes']);
        $this->assertNull($owner->fresh()->stripe_customer_id);
        $this->assertNull($account->familyAccount->fresh()->stripe_customer_id);
    }

    public function test_non_owner_cannot_manage_private_links(): void
    {
        [$owner, $account] = $this->account();
        $member = User::factory()->create(['role' => 'family']);
        FamilyAccountMember::create(['family_account_id' => $account->family_account_id, 'user_id' => $member->id, 'access_level' => 'member', 'status' => 'active', 'joined_at' => now()]);
        $this->actingAs($member)->get(route('family.care-hours.show', $account))->assertOk()->assertDontSee('Let someone contribute');
        $this->post(route('family.care-hours.link.create', $account))->assertForbidden();
        $link = app(CareHourContributionService::class)->createLink($account, $owner)['link'];
        $this->delete(route('family.care-hours.link.revoke', [$account, $link]))->assertForbidden();
        $this->assertNull($link->fresh()->revoked_at);
    }

    public function test_private_contribution_requires_verified_email_and_receipt_stays_private(): void
    {
        [$owner, $account] = $this->account();
        $created = app(CareHourContributionService::class)->createLink($account, $owner);
        $token = $created['token'];
        $this->get(route('care-hours.contribute', $token))->assertOk()->assertSee('Give care hours to Margaret')
            ->assertDontSee($owner->email)->assertDontSee('Care hours balance')->assertDontSee('Contributions and usage')
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $quote = app(CareHourPurchaseService::class)->quote($account, 10);
        $payload = ['hours' => 10, 'quotes' => [10 => $quote['quote_key']], 'request_key' => (string) Str::uuid(), 'terms' => 1];
        $this->post(route('care-hours.contribute.checkout', $token), $payload)->assertForbidden();
        $this->post(route('care-hours.contribute.verify', $token), ['name' => 'Donor', 'email' => 'donor@example.test'])->assertRedirect();
        $code = null;
        Notification::assertSentOnDemand(CareHourVerificationNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });
        $this->post(route('care-hours.contribute.confirm', $token), ['code' => $code])->assertRedirect();
        $this->post(route('care-hours.contribute.checkout', $token), $payload)->assertRedirect();
        $purchase = CareHourPurchase::sole();
        $account->update(['display_name' => 'Private legal name never shared']);
        $this->get(route('care-hours.receipt', $purchase->public_id))->assertOk()->assertSee('Your care hours are ready')->assertDontSee('Available')->assertDontSee($owner->email);
        $this->get(route('care-hours.receipt', $purchase->public_id))->assertSee('Margaret')->assertDontSee('Private legal name never shared');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('family_account_members', 1);
        $this->flushSession();
        $this->get(route('care-hours.receipt', $purchase->public_id))->assertOk()->assertSee('Verify the email used')->assertDontSee('Margaret')->assertDontSee('donor@example.test')->assertDontSee('$310');
    }

    public function test_revoked_link_stops_new_contributions_but_existing_buyer_can_recover_receipt(): void
    {
        [$owner, $account] = $this->account();
        $created = app(CareHourContributionService::class)->createLink($account, $owner);
        $link = $created['link'];
        $verification = CareHourContributionVerification::create([
            'id' => (string) Str::uuid(), 'care_hour_contribution_link_id' => $link->id, 'name' => 'Donor', 'email' => 'donor@example.test',
            'code_hash' => bcrypt('123456'), 'expires_at' => now()->addMinutes(10), 'verified_at' => now(),
            'token_hash' => hash('sha256', 'verified-receipt-token'), 'token_expires_at' => now()->addDay(),
        ]);
        $service = app(CareHourPurchaseService::class);
        $quote = $service->quote($account, 5);
        $purchase = $service->startContribution($link, 'verified-receipt-token', 5, $quote['quote_key'], (string) Str::uuid(), 'http://localhost/success/{PURCHASE_ID}', 'http://localhost/cancel/{PURCHASE_ID}');
        $service->refresh($purchase);
        app(CareHourContributionService::class)->revoke($link, $owner);
        $this->get(route('care-hours.contribute', $created['token']))->assertStatus(410)->assertDontSee('Margaret');
        $this->post(route('care-hours.receipt.verify', $purchase->public_id), ['email' => 'donor@example.test'])->assertRedirect();
        $code = null;
        Notification::assertSentOnDemand(CareHourVerificationNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });
        $this->post(route('care-hours.receipt.confirm', $purchase->public_id), ['code' => $code])->assertRedirect();
        $this->get(route('care-hours.receipt', $purchase->public_id))->assertOk()->assertSee('5 h added for Margaret');
    }

    public function test_removed_member_keeps_own_receipt_but_cannot_read_current_balance(): void
    {
        [$owner, $account] = $this->account();
        $member = User::factory()->create(['role' => 'family']);
        $membership = FamilyAccountMember::create(['family_account_id' => $account->family_account_id, 'user_id' => $member->id, 'access_level' => 'member', 'status' => 'active', 'joined_at' => now()]);
        $purchase = $this->buy($account, $member, 5);
        $membership->update(['status' => 'removed']);
        $this->actingAs($member)->get(route('family.care-hours.purchase', $purchase->public_id))->assertOk()->assertSee('Purchase receipt');
        $this->get(route('family.care-hours.show', $account))->assertForbidden();
        $this->actingAs($owner)->get(route('family.care-hours.purchase', $purchase->public_id))->assertForbidden();
    }

    public function test_existing_recipient_can_be_explicitly_linked_without_duplicate_balance(): void
    {
        [$owner, $account] = $this->account();
        $request = CareRequest::create(['family_user_id' => $owner->id, 'family_account_id' => $account->family_account_id, 'title' => 'Care for Margaret', 'request_type' => 'one_time', 'status' => 'open', 'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601']);
        $recipient = CareRecipient::create(['care_request_id' => $request->id, 'full_name' => 'Margaret', 'relationship_to_family' => 'Mother']);
        $this->actingAs($owner)->post(route('family.care-hours.enroll'), ['recipient' => 'recipient:'.$recipient->id])->assertSessionHasErrors('balance_target');
        $this->post(route('family.care-hours.enroll'), ['recipient' => 'recipient:'.$recipient->id, 'balance_target' => $account->id])->assertRedirect(route('family.care-hours.show', $account));
        $this->assertDatabaseCount('care_hour_accounts', 1);
        $this->assertDatabaseHas('care_hour_recipient_links', ['care_recipient_id' => $recipient->id, 'care_hour_account_id' => $account->id]);
    }

    public function test_admin_can_search_read_and_audit_a_provider_recheck_without_manual_credit_controls(): void
    {
        [$owner, $account] = $this->account();
        $purchase = $this->buy($account, $owner, 10);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.care-hours.index', ['search' => 'Margaret']))->assertOk()->assertSee('Margaret');
        $this->get(route('admin.care-hours.show', $account))->assertOk()->assertSee('Visit funding and caregiver settlement')->assertSee('11 h');
        $this->post(route('admin.care-hours.recheck', [$account, $purchase]), ['reason' => 'Verify payment after support question'])->assertRedirect();
        $this->assertDatabaseHas('family_account_activity_logs', ['action' => 'care_hours_purchase_recheck', 'actor_user_id' => $admin->id]);
        $this->assertSame(660, app(CareHourLedgerService::class)->balance($account)['available_minutes']);
    }

    public function test_receipt_survives_provider_outage_without_leaking_provider_error(): void
    {
        [$owner, $account] = $this->account();
        $purchase = $this->buy($account, $owner, 5);
        $this->mock(\App\Services\CareHours\CareHourPaymentGateway::class)->shouldReceive('retrieveCheckout')
            ->andThrow(new \App\Exceptions\Payments\PaymentException('Provider unavailable', 'private-provider-debug-value'));
        $this->actingAs($owner)->get(route('family.care-hours.purchase', $purchase->public_id))->assertOk()
            ->assertSee('We could not check the payment provider')->assertDontSee('private-provider-debug-value')->assertSee('5 h added for Margaret');
    }

    public function test_admin_attention_filter_shows_held_accounts_and_hides_healthy_accounts(): void
    {
        [$owner, $account] = $this->account('Needs attention recipient');
        [$other, $healthy] = $this->account('Healthy recipient');
        $purchase = $this->buy($account, $owner, 5);
        $this->buy($healthy, $other, 5);
        app(CareHourPurchaseService::class)->holdForReversal($purchase, $purchase->amount_cents);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.care-hours.index', ['attention' => 1]))->assertOk()
            ->assertSee('Needs attention recipient')->assertSee('1 need attention')->assertDontSee('Healthy recipient');
    }

    public function test_admin_attention_includes_unissued_purchase_errors_but_not_normal_checkout_or_terminal_failure(): void
    {
        [$owner, $account] = $this->account('Stalled package recipient');
        [$other, $healthy] = $this->account('Normal checkout recipient');
        $service = app(CareHourPurchaseService::class);
        $start = fn ($balance, $buyer) => $service->start($balance, 5, $buyer, $service->quote($balance, 5)['quote_key'], (string) Str::uuid(), 'http://localhost/success/{PURCHASE_ID}', 'http://localhost/cancel/{PURCHASE_ID}');
        $purchase = $start($account, $owner);
        $purchase->update(['last_error' => 'Checkout outcome needs reconciliation.']);
        $start($healthy, $other);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.care-hours.index', ['attention' => 1]))->assertOk()
            ->assertSee('Stalled package recipient')->assertSee('1 need attention')->assertDontSee('Normal checkout recipient');
        $purchase->update(['status' => 'failed']);
        $this->get(route('admin.care-hours.index', ['attention' => 1]))->assertOk()
            ->assertDontSee('Stalled package recipient')->assertDontSee('Normal checkout recipient');
    }

    public function test_admin_attention_tracks_cancelled_visit_refunds_without_a_settlement_and_clears_after_recovery(): void
    {
        [$owner, $account] = $this->account('Refund recovery recipient');
        $this->buy($account, $owner, 5);
        [$booking] = $this->booking($owner, $account);
        $service = app(PrepaidBookingFundingService::class);
        $funding = $service->selectForBooking($booking, $account, $owner);
        $service->prepare($booking);
        $booking->update(['status' => 'cancelled']);
        $service->release($booking);
        $payment = CareHourPaymentOperation::create([
            'public_id' => (string) Str::uuid(), 'operation_key' => 'attention-shortfall',
            'care_hour_booking_funding_id' => $funding->id, 'kind' => 'shortfall', 'status' => 'captured',
            'amount_cents' => 1550, 'captured_cents' => 1550, 'stripe_payment_intent_id' => 'pi_attention_shortfall',
        ]);
        $refund = CareHourPaymentOperation::create([
            'public_id' => (string) Str::uuid(), 'operation_key' => 'attention-refund',
            'care_hour_booking_funding_id' => $funding->id, 'kind' => 'refund', 'status' => 'failed',
            'amount_cents' => 1550, 'stripe_payment_intent_id' => 'pi_attention_shortfall', 'last_error' => 'Refund failed.',
        ]);
        $this->assertDatabaseCount('care_hour_settlements', 0);
        $admin = User::factory()->create(['role' => 'admin']);
        $url = route('admin.care-hours.index', ['attention' => 1]);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Refund recovery recipient')->assertSee('1 need attention');
        $refund->update(['status' => 'pending', 'last_error' => null]);
        $this->get($url)->assertOk()->assertSee('Refund recovery recipient')->assertSee('1 need attention');
        $refund->update(['status' => 'failed']); // Historical failure must not keep a recovered visit in the queue.
        $payment->update(['refunded_cents' => 1550]);
        $this->get($url)->assertOk()->assertDontSee('Refund recovery recipient');
        $payment->update(['status' => 'authorized', 'captured_cents' => 0, 'refunded_cents' => 0]);
        $this->get($url)->assertOk()->assertSee('Refund recovery recipient');
        $payment->update(['status' => 'cancelled']);
        $this->get($url)->assertOk()->assertDontSee('Refund recovery recipient');
        $payment->update(['status' => 'checkout', 'last_error' => null]);
        $this->get($url)->assertOk()->assertDontSee('Refund recovery recipient');
        $payment->update(['last_error' => 'Provider result could not be checked.']);
        $this->get($url)->assertOk()->assertSee('Refund recovery recipient');
        $payment->update(['last_error' => null, 'created_at' => now()->subHours(24)]);
        $this->get($url)->assertOk()->assertSee('Refund recovery recipient');
        $payment->update(['status' => 'failed']);
        $this->get($url)->assertOk()->assertDontSee('Refund recovery recipient');
    }

    public function test_active_prepaid_visit_displays_coverage_without_card_setup_prompts(): void
    {
        [$owner, $account] = $this->account();
        $this->buy($account, $owner, 5);
        [$booking, $request] = $this->booking($owner, $account);
        $funding = app(PrepaidBookingFundingService::class);
        $funding->selectForBooking($booking, $account, $owner);
        $funding->prepare($booking);
        Livewire::actingAs($owner)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSee('Using care hours')->assertSee('2 h reserved for this visit')
            ->assertDontSee('Payment authorization is not ready yet')->assertDontSee('Card authorization pending');
    }

    public function test_approval_shows_actual_hours_and_released_minutes_instead_of_full_card_charge(): void
    {
        [$owner, $account] = $this->account();
        $this->buy($account, $owner, 5);
        [$booking, $request] = $this->booking($owner, $account);
        $funding = app(PrepaidBookingFundingService::class);
        $funding->selectForBooking($booking, $account, $owner);
        $funding->prepare($booking);
        $booking->update(['status' => 'completed', 'worked_minutes' => 90, 'completed_at' => now(), 'timesheet_submitted_at' => now()]);
        Livewire::actingAs($owner)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSee('1 h 30 min from care hours')->assertSee('$0.00 remaining')->call('reviewCompletion')
            ->assertSee('Care hours to use')->assertSee('1 h 30 min')->assertSee('Hours to return')->assertSee('30 min')
            ->assertDontSee('Approve hours and pay $46.50');
    }

    public function test_shortfall_return_reads_owned_provider_session_and_never_saves_a_card(): void
    {
        [$owner, $account] = $this->account();
        $this->buy($account, $owner, 5);
        [$booking] = $this->booking($owner, $account);
        $booking->update(['scheduled_end_at' => $booking->scheduled_start_at->copy()->addHours(6), 'expected_minutes' => 360]);
        $funding = app(PrepaidBookingFundingService::class);
        $funding->selectForBooking($booking, $account, $owner);
        $funding->prepare($booking);
        $this->actingAs($owner)->post(route('family.care-hours.shortfall', $booking))->assertRedirect(route('family.care-hours.shortfall.return', $booking));
        [$outsider] = $this->account('Another family');
        $this->actingAs($outsider)->get(route('family.care-hours.shortfall.return', $booking))->assertNotFound();
        $this->assertFalse($funding->readiness($booking)['ready']);
        $this->actingAs($owner)->get(route('family.care-hours.shortfall.return', $booking))->assertRedirect(route('family.requests.show', $booking->care_request_id));
        $this->assertTrue($funding->readiness($booking)['ready']);
        $this->assertDatabaseHas('care_hour_payment_operations', ['kind' => 'shortfall', 'status' => 'authorized', 'amount_cents' => 3100]);
        $this->assertNull($owner->fresh()->stripe_default_payment_method_id);
    }

    public function test_hire_review_defaults_to_care_hours_coverage_and_can_switch_to_card(): void
    {
        [$owner, $account] = $this->account();
        $this->buy($account, $owner, 5);
        [$booking, $request] = $this->booking($owner, $account);
        $applicationId = $booking->care_request_application_id;
        $booking->delete();
        $request->update(['status' => 'open']);
        $request->applications()->update(['status' => 'applied', 'proposed_rate' => 30]);
        $review = Livewire::actingAs($owner)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $applicationId)->assertSet('careHourAccountId', $account->id);
        $review->assertSee('Visit coverage')->assertSee('From care hours')
            ->assertSee('Remaining payment')->assertSee('$0.00')->assertSee('Nothing to pay for scheduled care.')
            ->assertDontSee('Final charges depend on approved hours.')->assertDontSee('Estimated total');
        $review->set('careHourAccountId', null)->assertSee('Estimated total')->assertSee('$62.00');
        $request->update(['requested_end_at' => $request->requested_start_at->copy()->addHours(6)]);
        Livewire::actingAs($owner)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('reviewHire', $applicationId)->set('careHourAccountId', $account->id)
            ->assertSee('Remaining payment')->assertSee('$31.00')->assertSee('1 h needs a separate payment')
            ->assertDontSee('Nothing to pay for scheduled care.')->assertDontSee('Estimated total');
    }

    public function test_recurring_composer_does_not_add_hourly_fees_to_selected_care_hours(): void
    {
        [$owner, $account] = $this->account();
        $this->buy($account, $owner, 5);
        [, $request] = $this->booking($owner, $account);
        Livewire::actingAs($owner)->test(RegularCareComposer::class, ['careRequest' => $request->id])
            ->assertSet('careHourAccountId', $account->id)->assertSee('1 care hour covers 1 hour of care.')
            ->assertSee('No extra fee for covered time.')->assertDontSee('Hourly price')
            ->assertDontSee('/hour processing fee is added to every completed visit.')
            ->set('careHourAccountId', null)->assertSee('Hourly price');
        Livewire::actingAs($owner)->test(RegularCareComposer::class, ['careRequest' => $request->id])
            ->assertSet('careHourAccountId', null)->assertSee('Hourly price');
    }

    public function test_schedule_extra_payment_stays_separate_from_original_visit_until_accepted(): void
    {
        [$owner, $account] = $this->account();
        $this->buy($account, $owner, 5);
        [$booking, $request] = $this->booking($owner, $account);
        $funding = app(PrepaidBookingFundingService::class);
        $funding->selectForBooking($booking, $account, $owner);
        $funding->prepare($booking);
        $change = CareBookingChangeRequest::create([
            'care_booking_id' => $booking->id, 'requester_user_id' => $owner->id,
            'type' => 'reschedule', 'status' => 'pending', 'reason' => 'More time for an appointment',
            'proposed_start_at' => $booking->scheduled_start_at,
            'proposed_end_at' => $booking->scheduled_start_at->copy()->addHours(6),
        ]);
        Livewire::actingAs($owner)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSee('Cover extra time')->assertSee('$31.00');
        $this->actingAs($owner)->post(route('family.care-hours.change.payment', $change))->assertRedirect(route('family.care-hours.change.return', $change));
        $this->get(route('family.care-hours.change.return', $change))->assertRedirect(route('family.requests.show', $request));
        $this->assertSame('pending', $change->fresh()->status);
        $this->assertSame(120, $funding->forBooking($booking)->reserved_minutes);
        $this->assertSame(120, $funding->plannedMinutes($booking->fresh()));
        $this->assertTrue($funding->readiness($booking)['ready']);
        Livewire::actingAs($owner)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSee('Extra time is covered')->assertDontSee('Cover extra time');
    }

    private function booking(User $owner, CareHourAccount $account): array
    {
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $request = CareRequest::create(['family_user_id' => $owner->id, 'family_account_id' => $account->family_account_id,
            'title' => 'Care for Margaret', 'status' => 'filled', 'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'request_type' => 'one_time', 'requested_start_at' => now()->addDay(), 'requested_end_at' => now()->addDay()->addHours(2)]);
        $recipient = CareRecipient::create(['care_request_id' => $request->id, 'full_name' => 'Margaret', 'relationship_to_family' => 'Mother']);
        app(CareHourIdentityService::class)->linkRecipient($account, $recipient, $owner);
        $application = CareRequestApplication::create(['care_request_id' => $request->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'hired', 'proposed_rate' => 27]);
        $booking = CareBooking::create(['care_request_id' => $request->id, 'care_request_application_id' => $application->id,
            'family_user_id' => $owner->id, 'caregiver_user_id' => $caregiver->id, 'status' => 'scheduled',
            'scheduled_start_at' => $request->requested_start_at, 'scheduled_end_at' => $request->requested_end_at, 'expected_minutes' => 120]);

        return [$booking, $request];
    }

    private function account(string $name = 'Margaret'): array
    {
        $owner = User::factory()->create(['role' => 'family', 'email_verified_at' => now()]);
        $family = app(FamilyAccountContext::class)->account($owner);
        $account = CareHourAccount::create(['family_account_id' => $family->id, 'display_name' => $name, 'active' => true, 'currency' => 'usd']);

        return [$owner, $account];
    }

    private function buy(CareHourAccount $account, User $owner, int $hours): CareHourPurchase
    {
        $service = app(CareHourPurchaseService::class);
        $quote = $service->quote($account, $hours);

        return $service->refresh($service->start($account, $hours, $owner, $quote['quote_key'], (string) Str::uuid(), 'http://localhost/success/{PURCHASE_ID}', 'http://localhost/cancel/{PURCHASE_ID}'));
    }
}
