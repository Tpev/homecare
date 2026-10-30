<?php

namespace Tests\Feature\CareHours;

use App\Models\CareHourAccount;
use App\Models\CareRecipientProfile;
use App\Models\User;
use App\Notifications\CareHourVerificationNotification;
use App\Services\CareHours\CareHourGiftService;
use App\Services\CareHours\CareHourPurchaseService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Support\CareHourGiftClaimIntent;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\TestCase;

class GiftOnboardingJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['care_hours.enabled' => true, 'care_hours.sales_enabled' => true, 'care_hours.gifts_enabled' => true,
            'care_hours.welcome_enabled' => true, 'care_hours.enrollment_enabled' => true]);
        Notification::fake();
    }

    public function test_jane_buys_before_mum_exists_and_mum_enters_code_registers_and_claims_for_herself(): void
    {
        [$purchase, $code] = $this->gift();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->assertDatabaseCount('care_hour_lots', 0);
        $this->get(route('care-hours.claim'))->assertOk()->assertSee('Claim your gift of care')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->post(route('care-hours.claim.start'), ['code' => $code])->assertRedirect(route('register'));
        $this->assertStringNotContainsString($code, json_encode(session(CareHourGiftClaimIntent::SESSION_KEY)));
        $this->get(route('register'))->assertOk()->assertSee('Create your account to claim your gift')
            ->assertDontSee($code)->assertDontSee($purchase->buyer_email);

        Event::fake([Registered::class]);
        Volt::test('pages.auth.register')->set('name', 'Linda Bennett')->set('email', 'linda@example.test')
            ->set('password', 'password')->set('password_confirmation', 'password')->set('accept_terms', true)
            ->call('register')->assertHasNoErrors()->assertRedirect(route('family.care-hours.claim', absolute: false));
        $mum = User::where('email', 'linda@example.test')->sole();
        $this->assertAuthenticatedAs($mum);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->get(route('family.care-hours.claim'))->assertOk()->assertSee('Your gift is saved. No card or care profile needed.')
            ->assertDontSee('name="recipient"', false)->assertDontSee('Set up care')->assertDontSee($code);
        $this->post(route('family.care-hours.claim.preview'), ['recipient' => 'self'])->assertRedirect(route('family.care-hours.claim'));
        $account = CareHourAccount::sole();
        $this->assertNull($account->care_recipient_profile_id);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->assertSame(0, $account->balance()['available_minutes']);
        $this->get(route('family.care-hours.claim'))->assertOk()->assertSee('Review your gift')->assertSee('11 h')
            ->assertSee('Free welcome hours')->assertDontSee($code)->assertDontSee($purchase->buyer_email);
        $nonce = session('care_hours.gift_claim.nonce');
        $this->post(route('family.care-hours.claim.confirm'), ['nonce' => $nonce, 'confirm' => 1])
            ->assertRedirect(route('family.care-hours.claim.ready', $purchase->public_id))
            ->assertSessionMissing(CareHourGiftClaimIntent::SESSION_KEY)->assertSessionMissing('care_hours.gift_claim');
        $this->get(route('family.care-hours.claim.ready', $purchase->public_id))->assertOk()
            ->assertSee('Your care hours are ready')->assertSee('11 h added')->assertSee('Find care')
            ->assertSee('1 h free welcome care')->assertDontSee('@if')->assertDontSee('@endif')
            ->assertDontSee($code)->assertDontSee($purchase->buyer_email);
        $this->assertSame(660, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_hour_lots', 1);
        $this->assertDatabaseCount('care_hour_welcome_claims', 1);
        $this->assertDatabaseCount('care_requests', 0);
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertDatabaseCount('care_recipient_profile_versions', 0);
        $this->assertNull($mum->fresh()->stripe_customer_id);
        $this->assertNull($account->familyAccount->stripe_customer_id);
        $this->post(route('family.care-hours.claim.confirm'), ['nonce' => $nonce, 'confirm' => 1])->assertStatus(422);
        $this->from(route('care-hours.claim'))->post(route('care-hours.claim.start'), ['code' => $code])
            ->assertSessionHasErrors('gift_code')->assertSessionMissing('_old_input.code');
        $this->assertDatabaseCount('care_hour_lots', 1);
    }

    public function test_public_entry_rejects_invalid_codes_without_leaking_or_creating_identity_and_throttles_attempts(): void
    {
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $this->from(route('care-hours.claim'))->post(route('care-hours.claim.start'), ['code' => 'PRIVATE-INVALID-CODE'])
                ->assertSessionHasErrors('gift_code')->assertSessionMissing('_old_input.code')
                ->assertSessionMissing(CareHourGiftClaimIntent::SESSION_KEY);
        }
        $this->post(route('care-hours.claim.start'), ['code' => 'PRIVATE-INVALID-CODE'])->assertStatus(429);
        $this->get(route('care-hours.claim'))->assertOk()->assertDontSee('PRIVATE-INVALID-CODE');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->assertDatabaseCount('care_hour_accounts', 0);
    }

    public function test_paid_gift_can_be_claimed_for_self_after_new_sales_are_disabled(): void
    {
        [$purchase, $code] = $this->gift();
        config(['care_hours.enabled' => false, 'care_hours.sales_enabled' => false, 'care_hours.gifts_enabled' => false,
            'care_hours.enrollment_enabled' => false]);
        $this->get(route('care-hours.gifts.index'))->assertNotFound();
        $mum = User::factory()->create(['role' => 'family']);
        $this->actingAs($mum)->post(route('care-hours.claim.start'), ['code' => $code])
            ->assertRedirect(route('family.care-hours.claim'));
        $this->post(route('family.care-hours.claim.preview'), ['recipient' => 'self'])->assertRedirect();
        $this->post(route('family.care-hours.claim.confirm'), ['nonce' => session('care_hours.gift_claim.nonce'), 'confirm' => 1])
            ->assertRedirect(route('family.care-hours.claim.ready', $purchase->public_id));
        $this->assertSame(660, CareHourAccount::sole()->balance()['available_minutes']);
    }

    public function test_payment_hold_after_entry_blocks_profile_creation_and_hold_after_review_blocks_credit(): void
    {
        [$purchase, $code] = $this->gift();
        $mum = User::factory()->create(['role' => 'family']);
        $this->actingAs($mum)->post(route('care-hours.claim.start'), ['code' => $code])->assertRedirect();
        $purchase->update(['correction_state' => 'admin_review']);
        $this->post(route('family.care-hours.claim.preview'), ['recipient' => 'self'])->assertSessionHasErrors('gift_code');
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $purchase->update(['correction_state' => null]);
        $this->post(route('family.care-hours.claim.preview'), ['recipient' => 'self'])->assertRedirect();
        $purchase->update(['correction_state' => 'admin_review']);
        $this->post(route('family.care-hours.claim.confirm'), ['nonce' => session('care_hours.gift_claim.nonce'), 'confirm' => 1])
            ->assertSessionHasErrors('gift_code');
        $this->assertDatabaseCount('care_hour_lots', 0);
        $this->assertDatabaseCount('care_hour_welcome_claims', 0);
        $this->assertNull($purchase->gift->fresh()->claimed_at);
    }

    public function test_legacy_recipient_claim_payload_resolves_to_family_wallet_and_ready_page_is_family_scoped(): void
    {
        [$purchase, $code] = $this->gift();
        $actor = User::factory()->create(['role' => 'family']);
        $family = app(FamilyAccountContext::class)->account($actor);
        $profile = CareRecipientProfile::create(['family_account_id' => $family->id, 'created_by_user_id' => $actor->id,
            'full_name' => 'Robert Bennett', 'status' => 'draft']);
        $this->actingAs($actor)->post(route('care-hours.claim.start'), ['code' => $code])->assertRedirect();
        $this->get(route('family.care-hours.claim'))->assertOk()->assertSee('Your gift is saved. No card or care profile needed.')->assertDontSee('Robert Bennett');
        $this->post(route('family.care-hours.claim.preview'), ['recipient' => 'profile:'.$profile->id])->assertRedirect();
        $this->post(route('family.care-hours.claim.confirm'), ['nonce' => session('care_hours.gift_claim.nonce'), 'confirm' => 1])
            ->assertRedirect(route('family.care-hours.claim.ready', $purchase->public_id));
        $this->assertDatabaseCount('care_recipient_profiles', 1);
        $other = User::factory()->create(['role' => 'family']);
        $this->actingAs($other)->get(route('family.care-hours.claim.ready', $purchase->public_id))->assertForbidden();
    }

    public function test_review_is_actor_bound_and_changing_code_removes_the_previous_review(): void
    {
        [, $code] = $this->gift();
        $mum = User::factory()->create(['role' => 'family']);
        $this->actingAs($mum)->post(route('care-hours.claim.start'), ['code' => $code])->assertRedirect();
        $this->post(route('family.care-hours.claim.preview'), ['recipient' => 'self'])->assertRedirect();
        $nonce = session('care_hours.gift_claim.nonce');
        $other = User::factory()->create(['role' => 'family']);
        $this->actingAs($other)->post(route('family.care-hours.claim.confirm'), ['nonce' => $nonce, 'confirm' => 1])->assertForbidden();
        $this->assertDatabaseCount('care_hour_lots', 0);
        $this->actingAs($mum)->post(route('care-hours.claim.reset'))->assertRedirect(route('care-hours.claim'))
            ->assertSessionMissing('care_hours.gift_claim')->assertSessionMissing(CareHourGiftClaimIntent::SESSION_KEY);
        $this->post(route('family.care-hours.claim.confirm'), ['nonce' => $nonce, 'confirm' => 1])->assertStatus(422);
    }

    public function test_validation_never_flashes_a_code_from_alternate_input_bags(): void
    {
        $privateCode = 'PRIVATE-INVALID-QUERY-CODE';
        $this->from(route('care-hours.claim'))->post(route('care-hours.claim.start', ['code' => $privateCode]))
            ->assertSessionHasErrors('gift_code')->assertSessionMissing('_old_input.code');
        $mum = User::factory()->create(['role' => 'family']);
        $this->actingAs($mum)->from(route('family.care-hours.claim'))
            ->post(route('family.care-hours.claim.preview', ['code' => $privateCode]), ['recipient' => 'invalid'])
            ->assertSessionHasErrors('recipient')->assertSessionMissing('_old_input.code');
        $this->call('POST', route('care-hours.claim.start'), [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'text/html'], json_encode(['code' => $privateCode]))
            ->assertSessionHasErrors('gift_code')->assertSessionMissing('_old_input.code');
    }

    public function test_revisiting_ready_after_a_payment_hold_shows_current_balance_instead_of_ready_claim(): void
    {
        [$purchase, $code] = $this->gift();
        $mum = User::factory()->create(['role' => 'family']);
        $this->actingAs($mum)->post(route('care-hours.claim.start'), ['code' => $code])->assertRedirect();
        $this->post(route('family.care-hours.claim.preview'), ['recipient' => 'self'])->assertRedirect();
        $this->post(route('family.care-hours.claim.confirm'), ['nonce' => session('care_hours.gift_claim.nonce'), 'confirm' => 1])->assertRedirect();
        app(CareHourPurchaseService::class)->holdForReversal($purchase->fresh(), 31000);
        $account = CareHourAccount::sole();
        $this->get(route('family.care-hours.claim.ready', $purchase->public_id))
            ->assertRedirect(route('family.care-hours.show', $account))->assertSessionHasErrors('gift_code');
        $this->assertSame(0, $account->balance()['available_minutes']);
    }

    public function test_five_hour_gift_ready_screen_shows_no_welcome_bonus_or_template_directives(): void
    {
        [$purchase, $code] = $this->gift(5);
        $mum = User::factory()->create(['role' => 'family']);
        $this->actingAs($mum)->post(route('care-hours.claim.start'), ['code' => $code])->assertRedirect();
        $this->post(route('family.care-hours.claim.preview'), ['recipient' => 'self'])->assertRedirect();
        $this->post(route('family.care-hours.claim.confirm'), ['nonce' => session('care_hours.gift_claim.nonce'), 'confirm' => 1])->assertRedirect();
        $this->get(route('family.care-hours.claim.ready', $purchase->public_id))->assertOk()->assertSee('5 h added')
            ->assertDontSee('free welcome care')->assertDontSee('@if')->assertDontSee('@endif');
        $this->assertSame(300, CareHourAccount::sole()->balance()['available_minutes']);
    }

    private function gift(int $hours = 10): array
    {
        $gifts = app(CareHourGiftService::class);
        $verification = $gifts->requestVerification('jane@example.test', 'Jane Bennett');
        $code = '';
        Notification::assertSentOnDemand(CareHourVerificationNotification::class, function ($notice) use (&$code) {
            $code = $notice->code;

            return true;
        });
        $token = $gifts->verify($verification->id, $code);
        $purchase = $gifts->start($token, $hours, $gifts->quote($hours)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'));
        $purchase = app(CareHourPurchaseService::class)->refresh($purchase);

        return [$purchase, $gifts->codeForBuyer($purchase, null, $token)];
    }
}
