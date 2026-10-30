<?php

namespace Tests\Feature\CareHours;

use App\Livewire\Family\CreateCareRequestWizard;
use App\Models\CareHourAccount;
use App\Models\CareHourPurchase;
use App\Models\CareRecipient;
use App\Models\CareRequest;
use App\Models\User;
use App\Notifications\CareHourVerificationNotification;
use App\Services\CareHours\CareHourGiftService;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\CareHours\CareHourPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FamilyWalletScreensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['care_hours.enabled' => true, 'care_hours.enrollment_enabled' => true,
            'care_hours.sales_enabled' => true, 'care_hours.gifts_enabled' => true,
            'care_hours.welcome_enabled' => true, 'care_hours.pilot_only' => true,
            'care_hours.pilot_emails' => ['peverelli.t@gmail.com'], 'services.stripe.bypass' => true]);
        Notification::fake();
    }

    public function test_buy_opens_packages_without_a_care_profile_and_get_does_not_enroll(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->get(route('family.care-hours.index'))->assertOk()->assertSee('Your activity')
            ->assertDontSee('Choose a care balance')->assertViewHas('availableMinutes', 0);
        $this->get(route('family.care-hours.buy'))->assertRedirect(route('family.care-hours.index'));
        $this->assertDatabaseCount('care_hour_accounts', 0);
        $this->post(route('family.care-hours.buy.start'))->assertRedirect();
        $wallet = CareHourAccount::sole();
        $this->get(route('family.care-hours.packages', $wallet))->assertOk()->assertSee('Add hours to your family balance')
            ->assertSee('once per family account')->assertDontSee('Choose a care balance');
        $this->assertNull($wallet->care_recipient_profile_id);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->assertDatabaseCount('care_hour_purchases', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_old_request_records_do_not_add_choices_or_create_balances(): void
    {
        $owner = $this->owner();
        $wallet = app(CareHourIdentityService::class)->ensureFamilyWallet($owner);
        foreach (['Colette Peverelli', 'Colette Peverelli', 'Another person'] as $name) {
            $request = CareRequest::create(['family_user_id' => $owner->id, 'family_account_id' => $wallet->family_account_id,
                'title' => 'Care', 'status' => 'open', 'request_type' => 'one_time',
                'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601']);
            CareRecipient::create(['care_request_id' => $request->id, 'full_name' => $name, 'relationship_to_family' => 'Parent']);
        }
        $this->actingAs($owner)->post(route('family.care-hours.buy.start'))
            ->assertRedirect(route('family.care-hours.packages', $wallet));
        $this->get(route('family.care-hours.buy'))->assertRedirect(route('family.care-hours.packages', $wallet));
        $this->assertDatabaseCount('care_hour_accounts', 1);
        $this->assertDatabaseCount('care_hour_recipient_links', 0);
    }

    public function test_new_recipient_uses_family_credit_and_switching_people_preserves_card_choice(): void
    {
        $owner = $this->owner();
        $wallet = app(CareHourIdentityService::class)->ensureFamilyWallet($owner);
        $this->credit($wallet, $owner);
        Livewire::actingAs($owner)->test(CreateCareRequestWizard::class)
            ->set('recipient_full_name', 'Colette Peverelli')->set('recipient_relationship_to_family', 'Mother')
            ->assertSet('careHourAccountId', $wallet->id)
            ->set('careHourAccountId', null)->set('recipient_full_name', 'Robert Bennett')
            ->assertSet('careHourAccountId', null);
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertSame(300, $wallet->balance()['available_minutes']);
    }

    public function test_gift_can_be_claimed_into_family_wallet_without_a_profile_while_new_sales_are_paused(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $gifts = app(CareHourGiftService::class);
        $verification = $gifts->requestVerification($owner->email, $owner->name);
        $otp = '';
        Notification::assertSentOnDemand(CareHourVerificationNotification::class, function ($notice) use (&$otp) {
            $otp = $notice->code;

            return true;
        });
        $token = $gifts->verify($verification->id, $otp);
        $purchase = $gifts->start($token, 10, $gifts->quote(10)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'));
        $purchase = app(CareHourPurchaseService::class)->refresh($purchase);
        $code = $gifts->codeForBuyer($purchase, null, $token);
        config(['care_hours.enabled' => false, 'care_hours.gifts_enabled' => false, 'care_hours.enrollment_enabled' => false]);
        $this->get(route('family.care-hours.claim'))->assertOk()->assertDontSee('Who is this gift for?')
            ->assertDontSee('name="recipient"', false);
        $this->post(route('family.care-hours.claim.preview'), ['code' => $code])->assertRedirect();
        $this->get(route('family.care-hours.claim'))->assertOk()->assertSee('Your family balance')->assertSee('11 h');
        $this->post(route('family.care-hours.claim.confirm'), ['confirm' => 1, 'nonce' => session('care_hours.gift_claim.nonce')])->assertRedirect();
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->assertDatabaseCount('care_requests', 0);
        $this->assertSame(660, CareHourAccount::sole()->balance()['available_minutes']);
    }

    public function test_admin_shows_one_family_wallet_and_includes_historical_source_issues(): void
    {
        $owner = $this->owner();
        $wallet = app(CareHourIdentityService::class)->ensureFamilyWallet($owner);
        $source = CareHourAccount::create(['family_account_id' => $wallet->family_account_id, 'display_name' => 'Old recipient']);
        $this->credit($wallet, $owner);
        $purchase = $this->credit($source, $owner);
        $purchase->update(['status' => 'held']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.care-hours.index', ['attention' => 1]))->assertOk()
            ->assertViewHas('accounts', fn ($accounts) => $accounts->total() === 1 && $accounts->first()->id === $wallet->id
                && (int) $accounts->first()->purchase_attention_count === 1);
        $this->get(route('admin.care-hours.show', $wallet))->assertOk()->assertSee($purchase->public_id);
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => 'family', 'email' => 'peverelli.t@gmail.com', 'email_verified_at' => now()]);
    }

    private function credit(CareHourAccount $wallet, User $owner): CareHourPurchase
    {
        $purchase = CareHourPurchase::create(['public_id' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(),
            'care_hour_account_id' => $wallet->id, 'family_account_id' => $wallet->family_account_id,
            'buyer_user_id' => $owner->id, 'buyer_email' => $owner->email, 'buyer_name' => $owner->name,
            'paid_minutes' => 300, 'bonus_minutes' => 0, 'total_minutes' => 300, 'amount_cents' => 15500,
            'currency' => 'usd', 'status' => 'paid', 'processing_fee_cents' => 0, 'terms_version' => 'test', 'quote' => [],
            'terms_accepted_at' => now(), 'success_url' => '/care-hours', 'cancel_url' => '/care-hours']);
        app(CareHourLedgerService::class)->issuePurchase($purchase);

        return $purchase;
    }
}
