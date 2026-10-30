<?php

namespace Tests\Feature\CareHours;

use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\FamilyAccountMember;
use App\Models\User;
use App\Notifications\CareHourVerificationNotification;
use App\Services\CareHours\CareHourGiftService;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourPurchaseService;
use App\Services\CareRecipientProfiles\CareRecipientProfileService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class GiftSelfClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['care_hours.enabled' => true, 'care_hours.sales_enabled' => true, 'care_hours.enrollment_enabled' => true,
            'care_hours.gifts_enabled' => true, 'care_hours.welcome_enabled' => true, 'services.stripe.bypass' => true]);
        Notification::fake();
    }

    public function test_new_family_can_inspect_then_claim_without_creating_a_care_profile(): void
    {
        [$purchase, $code] = $this->gift();
        $gifts = app(CareHourGiftService::class);
        $this->assertSame(['paid_minutes' => 600, 'conditional_bonus_minutes' => 60], $gifts->inspectForClaim($code));
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $actor = User::factory()->create(['role' => 'family', 'name' => 'New Mum']);
        $account = $gifts->resolveSelfClaimAccount($code, $actor);
        $this->assertSame($account->id, $gifts->resolveSelfClaimAccount($code, $actor)->id);
        $this->assertNull($account->care_recipient_profile_id);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->assertDatabaseCount('care_recipient_profile_versions', 0);
        $this->assertSame(0, $account->balance()['available_minutes']);
        $quote = $gifts->previewForClaim($code, $account, $actor);
        $claimed = $gifts->claim($code, $account, $actor, $quote['quote_key']);
        $this->assertSame($claimed->id, $gifts->claim($code, $account, $actor, $quote['quote_key'])->id);
        $this->assertSame($purchase->id, $claimed->id);
        $this->assertSame(660, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_hour_accounts', 1);
        $this->assertDatabaseCount('care_hour_lots', 1);
        $this->assertDatabaseCount('care_requests', 0);
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertNull($actor->fresh()->stripe_customer_id);
    }

    public function test_family_member_claim_uses_the_owners_shared_family_wallet(): void
    {
        $owner = User::factory()->create(['role' => 'family', 'name' => 'Same Name']);
        $family = app(FamilyAccountContext::class)->account($owner);
        $ownerProfile = app(CareRecipientProfileService::class)->saveDraft($owner, null, ['full_name' => $owner->name, 'recipient_is_requester' => true]);
        $ownerAccount = app(CareHourIdentityService::class)->enrollProfile($ownerProfile, $owner);
        $member = User::factory()->create(['role' => 'family', 'name' => 'Same Name']);
        FamilyAccountMember::create(['family_account_id' => $family->id, 'user_id' => $member->id,
            'access_level' => 'member', 'status' => 'active', 'joined_at' => now()]);
        [, $code] = $this->gift();
        $gifts = app(CareHourGiftService::class);
        $account = $gifts->resolveSelfClaimAccount($code, $member);
        $this->assertSame($ownerAccount->id, $account->id);
        $this->assertNull($account->care_recipient_profile_id);
        $this->assertDatabaseCount('care_recipient_profiles', 1);
        $quote = $gifts->previewForClaim($code, $account, $member);
        $gifts->claim($code, $account, $member, $quote['quote_key']);
        $this->assertSame(660, $ownerAccount->balance()['available_minutes']);
        $this->assertSame(660, $account->balance()['available_minutes']);
        $this->assertSame($ownerProfile->id, $family->fresh()->default_care_recipient_profile_id);
    }

    public function test_existing_self_identity_and_welcome_history_are_reused_when_sales_are_paused(): void
    {
        $actor = User::factory()->create(['role' => 'family']);
        $profile = app(CareRecipientProfileService::class)->saveDraft($actor, null, ['full_name' => 'Existing Self', 'recipient_is_requester' => true]);
        $account = app(CareHourIdentityService::class)->enrollProfile($profile, $actor);
        $purchases = app(CareHourPurchaseService::class);
        $purchases->refresh($purchases->start($account, 10, $actor, $purchases->quote($account, 10)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel')));
        [, $code] = $this->gift();
        config(['care_hours.enabled' => false, 'care_hours.sales_enabled' => false, 'care_hours.enrollment_enabled' => false,
            'care_hours.gifts_enabled' => false, 'care_hours.welcome_enabled' => false]);
        $gifts = app(CareHourGiftService::class);
        $this->assertSame($account->id, $gifts->resolveSelfClaimAccount($code, $actor)->id);
        $quote = $gifts->previewForClaim($code, $account, $actor);
        $this->assertSame(0, $quote['bonus_minutes']);
        $gifts->claim($code, $account, $actor, $quote['quote_key']);
        $this->assertSame(1260, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_recipient_profiles', 1);
        $this->assertDatabaseCount('care_hour_welcome_claims', 1);
    }

    public function test_five_hour_self_claim_keeps_welcome_available_and_paid_gift_can_create_identity_while_paused(): void
    {
        [, $five] = $this->gift(5);
        [, $ten] = $this->gift(10);
        config(['care_hours.enabled' => false, 'care_hours.sales_enabled' => false, 'care_hours.enrollment_enabled' => false,
            'care_hours.gifts_enabled' => false, 'care_hours.welcome_enabled' => false]);
        $actor = User::factory()->create(['role' => 'family']);
        $gifts = app(CareHourGiftService::class);
        $account = $gifts->resolveSelfClaimAccount($five, $actor);
        $quote = $gifts->previewForClaim($five, $account, $actor);
        $this->assertSame(0, $quote['bonus_minutes']);
        $gifts->claim($five, $account, $actor, $quote['quote_key']);
        $this->assertDatabaseCount('care_hour_welcome_claims', 0);
        $this->assertSame($account->id, $gifts->resolveSelfClaimAccount($ten, $actor)->id);
        $quote = $gifts->previewForClaim($ten, $account, $actor);
        $this->assertSame(60, $quote['bonus_minutes']);
        $gifts->claim($ten, $account, $actor, $quote['quote_key']);
        $this->assertSame(960, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->assertDatabaseCount('care_hour_accounts', 1);
    }

    public function test_request_self_identity_reuses_its_existing_balance_without_name_matching(): void
    {
        $actor = User::factory()->create(['role' => 'family', 'name' => 'Current Name']);
        $family = app(FamilyAccountContext::class)->account($actor);
        $request = CareRequest::create(['family_account_id' => $family->id, 'family_user_id' => $actor->id,
            'created_by_user_id' => $actor->id, 'title' => 'Earlier care', 'status' => 'open', 'request_type' => 'one_time',
            'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601']);
        $recipient = $request->recipient()->create(['recipient_is_requester' => true, 'relationship_to_family' => 'self', 'full_name' => 'Previous Name']);
        $account = app(CareHourIdentityService::class)->enroll($recipient, $actor);
        [, $code] = $this->gift();
        $resolved = app(CareHourGiftService::class)->resolveSelfClaimAccount($code, $actor);
        $this->assertSame($account->id, $resolved->id);
        $this->assertNull($resolved->care_recipient_profile_id);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->assertDatabaseCount('care_hour_accounts', 1);
        $this->assertDatabaseCount('care_requests', 1);
    }

    public function test_claim_uses_shared_wallet_without_changing_a_loved_ones_profile(): void
    {
        $actor = User::factory()->create(['role' => 'family', 'name' => 'Same Name']);
        $lovedOne = app(CareRecipientProfileService::class)->saveDraft($actor, null, [
            'full_name' => $actor->name, 'recipient_is_requester' => false, 'relationship_to_family' => 'parent',
        ]);
        $lovedOneAccount = app(CareHourIdentityService::class)->enrollProfile($lovedOne, $actor);
        [, $code] = $this->gift();
        $account = app(CareHourGiftService::class)->resolveSelfClaimAccount($code, $actor);
        $this->assertSame($lovedOneAccount->id, $account->id);
        $this->assertFalse($lovedOne->fresh()->recipient_is_requester);
        $this->assertDatabaseCount('care_recipient_profiles', 1);
    }

    public function test_member_claim_does_not_depend_on_request_recipient_identity(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $family = app(FamilyAccountContext::class)->account($owner);
        $ownerProfile = app(CareRecipientProfileService::class)->saveDraft($owner, null, ['full_name' => $owner->name, 'recipient_is_requester' => true]);
        $member = User::factory()->create(['role' => 'family']);
        FamilyAccountMember::create(['family_account_id' => $family->id, 'user_id' => $member->id,
            'access_level' => 'member', 'status' => 'active', 'joined_at' => now()]);
        $request = CareRequest::create(['family_account_id' => $family->id, 'family_user_id' => $owner->id,
            'created_by_user_id' => $member->id, 'title' => 'Earlier care', 'status' => 'open', 'request_type' => 'one_time',
            'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601']);
        $request->recipient()->create(['recipient_is_requester' => true, 'relationship_to_family' => 'self',
            'full_name' => $member->name, 'care_recipient_profile_id' => $ownerProfile->id]);
        [, $code] = $this->gift();
        $account = app(CareHourGiftService::class)->resolveSelfClaimAccount($code, $member);
        $this->assertSame($family->id, $account->family_account_id);
        $this->assertNull($account->care_recipient_profile_id);
        $this->assertDatabaseCount('care_recipient_profiles', 1);
        $this->assertDatabaseCount('care_hour_accounts', 1);
    }

    public function test_profiles_do_not_block_claim_but_an_inactive_family_wallet_cannot_be_replaced(): void
    {
        foreach (['ambiguous', 'archived', 'inactive'] as $case) {
            $actor = User::factory()->create(['role' => 'family']);
            $profiles = app(CareRecipientProfileService::class);
            $profile = $profiles->saveDraft($actor, null, ['full_name' => $actor->name, 'recipient_is_requester' => true]);
            if ($case === 'ambiguous') {
                $profiles->saveDraft($actor, null, ['full_name' => $actor->name, 'recipient_is_requester' => true]);
            } elseif ($case === 'archived') {
                $profiles->archive($actor, $profile);
            } else {
                app(CareHourIdentityService::class)->enrollProfile($profile, $actor)->update(['active' => false]);
            }
            [, $code] = $this->gift();
            $before = CareRecipientProfile::count();
            if ($case === 'inactive') {
                $this->assertUnavailable(fn () => app(CareHourGiftService::class)->resolveSelfClaimAccount($code, $actor));
            } else {
                $account = app(CareHourGiftService::class)->resolveSelfClaimAccount($code, $actor);
                $this->assertNull($account->care_recipient_profile_id);
            }
            $this->assertSame($before, CareRecipientProfile::count());
        }
    }

    public function test_invalid_and_authoritatively_blocked_gifts_never_create_a_profile(): void
    {
        $actor = User::factory()->create(['role' => 'family']);
        $gifts = app(CareHourGiftService::class);
        $this->assertUnavailable(fn () => $gifts->resolveSelfClaimAccount('invalid', $actor));
        [$purchase, $code] = $this->gift();
        $reader = Mockery::mock(CareHourPurchaseService::class);
        $reader->shouldReceive('refresh')->twice()->andReturnUsing(function ($current) {
            $current->update(['status' => 'held', 'amount_reversed_cents' => 1]);

            return $current->fresh();
        });
        $this->app->instance(CareHourPurchaseService::class, $reader);
        $this->assertUnavailable(fn () => $gifts->inspectForClaim($code));
        $this->assertUnavailable(fn () => $gifts->resolveSelfClaimAccount($code, $actor));
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->assertDatabaseCount('care_hour_accounts', 0);
        $this->assertDatabaseCount('family_accounts', 0);
        $this->assertSame('held', $purchase->fresh()->status);
    }

    public function test_final_locked_gift_recheck_rolls_back_identity_when_payment_becomes_unavailable(): void
    {
        [$purchase, $code] = $this->gift();
        $actor = User::factory()->create(['role' => 'family']);
        $real = app(CareHourIdentityService::class);
        $identities = Mockery::mock(CareHourIdentityService::class);
        $identities->shouldReceive('ensureFamilyWallet')->once()->andReturnUsing(function ($user, $gift) use ($real, $purchase) {
            $account = $real->ensureFamilyWallet($user, $gift);
            $purchase->update(['status' => 'held']);

            return $account;
        });
        $this->app->instance(CareHourIdentityService::class, $identities);
        $this->assertUnavailable(fn () => app(CareHourGiftService::class)->resolveSelfClaimAccount($code, $actor));
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->assertDatabaseCount('care_hour_accounts', 0);
        $this->assertSame(0, DB::table('family_account_activity_logs')->where('action', 'care_profile_created')->count());
    }

    private function gift(int $hours = 10): array
    {
        $gifts = app(CareHourGiftService::class);
        $verification = $gifts->requestVerification(Str::uuid().'@example.test', 'Private Gift Buyer');
        $code = '';
        Notification::assertSentOnDemand(CareHourVerificationNotification::class, function ($notice) use (&$code): bool {
            $code = $notice->code;

            return true;
        });
        $token = $gifts->verify($verification->id, $code);
        $purchase = $gifts->start($token, $hours, $gifts->quote($hours)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'));
        $purchase = app(CareHourPurchaseService::class)->refresh($purchase);

        return [$purchase, $gifts->codeForBuyer($purchase, null, $token)];
    }

    private function assertUnavailable(callable $callback): void
    {
        try {
            $callback();
            $this->fail('An unavailable gift or inactive wallet must not create another wallet.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }
}
