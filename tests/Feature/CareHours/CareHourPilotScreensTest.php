<?php

namespace Tests\Feature\CareHours;

use App\Livewire\Family\CreateCareRequestWizard;
use App\Livewire\Family\NotificationsCenter;
use App\Models\CareHourAccount;
use App\Models\FamilyAccount;
use App\Models\User;
use App\Notifications\CareHourVerificationNotification;
use App\Services\CareHours\CareHourContributionService;
use App\Services\CareHours\CareHourGiftService;
use App\Services\CareHours\CareHourNavigation;
use App\Services\CareHours\CareHourPurchaseService;
use App\Services\CareRecipientProfiles\CareRecipientProfileService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Support\MarketplaceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CareHourPilotScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'care_hours.enabled' => true, 'care_hours.enrollment_enabled' => true,
            'care_hours.sales_enabled' => true, 'care_hours.contributions_enabled' => true,
            'care_hours.gifts_enabled' => true, 'care_hours.welcome_enabled' => true,
            'care_hours.pilot_only' => true, 'care_hours.pilot_emails' => ['peverelli.t@gmail.com'],
            'services.stripe.bypass' => true,
        ]);
        Notification::fake();
    }

    public function test_named_pilot_family_can_find_enroll_and_buy_hours(): void
    {
        $owner = $this->pilot();
        $profile = app(CareRecipientProfileService::class)->saveDraft($owner, null, ['full_name' => 'Linda Bennett']);
        $this->actingAs($owner)->get(route('family.billing.show'))->assertOk()->assertSee('Care hours');
        $this->get(route('family.care-hours.index'))->assertOk()->assertSee('Who are these hours for?')->assertSee('Linda Bennett');
        $this->post(route('family.care-hours.enroll'), ['recipient' => 'profile:'.$profile->id])->assertRedirect();
        $account = CareHourAccount::sole();
        $this->get(route('family.care-hours.show', $account))->assertOk()->assertSee('Buy hours');
        $this->get(route('family.care-hours.packages', $account))->assertOk()->assertSee('$310');
        $this->get(route('care-hours.gifts.index'))->assertOk();
        $this->assertDatabaseCount('care_hour_purchases', 0);
        $this->assertDatabaseCount('care_hour_lots', 0);
    }

    public function test_allowlist_is_exact_and_cannot_be_obtained_with_the_same_name_or_similar_email(): void
    {
        foreach (['someone@example.test', 'peverelli.t+pilot@gmail.com', 'peverelli.t@gmail.com.example.test'] as $email) {
            $other = User::factory()->create(['role' => 'family', 'name' => 'Thibaud Peverelli', 'email' => $email]);
            $existingFamilies = FamilyAccount::count();
            $this->assertFalse(app(CareHourNavigation::class)->visible($other));
            $this->assertDatabaseCount('family_accounts', $existingFamilies);
            $this->actingAs($other)->get(route('family.care-hours.index'))->assertNotFound();
            $this->post(route('family.care-hours.enroll'), ['recipient' => 'profile:1'])->assertForbidden();
            $this->get(route('care-hours.gifts.index'))->assertNotFound();
        }
        $this->assertDatabaseCount('care_hour_accounts', 0);
        $this->assertDatabaseCount('care_hour_purchases', 0);
    }

    public function test_email_case_is_normalized_but_a_caregiver_with_the_same_email_is_not_a_family_pilot(): void
    {
        $owner = User::factory()->create(['role' => 'family', 'email' => 'PEVERELLI.T@GMAIL.COM']);
        $this->assertTrue(app(CareHourNavigation::class)->visible($owner));
        $this->actingAs($owner)->get(route('family.care-hours.index'))->assertOk();
        $owner->update(['role' => 'caregiver']);
        $this->assertFalse(app(CareHourNavigation::class)->visible($owner));
        $this->actingAs($owner)->get(route('family.care-hours.index'))->assertForbidden();
        $this->get(route('care-hours.gifts.index'))->assertNotFound();
    }

    public function test_other_family_keeps_card_billing_and_request_screens_without_prepaid_prompts(): void
    {
        $other = User::factory()->create(['role' => 'family']);
        $this->actingAs($other)->get(route('family.billing.show'))->assertOk()
            ->assertSee('Billing &amp; Payments', false)->assertSee('Add a card once.')
            ->assertSee('How charging works')->assertDontSee('Care hours');
        Livewire::actingAs($other)->test(CreateCareRequestWizard::class)
            ->assertSet('careHourAccountId', null)->assertDontSee('Use care hours first');
        $this->assertDatabaseCount('care_hour_accounts', 0);
        $this->assertDatabaseCount('care_hour_purchases', 0);
        $this->assertDatabaseCount('care_hour_booking_fundings', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_partial_additive_schema_does_not_break_ordinary_family_navigation(): void
    {
        $other = User::factory()->create(['role' => 'family']);
        $this->account($other);
        Schema::drop('care_hour_purchases');

        $this->assertFalse(app(CareHourNavigation::class)->visible($other));
        $this->actingAs($other)->get(route('family.billing.show'))->assertOk()
            ->assertSee('Billing &amp; Payments', false)->assertSee('Add a card once.')->assertDontSee('Care hours');
        $this->get(route('family.care-hours.index'))->assertNotFound();
        $this->assertDatabaseCount('care_hour_lots', 0);
    }

    public function test_prepaid_notification_preferences_only_appear_for_pilot_or_existing_entitlements(): void
    {
        $other = User::factory()->create(['role' => 'family']);
        $hidden = Livewire::actingAs($other)->test(NotificationsCenter::class);
        $this->assertNotContains(MarketplaceEvent::CARE_HOURS_PAYMENT_ATTENTION, $hidden->get('eventKeys'));
        $this->assertNotContains(MarketplaceEvent::CARE_HOURS_VISIT_SETTLED, $hidden->get('eventKeys'));
        $visible = Livewire::actingAs($this->pilot())->test(NotificationsCenter::class);
        $this->assertContains(MarketplaceEvent::CARE_HOURS_PAYMENT_ATTENTION, $visible->get('eventKeys'));
        $this->assertContains(MarketplaceEvent::CARE_HOURS_VISIT_SETTLED, $visible->get('eventKeys'));
    }

    public function test_guest_or_other_family_cannot_buy_gifts_by_entering_the_pilot_email(): void
    {
        $this->get(route('care-hours.gifts.index'))->assertNotFound();
        $this->post(route('care-hours.gifts.verify'), ['name' => 'Thibaud Peverelli', 'email' => 'peverelli.t@gmail.com'])
            ->assertSessionHasErrors('purchase');
        $this->actingAs(User::factory()->create(['role' => 'family']))
            ->post(route('care-hours.gifts.verify'), ['name' => 'Thibaud Peverelli', 'email' => 'peverelli.t@gmail.com'])
            ->assertSessionHasErrors('purchase');
        $this->assertDatabaseCount('care_hour_gift_verifications', 0);
        $this->assertDatabaseCount('care_hour_purchases', 0);
        Notification::assertNothingSent();
    }

    public function test_pilot_must_use_own_verified_buyer_email_and_private_links_do_not_open_guest_sales(): void
    {
        $owner = $this->pilot();
        $account = $this->account($owner);
        $this->actingAs($owner)->post(route('care-hours.gifts.verify'), ['name' => 'Outside donor', 'email' => 'outside@example.test'])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('care_hour_gift_verifications', 0);
        $link = app(CareHourContributionService::class)->createLink($account, $owner);
        $this->get(route('care-hours.contribute', $link['token']))->assertOk();
        $this->post(route('care-hours.contribute.verify', $link['token']), ['name' => 'Outside donor', 'email' => 'outside@example.test'])
            ->assertSessionHasErrors('email');
        auth()->logout();
        $this->get(route('care-hours.contribute', $link['token']))->assertNotFound();
        $this->post(route('care-hours.contribute.verify', $link['token']), ['name' => 'Thibaud', 'email' => $owner->email])
            ->assertSessionHasErrors('care_hours');
        $this->assertDatabaseCount('care_hour_contribution_verifications', 0);
        $this->assertDatabaseCount('care_hour_purchases', 0);
        Notification::assertNothingSent();
    }

    public function test_restricting_the_pilot_does_not_hide_paid_balances_or_buyer_receipts(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $account = $this->account($owner);
        config(['care_hours.pilot_only' => false]);
        $service = app(CareHourPurchaseService::class);
        $purchase = $service->refresh($service->start($account, 5, $owner, $service->quote($account, 5)['quote_key'],
            (string) Str::uuid(), url('/ok/{PURCHASE_ID}'), url('/cancel/{PURCHASE_ID}')));
        config(['care_hours.pilot_only' => true]);
        $this->assertTrue(app(CareHourNavigation::class)->visible($owner));
        $this->actingAs($owner)->get(route('family.care-hours.show', $account))->assertOk()->assertSee('5 h')
            ->assertDontSee('Buy hours')->assertDontSee('Let someone contribute')->assertDontSee('Buy a gift of care');
        $this->get(route('family.care-hours.purchase', $purchase->public_id))->assertOk()->assertSee('5 h');
        $this->get(route('family.care-hours.packages', $account))->assertForbidden();
        $this->assertSame(300, $account->balance()['available_minutes']);
    }

    public function test_paid_gift_receipt_and_claim_survive_pilot_restrictions(): void
    {
        config(['care_hours.pilot_only' => false]);
        $gifts = app(CareHourGiftService::class);
        $verification = $gifts->requestVerification('jane@example.test', 'Jane Bennett');
        $code = '';
        Notification::assertSentOnDemand(CareHourVerificationNotification::class, function ($notice) use (&$code) {
            $code = $notice->code;

            return true;
        });
        $token = $gifts->verify($verification->id, $code);
        $purchase = $gifts->start($token, 10, $gifts->quote(10)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'));
        $purchase = app(CareHourPurchaseService::class)->refresh($purchase);
        $giftCode = $gifts->codeForBuyer($purchase, null, $token);
        $owner = User::factory()->create(['role' => 'family']);
        $account = $this->account($owner);
        config(['care_hours.pilot_only' => true, 'care_hours.enabled' => false, 'care_hours.gifts_enabled' => false]);
        $this->withSession(['care_hours.gift_buyer' => $token])->get(route('care-hours.gifts.receipt', $purchase->public_id))
            ->assertOk()->assertSee('Your gift is ready');
        $this->actingAs($owner)->post(route('family.care-hours.claim.preview'), ['code' => $giftCode, 'recipient' => 'account:'.$account->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('family.care-hours.claim.confirm'), ['confirm' => 1, 'nonce' => session('care_hours.gift_claim.nonce')])
            ->assertRedirect(route('family.care-hours.show', $account))->assertSessionHasNoErrors();
        $this->assertSame(660, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_hour_lots', 1);
    }

    public function test_global_pause_still_blocks_new_pilot_exposure_and_enrollment(): void
    {
        $owner = $this->pilot();
        config(['care_hours.enabled' => false]);
        $this->assertFalse(app(CareHourNavigation::class)->visible($owner));
        $this->actingAs($owner)->get(route('family.billing.show'))->assertOk()->assertDontSee('Care hours');
        $this->get(route('family.care-hours.index'))->assertNotFound();
        $this->post(route('family.care-hours.enroll'), ['recipient' => 'profile:1'])->assertForbidden();
        $this->get(route('care-hours.gifts.index'))->assertNotFound();
        $this->assertDatabaseCount('care_hour_accounts', 0);
        $this->assertDatabaseCount('care_hour_purchases', 0);
    }

    private function pilot(): User
    {
        return User::factory()->create(['role' => 'family', 'name' => 'Thibaud Peverelli', 'email' => 'peverelli.t@gmail.com']);
    }

    private function account(User $owner): CareHourAccount
    {
        return CareHourAccount::create(['family_account_id' => app(FamilyAccountContext::class)->account($owner)->id,
            'display_name' => 'Linda Bennett', 'active' => true, 'currency' => 'usd']);
    }
}
