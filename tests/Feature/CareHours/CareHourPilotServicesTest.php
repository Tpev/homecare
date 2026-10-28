<?php

namespace Tests\Feature\CareHours;

use App\Models\CareHourAccount;
use App\Models\CareHourContributionLink;
use App\Models\CareHourPurchase;
use App\Models\CareRecipient;
use App\Models\CareRecipientProfile;
use App\Models\User;
use App\Notifications\CareHourVerificationNotification;
use App\Services\CareHours\CareHourContributionService;
use App\Services\CareHours\CareHourGiftService;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourPurchaseService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CareHourPilotServicesTest extends TestCase
{
    use RefreshDatabase;

    private const PILOT_EMAIL = 'peverelli.t@gmail.com';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'care_hours.enabled' => true, 'care_hours.sales_enabled' => true,
            'care_hours.enrollment_enabled' => true, 'care_hours.contributions_enabled' => true,
            'care_hours.gifts_enabled' => true, 'care_hours.pilot_only' => true,
            'care_hours.pilot_emails' => [self::PILOT_EMAIL], 'services.stripe.bypass' => true,
        ]);
        Notification::fake();
    }

    public function test_direct_purchase_requires_the_explicit_pilot_buyer_before_writes(): void
    {
        [$account, $owner] = $this->account();
        $this->assertRejected(fn () => $this->startDirect($account, $owner));
        $this->assertDatabaseCount('care_hour_purchases', 0);
        $this->assertDatabaseCount('care_hour_welcome_claims', 0);
        Notification::assertNothingSent();
    }

    public function test_pilot_direct_purchase_does_not_depend_on_the_request_auth_guard(): void
    {
        [$account, $owner] = $this->account(self::PILOT_EMAIL);
        $purchase = $this->startDirect($account, $owner);
        $this->assertSame('pending', $purchase->status);
        $this->assertSame($owner->id, $purchase->buyer_user_id);
        $this->assertNotNull($purchase->checkout_requested_at);
    }

    public function test_direct_purchase_replay_and_paid_fulfillment_survive_pilot_restriction(): void
    {
        config(['care_hours.pilot_only' => false]);
        [$account, $owner] = $this->account();
        $key = (string) Str::uuid();
        $quote = app(CareHourPurchaseService::class)->quote($account, 10);
        $purchase = $this->startDirect($account, $owner, $key, $quote['quote_key']);
        config(['care_hours.pilot_only' => true]);
        $again = $this->startDirect($account, $owner, $key, $quote['quote_key']);
        $this->assertSame($purchase->id, $again->id);
        $paid = app(CareHourPurchaseService::class)->refresh($again);
        $this->assertSame('issued', $paid->status);
        $this->assertSame(660, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_hour_purchases', 1);
        app(CareHourPurchaseService::class)->authorizeReceipt($paid, $owner);
    }

    public function test_new_enrollment_rejects_nonpilot_before_family_or_recipient_writes(): void
    {
        $actor = User::factory()->create(['role' => 'family']);
        $families = \App\Models\FamilyAccount::count();
        $service = app(CareHourIdentityService::class);
        $this->assertRejected(fn () => $service->enroll(new CareRecipient, $actor));
        $this->assertRejected(fn () => $service->enrollProfile(new CareRecipientProfile, $actor));
        $this->assertDatabaseCount('family_accounts', $families);
        $this->assertDatabaseCount('care_hour_accounts', 0);
        $this->assertDatabaseCount('care_hour_recipient_links', 0);
    }

    public function test_pilot_can_enroll_its_existing_profile(): void
    {
        $owner = User::factory()->create(['role' => 'family', 'email' => self::PILOT_EMAIL]);
        $family = app(FamilyAccountContext::class)->account($owner);
        $profile = CareRecipientProfile::create([
            'family_account_id' => $family->id, 'created_by_user_id' => $owner->id,
            'full_name' => 'Pilot recipient', 'status' => 'active',
        ]);
        $account = app(CareHourIdentityService::class)->enrollProfile($profile, $owner);
        $this->assertSame($profile->id, $account->care_recipient_profile_id);
    }

    public function test_global_pause_still_blocks_the_pilot(): void
    {
        [$account, $owner] = $this->account(self::PILOT_EMAIL);
        $this->actingAs($owner);
        config(['care_hours.enabled' => false]);
        $this->assertRejected(fn () => $this->startDirect($account, $owner));
        $this->assertRejected(fn () => app(CareHourGiftService::class)->requestVerification($owner->email, $owner->name));
        $this->assertRejected(fn () => app(CareHourContributionService::class)->createLink($account, $owner));
        $this->assertDatabaseCount('care_hour_purchases', 0);
        Notification::assertNothingSent();
    }

    public function test_guest_cannot_start_gift_verification_even_using_the_pilot_email(): void
    {
        $this->assertRejected(fn () => app(CareHourGiftService::class)->requestVerification(self::PILOT_EMAIL, 'Thibaud'));
        $this->assertDatabaseCount('care_hour_gift_verifications', 0);
        Notification::assertNothingSent();
    }

    public function test_pilot_cannot_send_new_gift_verification_to_another_email(): void
    {
        [, $owner] = $this->account(self::PILOT_EMAIL);
        $this->actingAs($owner);
        $this->assertRejected(fn () => app(CareHourGiftService::class)->requestVerification('outside@example.test', 'Outside'));
        $this->assertDatabaseCount('care_hour_gift_verifications', 0);
        Notification::assertNothingSent();
    }

    public function test_pilot_can_verify_and_buy_a_gift_with_its_own_email(): void
    {
        [, $owner] = $this->account(self::PILOT_EMAIL);
        $this->actingAs($owner);
        [$purchase] = $this->gift(' PEVERELLI.T@GMAIL.COM ');
        $this->assertSame(self::PILOT_EMAIL, $purchase->buyer_email);
        $this->assertNotNull($purchase->checkout_requested_at);
        $this->assertDatabaseCount('care_hour_gifts', 1);
    }

    public function test_preverified_outside_gift_token_cannot_create_a_new_purchase_during_pilot(): void
    {
        config(['care_hours.pilot_only' => false]);
        $gifts = app(CareHourGiftService::class);
        $verification = $gifts->requestVerification('outside@example.test', 'Outside');
        $token = $gifts->verify($verification->id, $this->sentCode());
        [, $pilot] = $this->account(self::PILOT_EMAIL);
        $this->actingAs($pilot);
        config(['care_hours.pilot_only' => true]);
        $this->assertRejected(fn () => $gifts->start($token, 10, $gifts->quote(10)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel')));
        $this->assertDatabaseCount('care_hour_purchases', 0);
        $this->assertDatabaseCount('care_hour_gifts', 0);
    }

    public function test_existing_gift_replay_receipt_and_nonpilot_self_claim_remain_available(): void
    {
        config(['care_hours.pilot_only' => false]);
        [$purchase, $token, $key, $quoteKey] = $this->gift('outside@example.test');
        config(['care_hours.pilot_only' => true]);
        $gifts = app(CareHourGiftService::class);
        $again = $gifts->start($token, 10, $quoteKey, $key, url('/ok'), url('/cancel'));
        $this->assertSame($purchase->id, $again->id);
        $paid = app(CareHourPurchaseService::class)->refresh($again);
        $code = $gifts->codeForBuyer($paid, null, $token);
        $this->assertNotNull($code);
        Notification::fake();
        $receipt = $gifts->requestReceiptVerification($paid, 'outside@example.test');
        $receiptToken = $gifts->verifyReceipt($paid, $receipt->id, $this->sentCode());
        $gifts->authorizeReceipt($paid, null, $receiptToken);
        $recipient = User::factory()->create(['role' => 'family']);
        $account = $gifts->resolveSelfClaimAccount($code, $recipient);
        $quote = $gifts->previewForClaim($code, $account, $recipient);
        $claimed = $gifts->claim($code, $account, $recipient, $quote['quote_key']);
        $this->assertSame('issued', $claimed->status);
        $this->assertSame(660, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_hour_purchases', 1);
    }

    public function test_only_pilot_owner_can_create_new_contribution_links(): void
    {
        [$outsideAccount, $outside] = $this->account();
        $service = app(CareHourContributionService::class);
        $this->assertRejected(fn () => $service->createLink($outsideAccount, $outside));
        $this->assertDatabaseCount('care_hour_contribution_links', 0);
        [$account, $owner] = $this->account(self::PILOT_EMAIL);
        $created = $service->createLink($account, $owner);
        $this->assertSame($account->id, $created['link']->care_hour_account_id);
    }

    public function test_private_link_does_not_grant_guests_pilot_access_or_mail_delivery(): void
    {
        [$account, $owner] = $this->account(self::PILOT_EMAIL);
        $service = app(CareHourContributionService::class);
        $created = $service->createLink($account, $owner);
        $this->assertRejected(fn () => $service->resolve($created['token']));
        $this->assertRejected(fn () => $service->requestVerification($created['link'], self::PILOT_EMAIL, 'Thibaud'));
        $this->assertDatabaseCount('care_hour_contribution_verifications', 0);
        Notification::assertNothingSent();
    }

    public function test_pilot_contributor_must_use_its_own_email(): void
    {
        [$account, $owner] = $this->account(self::PILOT_EMAIL);
        $this->actingAs($owner);
        $service = app(CareHourContributionService::class);
        $created = $service->createLink($account, $owner);
        $this->assertRejected(fn () => $service->requestVerification($created['link'], 'outside@example.test', 'Outside'));
        $this->assertDatabaseCount('care_hour_contribution_verifications', 0);
        Notification::assertNothingSent();
        $verification = $service->requestVerification($created['link'], self::PILOT_EMAIL, $owner->name);
        $token = $service->verify($created['link'], $verification->id, $this->sentCode());
        $purchase = $this->startContribution($account, $created['link'], $token);
        $this->assertSame(self::PILOT_EMAIL, $purchase->buyer_email);
    }

    public function test_preverified_outside_contribution_cannot_start_a_new_purchase_during_pilot(): void
    {
        config(['care_hours.pilot_only' => false]);
        [$account, $link, $token] = $this->outsideContribution();
        [, $pilot] = $this->account(self::PILOT_EMAIL);
        $this->actingAs($pilot);
        config(['care_hours.pilot_only' => true]);
        $this->assertRejected(fn () => $this->startContribution($account, $link, $token));
        $this->assertDatabaseCount('care_hour_purchases', 0);
    }

    public function test_existing_contribution_replay_fulfillment_and_receipt_recovery_survive_pilot(): void
    {
        config(['care_hours.pilot_only' => false]);
        [$account, $link, $token] = $this->outsideContribution();
        $key = (string) Str::uuid();
        $quoteKey = app(CareHourPurchaseService::class)->quote($account, 10)['quote_key'];
        $purchase = $this->startContribution($account, $link, $token, $key, $quoteKey);
        config(['care_hours.pilot_only' => true]);
        $again = $this->startContribution($account, $link, $token, $key, $quoteKey);
        $this->assertSame($purchase->id, $again->id);
        $paid = app(CareHourPurchaseService::class)->refresh($again);
        $this->assertSame('issued', $paid->status);
        $this->assertSame(660, $account->balance()['available_minutes']);
        Notification::fake();
        $service = app(CareHourContributionService::class);
        $verification = $service->requestReceiptVerification($paid, 'outside@example.test');
        $receiptToken = $service->verifyReceipt($paid, $verification->id, $this->sentCode());
        app(CareHourPurchaseService::class)->authorizeReceipt($paid, null, $receiptToken);
        $this->assertDatabaseCount('care_hour_purchases', 1);
    }

    private function assertRejected(callable $action): void
    {
        try {
            $action();
            $this->fail('A restricted pilot operation should not proceed.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    private function account(?string $email = null): array
    {
        $owner = User::factory()->create(array_filter(['role' => 'family', 'email' => $email]));
        $family = app(FamilyAccountContext::class)->account($owner);

        return [CareHourAccount::create(['family_account_id' => $family->id, 'display_name' => 'Recipient', 'active' => true, 'currency' => 'usd']), $owner];
    }

    private function startDirect(CareHourAccount $account, User $owner, ?string $key = null, ?string $quoteKey = null): CareHourPurchase
    {
        $service = app(CareHourPurchaseService::class);

        return $service->start($account, 10, $owner, $quoteKey ?? $service->quote($account, 10)['quote_key'], $key ?? (string) Str::uuid(), url('/ok'), url('/cancel'));
    }

    private function gift(string $email): array
    {
        $gifts = app(CareHourGiftService::class);
        $verification = $gifts->requestVerification($email, 'Gift buyer');
        $token = $gifts->verify($verification->id, $this->sentCode());
        $key = (string) Str::uuid();
        $quoteKey = $gifts->quote(10)['quote_key'];
        $purchase = $gifts->start($token, 10, $quoteKey, $key, url('/ok'), url('/cancel'));

        return [$purchase, $token, $key, $quoteKey];
    }

    private function outsideContribution(): array
    {
        [$account, $owner] = $this->account();
        $service = app(CareHourContributionService::class);
        $link = $service->createLink($account, $owner)['link'];
        $verification = $service->requestVerification($link, 'outside@example.test', 'Outside');
        $token = $service->verify($link, $verification->id, $this->sentCode());

        return [$account, $link, $token];
    }

    private function startContribution(CareHourAccount $account, CareHourContributionLink $link, string $token, ?string $key = null, ?string $quoteKey = null): CareHourPurchase
    {
        $service = app(CareHourPurchaseService::class);

        return $service->startContribution($link, $token, 10, $quoteKey ?? $service->quote($account, 10)['quote_key'], $key ?? (string) Str::uuid(), url('/ok'), url('/cancel'));
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
}
