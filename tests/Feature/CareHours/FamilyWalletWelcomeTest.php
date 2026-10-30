<?php

namespace Tests\Feature\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareHourAccount;
use App\Models\CareHourPurchase;
use App\Models\FamilyAccountMember;
use App\Models\User;
use App\Notifications\CareHourVerificationNotification;
use App\Services\CareHours\CareHourGiftService;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourPurchaseService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\FamilyAccounts\FamilyAccountOwnershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FamilyWalletWelcomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['care_hours.enabled' => true, 'care_hours.sales_enabled' => true,
            'care_hours.gifts_enabled' => true, 'care_hours.welcome_enabled' => true]);
        Notification::fake();
    }

    public function test_five_hours_preserves_one_family_bonus_and_later_legacy_sources_cannot_get_another(): void
    {
        [$owner, $first, $second] = $this->family();
        $purchases = app(CareHourPurchaseService::class);
        $five = $purchases->refresh($this->buy($first, $owner, 5));
        $this->assertSame(0, $five->bonus_minutes);
        $this->assertSame(60, $purchases->quote($second, 10)['bonus_minutes']);
        $welcome = $purchases->refresh($this->buy($second, $owner, 10));
        $this->assertSame(60, $welcome->bonus_minutes);
        $this->assertSame(0, $purchases->quote($first, 40)['bonus_minutes']);
        $this->assertSame(0, $purchases->quote($second, 20)['bonus_minutes']);
        $this->assertSame(960, $first->balance()['available_minutes']);
        $this->assertSame($first->id, $five->fresh()->care_hour_account_id);
        $this->assertSame($second->id, $welcome->fresh()->care_hour_account_id);
        [, $unrelated] = $this->family();
        $this->assertSame(240, $purchases->quote($unrelated, 40)['bonus_minutes']);
    }

    public function test_pending_purchase_blocks_stale_bonus_offer_on_another_family_source_and_known_failure_releases_it(): void
    {
        [$owner, $first, $second] = $this->family();
        $purchases = app(CareHourPurchaseService::class);
        $stale = $purchases->quote($second, 20);
        $pending = $this->buy($first, $owner, 10);
        try {
            $purchases->start($second, 20, $owner, $stale['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'));
            $this->fail('Another legacy source must not obtain a concurrent family bonus.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('care_hour_purchases', 1);
        }
        $this->assertTrue($purchases->quote($second, 20)['welcome_pending']);
        $purchases->markFailed($pending);
        $this->assertSame(120, $purchases->quote($second, 20)['bonus_minutes']);
        $paid = $purchases->refresh($this->buy($second, $owner, 20));
        $this->assertSame(1320, $paid->total_minutes);
    }

    public function test_gift_and_direct_purchase_share_family_eligibility_without_reducing_awarded_hours(): void
    {
        [$owner, $first, $second] = $this->family();
        [$gift, $code] = $this->gift(20);
        $gifts = app(CareHourGiftService::class);
        $pending = $this->buy($second, $owner, 10);
        try {
            $gifts->previewForClaim($code, $first, $owner);
            $this->fail('Gift claim must wait for the pending family welcome offer.');
        } catch (ValidationException) {
            $this->assertNull($gift->fresh()->gift->claimed_at);
        }
        $paid = app(CareHourPurchaseService::class)->refresh($pending);
        $quote = $gifts->previewForClaim($code, $first, $owner);
        $this->assertSame(0, $quote['bonus_minutes']);
        $claimed = $gifts->claim($code, $first, $owner, $quote['quote_key']);
        $this->assertSame(1200, $claimed->total_minutes);
        $this->assertSame(60, $paid->fresh()->bonus_minutes);
        $this->assertSame(1860, $first->balance()['available_minutes']);
        $this->assertNull($owner->fresh()->stripe_customer_id);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_paid_gift_can_create_family_wallet_while_sales_are_paused_without_a_care_profile(): void
    {
        [$gift, $code] = $this->gift();
        $owner = User::factory()->create(['role' => 'family']);
        config(['care_hours.enabled' => false, 'care_hours.gifts_enabled' => false,
            'care_hours.sales_enabled' => false, 'care_hours.enrollment_enabled' => false]);
        $gifts = app(CareHourGiftService::class);
        $account = $gifts->resolveFamilyClaimAccount($code, $owner);
        $this->assertSame($account->id, app(CareHourIdentityService::class)->walletFor($owner)->id);
        $quote = $gifts->previewForClaim($code, $account, $owner);
        $gifts->claim($code, $account, $owner, $quote['quote_key']);
        $this->assertSame(660, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->assertDatabaseCount('care_request_recipients', 0);
        $this->assertSame('awarded', DB::table('care_hour_welcome_claims')->value('status'));
    }

    public function test_unsupported_legacy_currency_cannot_start_a_usd_package_purchase(): void
    {
        [$owner, $account] = $this->family();
        $account->update(['currency' => 'cad']);
        try {
            $this->buy($account, $owner, 10);
            $this->fail('A different currency must be rejected before creating a purchase.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('care_hour_purchases', 0);
            $this->assertDatabaseCount('care_hour_welcome_claims', 0);
        }
    }

    public function test_changing_family_owner_keeps_the_same_wallet_credit_and_used_welcome_offer(): void
    {
        [$owner, $source] = $this->family();
        $purchases = app(CareHourPurchaseService::class);
        $paid = $purchases->refresh($this->buy($source, $owner, 10));
        $identity = app(CareHourIdentityService::class);
        $wallet = $identity->ensureFamilyWallet($owner);
        $family = $wallet->familyAccount;
        $newOwner = User::factory()->create(['role' => 'family']);
        $membership = FamilyAccountMember::create([
            'family_account_id' => $family->id, 'user_id' => $newOwner->id,
            'access_level' => 'member', 'status' => 'active', 'joined_at' => now(),
        ]);
        app(FamilyAccountOwnershipService::class)->transfer(
            User::factory()->create(['role' => 'admin']), $family, $membership,
            'Both family members verified by support.',
        );

        $this->assertSame($wallet->id, $identity->walletFor($newOwner)->id);
        $this->assertSame(660, $identity->walletFor($newOwner)->balance()['available_minutes']);
        $this->assertSame(0, $purchases->quote($identity->walletFor($newOwner), 10)['bonus_minutes']);
        $this->assertTrue($identity->isOwner($wallet, $newOwner));
        $this->assertFalse($identity->isOwner($wallet, $owner));
        $this->assertSame($owner->id, $paid->fresh()->buyer_user_id);
        $this->assertSame($family->id, $paid->fresh()->family_account_id);
    }

    public function test_reversal_retries_after_gift_claim_changes_the_lock_target(): void
    {
        [$owner, $account] = $this->family();
        [$unclaimedPurchase, $code] = $this->gift();
        $gifts = app(CareHourGiftService::class);
        $quote = $gifts->previewForClaim($code, $account, $owner);
        $gifts->claim($code, $account, $owner, $quote['quote_key']);

        $purchases = app(CareHourPurchaseService::class);
        try {
            $purchases->holdForReversal($unclaimedPurchase, 31000);
            $this->fail('A stale gift snapshot must retry using the newly assigned family lock.');
        } catch (PaymentException) {
            $this->assertSame(660, $account->balance()['available_minutes']);
        }
        $purchases->holdForReversal($unclaimedPurchase->fresh(), 31000);
        $this->assertSame(0, $account->balance()['available_minutes']);
        $this->assertSame(660, $account->balance()['held_minutes']);
    }

    private function family(): array
    {
        $owner = User::factory()->create(['role' => 'family']);
        $family = app(FamilyAccountContext::class)->account($owner);
        $first = CareHourAccount::create(['family_account_id' => $family->id, 'display_name' => 'Colette', 'active' => true, 'currency' => 'usd']);
        $second = CareHourAccount::create(['family_account_id' => $family->id, 'display_name' => 'Another person', 'active' => true, 'currency' => 'usd']);

        return [$owner, $first, $second];
    }

    private function buy(CareHourAccount $account, User $owner, int $hours): CareHourPurchase
    {
        $service = app(CareHourPurchaseService::class);

        return $service->start($account, $hours, $owner, $service->quote($account, $hours)['quote_key'],
            (string) Str::uuid(), url('/ok'), url('/cancel'));
    }

    private function gift(int $hours = 10): array
    {
        $service = app(CareHourGiftService::class);
        $verification = $service->requestVerification(Str::uuid().'@example.test', 'Jane');
        $code = '';
        Notification::assertSentOnDemand(CareHourVerificationNotification::class, function ($notice) use (&$code) {
            $code = $notice->code;

            return true;
        });
        $token = $service->verify($verification->id, $code);
        $purchase = $service->start($token, $hours, $service->quote($hours)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'));
        $purchase = app(CareHourPurchaseService::class)->refresh($purchase);

        return [$purchase, $service->codeForBuyer($purchase, null, $token)];
    }
}
