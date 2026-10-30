<?php

namespace Tests\Feature\CareHours;

use App\Models\CareHourAccount;
use App\Models\User;
use App\Notifications\CareHourPurchaseNotification;
use App\Notifications\CareHourVerificationNotification;
use App\Services\CareHours\CareHourContributionService;
use App\Services\CareHours\CareHourPurchaseService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CareHourContributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['care_hours.enabled' => true, 'care_hours.sales_enabled' => true, 'care_hours.welcome_enabled' => true, 'care_hours.contributions_enabled' => true]);
        Notification::fake();
    }

    public function test_verified_outside_contribution_does_not_create_membership_or_change_a_saved_card(): void
    {
        [$account, $owner, $contributions, $link, $token] = $this->verifiedLink();
        $before = User::count();
        $service = app(CareHourPurchaseService::class);
        $purchase = $service->startContribution($link, $token, 10, $service->quote($account, 10)['quote_key'], (string) Str::uuid(), url('/receipt/{PURCHASE_ID}'), url('/cancel'));
        $service->refresh($purchase);
        $this->assertNull($purchase->buyer_user_id);
        $this->assertSame('contributor@example.test', $purchase->buyer_email);
        $this->assertSame(660, $account->balance()['available_minutes']);
        $this->assertSame($before, User::count());
        $this->assertNull($owner->fresh()->stripe_customer_id);
        $service->authorizeReceipt($purchase, null, $token);
        $this->assertSame(1, $account->familyAccount->activeMemberships()->count());
    }

    public function test_accepted_contribution_finishes_after_link_revocation_but_new_checkout_cannot_start(): void
    {
        [$account, $owner, $contributions, $link, $token] = $this->verifiedLink();
        $service = app(CareHourPurchaseService::class);
        $request = (string) Str::uuid();
        $quote = $service->quote($account, 10);
        $purchase = $service->startContribution($link, $token, 10, $quote['quote_key'], $request, url('/ok'), url('/cancel'));
        $contributions->revoke($link, $owner);
        $this->assertSame('issued', $service->refresh($purchase)->status);
        $service->authorizeReceipt($purchase, null, $token);
        $this->assertSame($purchase->id, $service->startContribution($link, $token, 10, $quote['quote_key'], $request, url('/ok'), url('/cancel'))->id);
        $this->expectException(ValidationException::class);
        $service->startContribution($link, $token, 5, $service->quote($account, 5)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'));
    }

    public function test_wrong_verification_attempts_are_persisted_and_code_cannot_be_reused(): void
    {
        [$account, $owner] = $this->account();
        $service = app(CareHourContributionService::class);
        $link = $service->createLink($account, $owner)['link'];
        $verification = $service->requestVerification($link, 'buyer@example.test', 'Alex');
        $code = $this->sentCode();
        try {
            $service->verify($link, $verification->id, '000000');
            $this->fail();
        } catch (ValidationException) {
        }
        $this->assertSame(1, $verification->fresh()->attempts);
        $token = $service->verify($link, $verification->id, $code);
        $this->assertSame('buyer@example.test', $service->verifiedBuyer($link, $token)['email']);
        $this->expectException(ValidationException::class);
        $service->verify($link, $verification->id, $code);
    }

    public function test_private_link_and_recipient_owner_do_not_grant_access_to_donor_receipt(): void
    {
        [$account, $owner, $contributions, $link, $token] = $this->verifiedLink();
        $service = app(CareHourPurchaseService::class);
        $purchase = $service->startContribution($link, $token, 5, $service->quote($account, 5)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'));
        $this->expectException(AuthorizationException::class);
        $service->authorizeReceipt($purchase, $owner, null);
    }

    public function test_receipt_email_verification_works_after_link_revocation(): void
    {
        [$account, $owner, $contributions, $link, $token] = $this->verifiedLink();
        $service = app(CareHourPurchaseService::class);
        $purchase = $service->startContribution($link, $token, 5, $service->quote($account, 5)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'));
        $contributions->revoke($link, $owner);
        Notification::fake();
        $verification = $contributions->requestReceiptVerification($purchase, 'contributor@example.test');
        $newToken = $contributions->verifyReceipt($purchase, $verification->id, $this->sentCode());
        $service->authorizeReceipt($purchase, null, $newToken);
        $this->assertNotSame($token, $newToken);
    }

    public function test_receipt_verification_hides_whether_the_contributor_email_matched(): void
    {
        [$account, , $contributions, $link, $token] = $this->verifiedLink();
        $purchases = app(CareHourPurchaseService::class);
        $purchase = $purchases->startContribution($link, $token, 5, $purchases->quote($account, 5)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'));
        Notification::fake();
        $verification = $contributions->requestReceiptVerification($purchase, $purchase->buyer_email);
        $validCode = $this->sentCode();
        $wrongCode = $validCode === '000000' ? '111111' : '000000';
        $errors = [];
        foreach ([(string) Str::uuid(), $verification->id] as $id) {
            try {
                $contributions->verifyReceipt($purchase, $id, $wrongCode);
                $this->fail('Unknown verification and incorrect code must both be rejected.');
            } catch (ValidationException $exception) {
                $errors[] = $exception->errors();
            }
        }
        $this->assertSame($errors[0], $errors[1]);
        $this->assertSame(['code' => ['This code is invalid or expired. Request a new code.']], $errors[0]);
        $this->assertSame(1, $verification->fresh()->attempts);
    }

    public function test_different_family_owner_cannot_enable_or_revoke_a_link(): void
    {
        [$account] = $this->account();
        [, $other] = $this->account();
        $this->expectException(AuthorizationException::class);
        app(CareHourContributionService::class)->createLink($account, $other);
    }

    public function test_guest_receipt_uses_owner_approved_link_name_not_private_current_account_name(): void
    {
        [$account, , , $link, $token] = $this->verifiedLink();
        // Preserve the public label on links created before the family-wallet update.
        $link->update(['display_name' => 'Margaret']);
        $service = app(CareHourPurchaseService::class);
        $purchase = $service->startContribution($link, $token, 5, $service->quote($account, 5)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'));
        $account->update(['display_name' => 'Private full recipient name']);
        $message = (new CareHourPurchaseNotification($purchase))->toMail(new \stdClass);
        $copy = implode(' ', $message->introLines);
        $this->assertStringContainsString('Margaret', $copy);
        $this->assertStringNotContainsString('Private full recipient name', $copy);
    }

    private function verifiedLink(): array
    {
        [$account, $owner] = $this->account();
        $service = app(CareHourContributionService::class);
        $created = $service->createLink($account, $owner);
        $this->assertNotSame($created['token'], $created['link']->token_hash);
        $this->assertSame('Family care balance', $created['link']->display_name);
        $verification = $service->requestVerification($created['link'], 'contributor@example.test', 'Alex');
        $token = $service->verify($created['link'], $verification->id, $this->sentCode());

        return [$account, $owner, $service, $created['link'], $token];
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

    private function account(): array
    {
        $owner = User::factory()->create(['role' => 'family']);
        $family = app(FamilyAccountContext::class)->account($owner);

        return [CareHourAccount::create(['family_account_id' => $family->id, 'display_name' => 'Margaret', 'active' => true, 'currency' => 'usd']), $owner];
    }
}
