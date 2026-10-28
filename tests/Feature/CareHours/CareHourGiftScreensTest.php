<?php

namespace Tests\Feature\CareHours;

use App\Models\CareHourAccount;
use App\Models\CareHourPurchase;
use App\Models\CareRecipientProfile;
use App\Models\User;
use App\Notifications\CareHourVerificationNotification;
use App\Services\CareHours\CareHourGiftService;
use App\Services\CareHours\CareHourPurchaseService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class CareHourGiftScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['care_hours.enabled' => true, 'care_hours.sales_enabled' => true, 'care_hours.gifts_enabled' => true, 'care_hours.welcome_enabled' => true]);
        Notification::fake();
    }

    public function test_guest_buys_once_with_verified_email_and_receives_a_private_code_without_a_recipient_account(): void
    {
        $this->get(route('care-hours.gifts.index'))->assertOk()->assertSee('Give hours. Let them choose when.')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $quote = app(CareHourGiftService::class)->quote(10);
        $payload = ['hours' => 10, 'quotes' => [10 => $quote['quote_key']], 'request_key' => (string) Str::uuid(), 'terms' => 1];
        $this->post(route('care-hours.gifts.checkout'), $payload)->assertForbidden();
        $this->post(route('care-hours.gifts.verify'), ['name' => 'Daniel Bennett', 'email' => 'daniel@example.test'])->assertRedirect();
        $this->post(route('care-hours.gifts.confirm'), ['code' => $this->otp()])->assertRedirect();
        $this->get(route('care-hours.gifts.index'))->assertOk()->assertSee('$310')->assertSee('welcome');
        $this->post(route('care-hours.gifts.checkout'), array_diff_key($payload, ['terms' => 1]))->assertSessionHasErrors('terms');
        $this->assertDatabaseCount('care_hour_purchases', 0);
        $this->post(route('care-hours.gifts.checkout'), $payload)->assertRedirect();
        $purchase = CareHourPurchase::sole();
        $receipt = route('care-hours.gifts.receipt', $purchase->public_id);
        $this->get($receipt)->assertOk()->assertSee('Your gift is ready')->assertSee('paid-gift-code')->assertSee('$310.00');
        $this->post(route('care-hours.gifts.checkout'), $payload)->assertRedirect();
        $this->assertDatabaseCount('care_hour_purchases', 1);
        $this->assertDatabaseCount('care_hour_lots', 0);
        $this->assertDatabaseCount('care_hour_accounts', 0);
        $this->assertDatabaseCount('users', 0);
        $this->flushSession();
        $this->get($receipt)->assertOk()->assertDontSee('paid-gift-code')->assertDontSee('$310')->assertDontSee('daniel@example.test');
    }

    public function test_claim_previews_exact_recipient_and_bonus_then_adds_once_without_exposing_code_or_donor(): void
    {
        [$owner, $account] = $this->account();
        [$purchase, $code] = $this->gift();
        $this->actingAs($owner)->get(route('family.care-hours.claim'))->assertOk()->assertSee('Robert Bennett');
        $this->post(route('family.care-hours.claim.preview'), ['code' => $code, 'recipient' => 'account:'.$account->id])->assertRedirect();
        $this->assertSame(0, $account->balance()['available_minutes']);
        $draft = session('care_hours.gift_claim');
        $this->assertNotSame($code, $draft['encrypted_code']);
        $this->get(route('family.care-hours.claim'))->assertOk()->assertSee('11 h')->assertSee('Free welcome hours')
            ->assertDontSee($code)->assertDontSee($purchase->buyer_email);
        $this->post(route('family.care-hours.claim.confirm'), ['nonce' => $draft['nonce']])->assertSessionHasErrors('confirm');
        $this->post(route('family.care-hours.claim.confirm'), ['nonce' => $draft['nonce'], 'confirm' => 1])
            ->assertRedirect(route('family.care-hours.show', $account))->assertSessionMissing('care_hours.gift_claim');
        $this->assertSame(660, $account->balance()['available_minutes']);
        $this->post(route('family.care-hours.claim.confirm'), ['nonce' => $draft['nonce'], 'confirm' => 1])->assertStatus(422);
        $this->assertDatabaseCount('care_hour_lots', 1);
        $this->get(route('family.care-hours.show', $account))->assertOk()->assertDontSee($purchase->buyer_email);
        $this->assertNull($owner->fresh()->stripe_customer_id);
        $this->assertNull($account->familyAccount->fresh()->stripe_customer_id);
    }

    public function test_invalid_code_is_not_flashed_and_recipient_selection_is_family_scoped(): void
    {
        [$owner] = $this->account();
        [, $other] = $this->account('Private other recipient');
        [, $code] = $this->gift();
        $this->actingAs($owner)->from(route('family.care-hours.claim'))
            ->post(route('family.care-hours.claim.preview'), ['code' => $code, 'recipient' => 'invalid'])
            ->assertSessionHasErrors('recipient')->assertSessionMissing('_old_input.code');
        $this->get(route('family.care-hours.claim'))->assertOk()->assertDontSee('Private other recipient')->assertDontSee($code);
        $this->post(route('family.care-hours.claim.preview'), ['code' => $code, 'recipient' => 'account:'.$other->id])->assertForbidden();
        $this->assertDatabaseCount('care_hour_lots', 0);
    }

    public function test_existing_profile_can_claim_paid_gift_while_new_sales_and_enrollment_are_paused(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $family = app(FamilyAccountContext::class)->account($owner);
        $profile = CareRecipientProfile::create(['family_account_id' => $family->id, 'created_by_user_id' => $owner->id, 'full_name' => 'Robert Bennett', 'status' => 'active']);
        [, $code] = $this->gift();
        config(['care_hours.enabled' => false, 'care_hours.sales_enabled' => false, 'care_hours.gifts_enabled' => false, 'care_hours.enrollment_enabled' => false]);
        $this->get(route('care-hours.gifts.index'))->assertNotFound();
        $this->actingAs($owner)->post(route('family.care-hours.claim.preview'), ['code' => $code, 'recipient' => 'profile:'.$profile->id])->assertRedirect();
        $this->get(route('family.care-hours.claim'))->assertOk()->assertSee('11 h');
        $this->post(route('family.care-hours.claim.confirm'), ['nonce' => session('care_hours.gift_claim.nonce'), 'confirm' => 1])->assertRedirect();
        $this->assertSame(660, CareHourAccount::sole()->balance()['available_minutes']);
    }

    public function test_receipt_recovery_is_private_after_claim_and_does_not_authorize_new_purchase(): void
    {
        [$owner, $account] = $this->account();
        [$purchase, $code] = $this->gift();
        $gifts = app(CareHourGiftService::class);
        $gifts->claim($code, $account, $owner, $gifts->previewForClaim($code, $account, $owner)['quote_key']);
        Notification::fake();
        $this->post(route('care-hours.gifts.receipt.verify', $purchase->public_id), ['email' => $purchase->buyer_email])->assertRedirect();
        $this->post(route('care-hours.gifts.receipt.confirm', $purchase->public_id), ['code' => $this->otp()])->assertRedirect();
        $this->get(route('care-hours.gifts.receipt', $purchase->public_id))->assertOk()->assertSee('Your gift has been claimed')
            ->assertDontSee('Robert Bennett')->assertDontSee($code)->assertDontSee('paid-gift-code');
        $this->get(route('care-hours.gifts.index'))->assertOk()->assertSee('Send verification code')->assertDontSee('Continue to payment');
    }

    public function test_admin_can_follow_unclaimed_and_claimed_gifts_and_audited_payment_checks_without_bearer_codes(): void
    {
        [$purchase, $code] = $this->gift();
        [$owner, $account] = $this->account();
        $this->actingAs($owner)->get(route('admin.care-hours.gifts.index'))->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.care-hours.gifts.index', ['state' => 'unclaimed']))->assertOk()
            ->assertSee($purchase->public_id)->assertSee('Awaiting claim')->assertDontSee($code);
        $this->post(route('admin.care-hours.gifts.recheck', $purchase->public_id), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('care_hour_gift_admin_checks', 0);
        $this->post(route('admin.care-hours.gifts.recheck', $purchase->public_id), ['reason' => 'Buyer requested a payment review'])->assertRedirect();
        $this->assertDatabaseHas('care_hour_gift_admin_checks', ['care_hour_purchase_id' => $purchase->id, 'actor_user_id' => $admin->id]);
        $gifts = app(CareHourGiftService::class);
        $gifts->claim($code, $account, $owner, $gifts->previewForClaim($code, $account, $owner)['quote_key']);
        $this->get(route('admin.care-hours.gifts.index', ['state' => 'claimed']))->assertOk()->assertSee('Robert Bennett')->assertSee('Claimed')->assertDontSee($code);
        $this->get(route('admin.care-hours.gifts.index'))->assertSee('Buyer requested a payment review');
    }

    public function test_admin_must_verify_the_actual_buyer_before_opening_an_unclaimed_gift_receipt(): void
    {
        [$purchase, $code] = $this->gift();
        $admin = User::factory()->create(['role' => 'admin', 'email' => $purchase->buyer_email]);
        $receipt = route('care-hours.gifts.receipt', $purchase->public_id);

        $this->actingAs($admin)->get($receipt)->assertOk()->assertSee('Open your gift receipt')
            ->assertDontSee($code)->assertDontSee('paid-gift-code')->assertDontSee('$310.00');
        $this->get(route('admin.care-hours.gifts.index'))->assertOk()->assertSee('Awaiting claim')->assertDontSee($code);

        Notification::fake();
        $this->post(route('care-hours.gifts.receipt.verify', $purchase->public_id), ['email' => $purchase->buyer_email])->assertRedirect();
        $this->post(route('care-hours.gifts.receipt.confirm', $purchase->public_id), ['code' => $this->otp()])->assertRedirect($receipt);
        $this->get($receipt)->assertOk()->assertSee('Your gift is ready')->assertSee($code)->assertSee('paid-gift-code');
        $this->assertNull($purchase->gift->fresh()->claimed_at);
        $this->assertDatabaseCount('care_hour_lots', 0);
    }

    public function test_family_claim_requires_login_and_accepts_a_code_when_no_recipient_exists(): void
    {
        $this->get(route('family.care-hours.claim'))->assertRedirect(route('login'));
        $owner = User::factory()->create(['role' => 'family']);
        $this->actingAs($owner)->get(route('family.care-hours.claim'))->assertOk()->assertSee('Gift code')
            ->assertSee('No card or care request needed')->assertSee('Your gift does not expire')->assertDontSee('Set up care');
    }

    public function test_gift_receipts_use_their_private_receipt_flow_even_from_other_purchase_urls(): void
    {
        [$purchase] = $this->gift();
        $this->get(route('care-hours.receipt', $purchase->public_id))->assertRedirect(route('care-hours.gifts.receipt', $purchase->public_id));
        $this->post(route('care-hours.receipt.verify', $purchase->public_id), ['email' => $purchase->buyer_email])->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('family.care-hours.purchase', $purchase->public_id))->assertRedirect(route('care-hours.gifts.receipt', $purchase->public_id));
    }

    public function test_repeated_claim_attempts_are_limited_without_blocking_reading_the_claim_screen(): void
    {
        [$owner, $account] = $this->account();
        $this->actingAs($owner);
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $this->post(route('family.care-hours.claim.preview'), ['code' => 'invalid', 'recipient' => 'account:'.$account->id])->assertSessionHasErrors('gift_code');
        }
        $this->post(route('family.care-hours.claim.preview'), ['code' => 'invalid', 'recipient' => 'account:'.$account->id])->assertStatus(429);
        $this->get(route('family.care-hours.claim'))->assertOk();
        $this->assertDatabaseCount('care_hour_lots', 0);
    }

    public function test_pending_gift_can_resume_the_original_checkout_without_issuing_a_code_or_new_purchase(): void
    {
        $gifts = app(CareHourGiftService::class);
        $verification = $gifts->requestVerification('daniel@example.test', 'Daniel Bennett');
        $token = $gifts->verify($verification->id, $this->otp());
        $purchase = $gifts->start($token, 10, $gifts->quote(10)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'));
        $reader = Mockery::mock(CareHourPurchaseService::class);
        $reader->shouldReceive('refresh')->once()->andReturn($purchase);
        $this->app->instance(CareHourPurchaseService::class, $reader);
        $this->withSession(['care_hours.gift_buyer' => $token])->get(route('care-hours.gifts.receipt', $purchase->public_id))
            ->assertOk()->assertSee('Continue payment')->assertSee($purchase->checkout_url)->assertDontSee('paid-gift-code');
        $this->assertDatabaseCount('care_hour_purchases', 1);
        $this->assertDatabaseCount('care_hour_lots', 0);
    }

    private function account(string $name = 'Robert Bennett'): array
    {
        $owner = User::factory()->create(['role' => 'family']);
        $family = app(FamilyAccountContext::class)->account($owner);

        return [$owner, CareHourAccount::create(['family_account_id' => $family->id, 'display_name' => $name, 'active' => true, 'currency' => 'usd'])];
    }

    private function gift(): array
    {
        $gifts = app(CareHourGiftService::class);
        $verification = $gifts->requestVerification('daniel@example.test', 'Daniel Bennett');
        $token = $gifts->verify($verification->id, $this->otp());
        $purchase = $gifts->start($token, 10, $gifts->quote(10)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'));
        $purchase = app(CareHourPurchaseService::class)->refresh($purchase);

        return [$purchase, $gifts->codeForBuyer($purchase, null, $token)];
    }

    private function otp(): string
    {
        $code = '';
        Notification::assertSentOnDemand(CareHourVerificationNotification::class, function ($notice) use (&$code) {
            $code = $notice->code;

            return true;
        });

        return $code;
    }
}
