<?php

namespace Tests\Feature\CareHours;

use App\Exceptions\Payments\PaymentException;
use App\Models\CareHourAccount;
use App\Models\CareHourGift;
use App\Models\CareHourPurchase;
use App\Models\CareRecipientProfile;
use App\Models\User;
use App\Notifications\CareHourGiftNotification;
use App\Notifications\CareHourPurchaseNotification;
use App\Notifications\CareHourVerificationNotification;
use App\Services\CareHours\CareHourGiftService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\CareHours\CareHourPaymentGateway;
use App\Services\CareHours\CareHourPurchaseService;
use App\Services\CareHours\CareHourWebhookService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class CareHourGiftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['care_hours.enabled' => true, 'care_hours.sales_enabled' => true, 'care_hours.gifts_enabled' => true, 'care_hours.welcome_enabled' => true]);
        Notification::fake();
    }

    public function test_guest_purchase_is_unassigned_and_paid_gift_issues_no_hours_until_one_private_claim(): void
    {
        [$account, $owner] = $this->account();
        [$purchase, $token] = $this->gift(10);
        $gifts = app(CareHourGiftService::class);
        $this->assertNull($gifts->codeForBuyer($purchase, null, $token));
        $this->assertNull($purchase->care_hour_account_id);
        $this->assertNull($purchase->family_account_id);
        $this->assertNull($purchase->buyer_user_id);
        $this->assertDatabaseCount('care_hour_lots', 0);
        $purchase = app(CareHourPurchaseService::class)->refresh($purchase);
        $code = $gifts->codeForBuyer($purchase, null, $token);
        $this->assertSame('paid', $purchase->status);
        $this->assertNotNull($code);
        $this->assertDatabaseCount('care_hour_lots', 0);
        $this->assertDatabaseCount('care_hour_welcome_claims', 0);
        $stored = DB::table('care_hour_gifts')->first();
        $this->assertNotSame($code, $stored->encrypted_code);
        $this->assertSame(hash('sha256', str_replace('-', '', $code)), $stored->code_hash);
        $this->assertArrayNotHasKey('encrypted_code', $purchase->gift->toArray());
        $this->assertArrayNotHasKey('code_hash', $purchase->gift->toArray());
        $preview = $gifts->previewForClaim(strtolower($code), $account, $owner);
        $this->assertSame(660, $preview['total_minutes']);
        $claimed = $gifts->claim($code, $account, $owner, $preview['quote_key']);
        $this->assertSame($claimed->id, $gifts->claim($code, $account, $owner, $preview['quote_key'])->id);
        $this->assertSame(660, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_hour_lots', 1);
        $this->assertDatabaseCount('care_hour_welcome_claims', 1);
        $this->assertSame('awarded', DB::table('care_hour_welcome_claims')->value('status'));
        $this->assertNull($gifts->codeForBuyer($claimed, null, $token));
        $this->assertSame($purchase->stripe_charge_id, $claimed->stripe_charge_id);
        $this->assertSame($purchase->stripe_payment_intent_id, $claimed->stripe_payment_intent_id);
        $this->assertNull($owner->fresh()->stripe_customer_id);
        $this->assertNull($account->familyAccount->fresh()->stripe_customer_id);
    }

    public function test_code_cannot_claim_twice_for_another_existing_recipient_or_family(): void
    {
        [$account, $owner] = $this->account();
        [$other, $otherOwner] = $this->account();
        [$purchase, $token, $code] = $this->paidGift();
        $gifts = app(CareHourGiftService::class);
        $first = $gifts->previewForClaim($code, $account, $owner);
        $second = $gifts->previewForClaim($code, $other, $otherOwner);
        $gifts->claim($code, $account, $owner, $first['quote_key']);
        try {
            $gifts->claim($code, $other, $otherOwner, $second['quote_key']);
            $this->fail('A gift cannot be claimed twice.');
        } catch (ValidationException) {
            $this->assertSame(0, $other->balance()['available_minutes']);
            $this->assertSame($account->id, $purchase->fresh()->care_hour_account_id);
        }
    }

    public function test_five_hour_gift_preserves_welcome_and_later_gift_and_direct_purchases_share_eligibility(): void
    {
        [$account, $owner] = $this->account();
        $gifts = app(CareHourGiftService::class);
        foreach ([5 => 300, 10 => 960, 20 => 2160] as $hours => $balance) {
            [, , $code] = $this->paidGift($hours);
            $preview = $gifts->previewForClaim($code, $account, $owner);
            $gifts->claim($code, $account, $owner, $preview['quote_key']);
            $this->assertSame($balance, $account->balance()['available_minutes']);
            if ($hours === 5) {
                $this->assertDatabaseCount('care_hour_welcome_claims', 0);
            }
        }
        $this->assertSame(0, app(CareHourPurchaseService::class)->quote($account, 40)['bonus_minutes']);
    }

    public function test_pending_welcome_purchase_blocks_claim_and_changed_offer_requires_new_confirmation(): void
    {
        [$account, $owner] = $this->account();
        [, , $code] = $this->paidGift();
        $gifts = app(CareHourGiftService::class);
        $preview = $gifts->previewForClaim($code, $account, $owner);
        $purchases = app(CareHourPurchaseService::class);
        $direct = $purchases->start($account, 10, $owner, $purchases->quote($account, 10)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'));
        try {
            $gifts->claim($code, $account, $owner, $preview['quote_key']);
            $this->fail('Reserved welcome must wait for resolution.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('care_hour_lots', 0);
        }
        $purchases->refresh($direct);
        try {
            $gifts->claim($code, $account, $owner, $preview['quote_key']);
            $this->fail('A changed bonus requires new confirmation.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('care_hour_lots', 1);
        }
        $updated = $gifts->previewForClaim($code, $account, $owner);
        $this->assertSame(0, $updated['bonus_minutes']);
        $gifts->claim($code, $account, $owner, $updated['quote_key']);
        $this->assertSame(1260, $account->balance()['available_minutes']);
    }

    public function test_paid_gift_honors_recorded_bonus_and_claims_existing_profile_when_rollout_is_paused(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $family = app(FamilyAccountContext::class)->account($owner);
        $profile = CareRecipientProfile::query()->create(['family_account_id' => $family->id, 'created_by_user_id' => $owner->id, 'full_name' => 'Existing Recipient', 'status' => 'active']);
        [, , $code] = $this->paidGift();
        config(['care_hours.enabled' => false, 'care_hours.gifts_enabled' => false, 'care_hours.sales_enabled' => false, 'care_hours.enrollment_enabled' => false, 'care_hours.welcome_enabled' => false]);
        $gifts = app(CareHourGiftService::class);
        $account = $gifts->resolveClaimAccount($code, $owner, 'profile:'.$profile->id);
        $this->assertSame($profile->id, $account->care_recipient_profile_id);
        $preview = $gifts->previewForClaim($code, $account, $owner);
        $this->assertSame(60, $preview['bonus_minutes']);
        $gifts->claim($code, $account, $owner, $preview['quote_key']);
        $this->assertSame(660, $account->balance()['available_minutes']);
    }

    public function test_refund_or_dispute_prevents_unclaimed_credit_and_post_claim_hold_uses_original_lot(): void
    {
        [$account, $owner] = $this->account();
        [$purchase, , $code] = $this->paidGift();
        $gifts = app(CareHourGiftService::class);
        $preview = $gifts->previewForClaim($code, $account, $owner);
        $financials = $this->financials($purchase);
        $financials['amount_refunded_cents'] = 100;
        app(CareHourPurchaseService::class)->acceptPaid($purchase, $financials);
        try {
            $gifts->claim($code, $account, $owner, $preview['quote_key']);
            $this->fail('Partially refunded gifts cannot issue hours.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('care_hour_lots', 0);
        }
        [$second, , $secondCode] = $this->paidGift();
        $webhooks = app(CareHourWebhookService::class);
        $webhooks->handle('charge.dispute.created', ['id' => 'dp_gift', 'charge' => $second->stripe_charge_id, 'status' => 'needs_response'], false);
        try {
            $gifts->previewForClaim($secondCode, $account, $owner);
            $this->fail('Disputed gifts cannot issue hours.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('care_hour_lots', 0);
        }
        $webhooks->handle('charge.dispute.closed', ['id' => 'dp_gift', 'charge' => $second->stripe_charge_id, 'status' => 'won'], false);
        $preview = $gifts->previewForClaim($secondCode, $account, $owner);
        $claimed = $gifts->claim($secondCode, $account, $owner, $preview['quote_key']);
        $webhooks->handle('charge.dispute.created', ['id' => 'dp_gift_again', 'charge' => $claimed->stripe_charge_id, 'status' => 'needs_response'], false);
        $this->assertSame(660, $account->balance()['held_minutes']);
        $this->assertSame(0, $account->balance()['available_minutes']);
    }

    public function test_reversed_or_disputed_initial_success_never_makes_code_available(): void
    {
        foreach ([['amount_refunded_cents' => 1], ['disputed' => true, 'dispute_id' => 'dp_initial', 'dispute_status' => 'lost']] as $overrides) {
            [$purchase, $token] = $this->gift();
            $paid = app(CareHourPurchaseService::class)->acceptPaid($purchase, array_replace($this->financials($purchase), $overrides));
            $this->assertSame('held', $paid->status);
            $this->assertNull(app(CareHourGiftService::class)->codeForBuyer($paid, null, $token));
            $this->assertNull($paid->gift->code_hash);
        }
        $this->assertDatabaseCount('care_hour_lots', 0);
    }

    public function test_claim_refresh_checks_new_provider_refund_after_preview(): void
    {
        [$account, $owner] = $this->account();
        [$purchase, , $code] = $this->paidGift();
        $gifts = app(CareHourGiftService::class);
        $preview = $gifts->previewForClaim($code, $account, $owner);
        $real = app(CareHourPaymentGateway::class);
        $session = $real->retrieveCheckout($purchase->stripe_checkout_session_id);
        $financials = $this->financials($purchase);
        $financials['amount_refunded_cents'] = $purchase->amount_cents;
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('retrieveCheckout')->once()->andReturn($session);
        $gateway->shouldReceive('financials')->once()->andReturn($financials);
        $this->app->instance(CareHourPaymentGateway::class, $gateway);
        try {
            $gifts->claim($code, $account, $owner, $preview['quote_key']);
            $this->fail('Provider refund must be reconciled before claim.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('care_hour_lots', 0);
            $this->assertSame('held', $purchase->fresh()->status);
        }
    }

    public function test_paid_confirmation_cannot_mint_an_unmarked_orphan_or_use_wrong_financial_metadata(): void
    {
        [$purchase] = $this->gift();
        $financials = $this->financials($purchase);
        $wrong = $financials;
        $wrong['metadata']['care_hour_purchase_id'] = (string) Str::uuid();
        try {
            app(CareHourPurchaseService::class)->acceptPaid($purchase, $wrong);
            $this->fail('Payment ownership must match.');
        } catch (PaymentException) {
            $this->assertNull($purchase->fresh()->paid_at);
        }
        $purchase->gift()->delete();
        $this->expectException(PaymentException::class);
        app(CareHourPurchaseService::class)->acceptPaid($purchase, $financials);
    }

    public function test_unknown_actual_fee_stays_unknown_then_updates_original_claimed_lot(): void
    {
        [$account, $owner] = $this->account();
        [$purchase, $token] = $this->gift();
        $financials = $this->financials($purchase);
        $financials['processing_fee_cents'] = null;
        $financials['fee_finalized'] = false;
        $purchase = app(CareHourPurchaseService::class)->acceptPaid($purchase, $financials);
        $this->assertNull($purchase->processing_fee_cents);
        $gateway = Mockery::mock(CareHourPaymentGateway::class);
        $gateway->shouldReceive('retrieveCheckout')->andReturn(app(CareHourPaymentGateway::class)->retrieveCheckout($purchase->stripe_checkout_session_id));
        $gateway->shouldReceive('financials')->andReturn($financials);
        $this->app->instance(CareHourPaymentGateway::class, $gateway);
        $gifts = app(CareHourGiftService::class);
        $code = $gifts->codeForBuyer($purchase, null, $token);
        $preview = $gifts->previewForClaim($code, $account, $owner);
        $claimed = $gifts->claim($code, $account, $owner, $preview['quote_key']);
        $this->assertNull(DB::table('care_hour_lots')->value('processing_fee_cents'));
        $financials['fee_finalized'] = true;
        $financials['processing_fee_cents'] = 973;
        $financials['fee_components'] = [['type' => 'stripe_fee', 'amount' => 973]];
        app(CareHourPurchaseService::class)->acceptPaid($claimed, $financials);
        $this->assertSame(973, DB::table('care_hour_lots')->value('processing_fee_cents'));
        $this->assertSame(660, DB::table('care_hour_lots')->value('total_minutes'));
    }

    public function test_guest_verification_receipt_recovery_and_buyer_notification_reveal_no_recipient_or_code(): void
    {
        [$account, $owner] = $this->account();
        [$purchase, $token, $code] = $this->paidGift();
        $gifts = app(CareHourGiftService::class);
        $preview = $gifts->previewForClaim($code, $account, $owner);
        $gifts->claim($code, $account, $owner, $preview['quote_key']);
        $this->assertSame(2, app(CareHourPurchaseService::class)->dispatchNotices());
        Notification::assertSentOnDemand(CareHourGiftNotification::class, function ($notice) use ($account, $code): bool {
            $mail = json_encode($notice->toMail(new \stdClass)->toArray());
            $this->assertStringNotContainsString($account->display_name, $mail);
            $this->assertStringNotContainsString($code, $mail);

            return true;
        });
        Notification::assertSentTo($owner, CareHourPurchaseNotification::class);
        $this->assertSame(0, app(CareHourPurchaseService::class)->dispatchNotices());
        Notification::fake();
        config(['care_hours.enabled' => false, 'care_hours.gifts_enabled' => false]);
        $verification = $gifts->requestReceiptVerification($purchase, $purchase->buyer_email);
        $recoveryCode = $this->sentCode();
        try {
            $gifts->verifyReceipt($purchase, $verification->id, '000000');
            $this->fail('Bad verification should not be accepted.');
        } catch (ValidationException) {
            $this->assertSame(1, $verification->fresh()->attempts);
        }
        $recovered = $gifts->verifyReceipt($purchase, $verification->id, $recoveryCode);
        $gifts->authorizeReceipt($purchase, null, $recovered);
        $this->expectException(AuthorizationException::class);
        $gifts->authorizeReceipt($purchase, $owner);
    }

    public function test_receipt_verification_hides_whether_the_buyer_email_matched(): void
    {
        [$purchase] = $this->paidGift();
        $gifts = app(CareHourGiftService::class);
        Notification::fake();
        $verification = $gifts->requestReceiptVerification($purchase, $purchase->buyer_email);
        $validCode = $this->sentCode();
        $wrongCode = $validCode === '000000' ? '111111' : '000000';
        $errors = [];
        foreach ([(string) Str::uuid(), $verification->id] as $id) {
            try {
                $gifts->verifyReceipt($purchase, $id, $wrongCode);
                $this->fail('Unknown verification and incorrect code must both be rejected.');
            } catch (ValidationException $exception) {
                $errors[] = $exception->errors();
            }
        }
        $this->assertSame($errors[0], $errors[1]);
        $this->assertSame(['code' => ['This code is invalid or expired. Request a new code.']], $errors[0]);
        $this->assertSame(1, $verification->fresh()->attempts);
    }

    public function test_checkout_retries_and_duplicate_async_webhooks_keep_one_unassigned_gift(): void
    {
        $gifts = app(CareHourGiftService::class);
        [$purchase, $token] = $this->gift();
        $webhooks = app(CareHourWebhookService::class);
        $event = ['id' => $purchase->stripe_checkout_session_id, 'metadata' => ['care_hour_domain' => 'purchase', 'care_hour_purchase_id' => $purchase->public_id]];
        $webhooks->handle('checkout.session.async_payment_succeeded', $event, false);
        $originalCode = $gifts->codeForBuyer($purchase->fresh(), null, $token);
        $webhooks->handle('checkout.session.completed', $event, false);
        $webhooks->handle('payment_intent.succeeded', ['id' => $purchase->fresh()->stripe_payment_intent_id, 'metadata' => $event['metadata']], false);
        $this->assertSame($originalCode, $gifts->codeForBuyer($purchase->fresh(), null, $token));
        $this->assertDatabaseCount('care_hour_gifts', 1);
        $this->assertDatabaseCount('care_hour_lots', 0);
        $this->assertSame(1, DB::table('care_hour_purchase_notices')->count());
        [$failed, $failedToken] = $this->gift();
        app(CareHourPurchaseService::class)->markFailed($failed);
        $this->assertSame('failed', $failed->fresh()->status);
        $this->assertNull($gifts->codeForBuyer($failed, null, $failedToken));
        $this->assertNull($failed->gift->ready_at);
    }

    public function test_failed_issue_rolls_back_claim_assignment_welcome_and_audit_together(): void
    {
        [$account, $owner] = $this->account();
        [$purchase, , $code] = $this->paidGift();
        $preview = app(CareHourGiftService::class)->previewForClaim($code, $account, $owner);
        $ledger = Mockery::mock(CareHourLedgerService::class);
        $ledger->shouldReceive('issuePurchase')->once()->andThrow(new \RuntimeException('Simulated ledger failure'));
        $this->app->instance(CareHourLedgerService::class, $ledger);
        try {
            app(CareHourGiftService::class)->claim($code, $account, $owner, $preview['quote_key']);
            $this->fail('The claim must fail atomically.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated ledger failure', $exception->getMessage());
        }
        $this->assertNull($purchase->fresh()->care_hour_account_id);
        $this->assertNull($purchase->fresh()->gift->claimed_at);
        $this->assertDatabaseCount('care_hour_welcome_claims', 0);
        $this->assertDatabaseCount('care_hour_lots', 0);
        $this->assertSame(0, DB::table('family_account_activity_logs')->where('action', 'care_hours_gift_claimed')->count());
    }

    public function test_unauthorized_recipient_and_invalid_code_cannot_create_an_account(): void
    {
        [$account] = $this->account();
        [, $stranger] = $this->account();
        [, , $code] = $this->paidGift();
        try {
            app(CareHourGiftService::class)->previewForClaim($code, $account, $stranger);
            $this->fail('Claim cannot bypass family access.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('care_hour_lots', 0);
        }
        $before = CareHourAccount::query()->count();
        try {
            app(CareHourGiftService::class)->resolveClaimAccount('not-a-valid-gift', $stranger, 'profile:999999');
            $this->fail('A recipient must not be enrolled for an invalid gift.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('care_hour_accounts', $before);
        }
    }

    private function gift(int $hours = 10): array
    {
        $gifts = app(CareHourGiftService::class);
        $verification = $gifts->requestVerification(Str::uuid().'@example.test', 'Gift Buyer');
        $token = $gifts->verify($verification->id, $this->sentCode());
        $purchase = $gifts->start($token, $hours, $gifts->quote($hours)['quote_key'], (string) Str::uuid(), url('/care-hours/gifts/receipts/{PURCHASE_ID}'), url('/cancel'));

        return [$purchase, $token];
    }

    private function paidGift(int $hours = 10): array
    {
        [$purchase, $token] = $this->gift($hours);
        $purchase = app(CareHourPurchaseService::class)->refresh($purchase);

        return [$purchase, $token, app(CareHourGiftService::class)->codeForBuyer($purchase, null, $token)];
    }

    private function sentCode(): string
    {
        $code = '';
        Notification::assertSentOnDemand(CareHourVerificationNotification::class, function ($notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });

        return $code;
    }

    private function financials(CareHourPurchase $purchase): array
    {
        return app(CareHourPaymentGateway::class)->financials('pi_bypass_care_hours_'.$purchase->public_id);
    }

    private function account(): array
    {
        $owner = User::factory()->create(['role' => 'family']);
        $family = app(FamilyAccountContext::class)->account($owner);
        $account = CareHourAccount::query()->create(['family_account_id' => $family->id, 'display_name' => 'Private Recipient', 'currency' => 'usd', 'active' => true]);

        return [$account, $owner];
    }
}
