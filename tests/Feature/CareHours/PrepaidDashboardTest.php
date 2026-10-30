<?php

namespace Tests\Feature\CareHours;

use App\Models\CareHourAccount;
use App\Models\CareRecipient;
use App\Models\CareRequest;
use App\Models\User;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourPurchaseService;
use App\Services\CareRecipientProfiles\CareRecipientProfileService;
use App\Services\FamilyAccounts\FamilyAccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrepaidDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'care_hours.enabled' => true, 'care_hours.enrollment_enabled' => true,
            'care_hours.sales_enabled' => true, 'care_hours.welcome_enabled' => true,
            'care_hours.gifts_enabled' => true, 'care_hours.pilot_only' => true,
            'care_hours.pilot_emails' => ['peverelli.t@gmail.com'], 'services.stripe.bypass' => true,
        ]);
        Notification::fake();
    }

    public function test_empty_dashboard_shows_available_credit_without_recipient_form_or_get_enrollment(): void
    {
        $owner = $this->owner();
        $this->profile($owner, 'Linda Bennett');
        $this->actingAs($owner)->get(route('family.care-hours.index'))->assertOk()
            ->assertSee('Prepaid care hours')->assertSee('Available credit')->assertSee('Buy prepaid care')
            ->assertDontSee('Who are these hours for?')->assertDontSee('name="recipient"', false)
            ->assertDontSee('Linda Bennett')->assertViewHas('availableMinutes', 0);
        $this->assertDatabaseCount('care_hour_accounts', 0);
        $this->assertDatabaseCount('care_hour_purchases', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_single_profile_is_enrolled_only_after_buy_action_and_repeated_action_reuses_balance(): void
    {
        $owner = $this->owner();
        $profile = $this->profile($owner, 'Linda Bennett');
        $this->actingAs($owner)->get(route('family.care-hours.buy'))->assertRedirect(route('family.care-hours.index'));
        $this->assertDatabaseCount('care_hour_accounts', 0);
        $this->post(route('family.care-hours.buy.start'))->assertRedirect();
        $account = CareHourAccount::sole();
        $this->assertNull($account->care_recipient_profile_id);
        $this->post(route('family.care-hours.buy.start'))->assertRedirect(route('family.care-hours.packages', $account));
        $this->get(route('family.care-hours.index'))->assertOk()->assertDontSee('Choose a care balance');
        $this->assertDatabaseCount('care_hour_accounts', 1);
        $this->assertDatabaseCount('care_hour_purchases', 0);
    }

    public function test_single_existing_balance_opens_packages_without_creating_another_account(): void
    {
        $owner = $this->owner();
        $account = $this->account($owner, 'Linda Bennett');
        $this->actingAs($owner)->get(route('family.care-hours.buy'))->assertRedirect(route('family.care-hours.packages', $account));
        $this->post(route('family.care-hours.buy.start'))->assertRedirect(route('family.care-hours.packages', $account));
        $this->assertDatabaseCount('care_hour_accounts', 1);
        $this->assertDatabaseCount('care_hour_purchases', 0);
    }

    public function test_multiple_profiles_use_one_family_wallet_without_merging_care_profiles(): void
    {
        $owner = $this->owner();
        $first = $this->profile($owner, 'Alex Bennett');
        $second = $this->profile($owner, 'Alex Bennett');
        $this->actingAs($owner)->post(route('family.care-hours.buy.start'))->assertRedirect();
        $account = CareHourAccount::sole();
        $this->assertNull($account->care_recipient_profile_id);
        $this->get(route('family.care-hours.buy'))->assertRedirect(route('family.care-hours.packages', $account));
        $this->post(route('family.care-hours.buy.start'), ['target' => 'profile:'.$second->id])
            ->assertRedirect(route('family.care-hours.packages', $account));
        $this->assertDatabaseCount('care_hour_accounts', 1);
        $this->assertDatabaseCount('care_recipient_profiles', 2);
        $this->assertDatabaseMissing('care_hour_accounts', ['care_recipient_profile_id' => $first->id]);
    }

    public function test_foreign_archived_or_inactive_purchase_targets_are_rejected_before_writes(): void
    {
        $owner = $this->owner();
        $other = User::factory()->create(['role' => 'family']);
        $foreign = $this->profile($other, 'Private person');
        $foreignRecipient = $this->recipient($other, 'Private recipient');
        $foreignAccount = $this->account($other, 'Private account');
        $archived = $this->profile($owner, 'Archived person');
        $archived->update(['status' => 'archived']);
        $inactive = $this->account($owner, 'Inactive account');
        $inactive->update(['active' => false]);
        foreach (['profile:'.$foreign->id, 'recipient:'.$foreignRecipient->id, 'profile:'.$archived->id, 'account:'.$foreignAccount->id, 'profile:999999'] as $target) {
            $this->actingAs($owner)->post(route('family.care-hours.buy.start'), ['target' => $target])->assertForbidden();
        }
        $this->post(route('family.care-hours.buy.start'), ['target' => 'account:'.$inactive->id])->assertSessionHasErrors();
        $this->assertDatabaseCount('care_hour_accounts', 2);
        $this->assertDatabaseCount('care_hour_purchases', 0);
        $this->assertDatabaseCount('care_hour_recipient_links', 0);
    }

    public function test_canonical_profile_already_linked_through_a_legacy_request_is_not_a_second_purchase_choice(): void
    {
        $owner = $this->owner();
        $account = $this->account($owner, 'Linda Bennett');
        $recipient = $this->recipient($owner, 'Linda Bennett');
        app(CareHourIdentityService::class)->linkRecipient($account, $recipient, $owner);
        $profile = $this->profile($owner, 'Linda Bennett');
        $recipient->update(['care_recipient_profile_id' => $profile->id]);
        $this->actingAs($owner)->get(route('family.care-hours.buy'))->assertRedirect(route('family.care-hours.packages', $account));
        $this->post(route('family.care-hours.buy.start'))->assertRedirect(route('family.care-hours.packages', $account));
        $this->assertDatabaseCount('care_hour_accounts', 1);
    }

    public function test_unlinked_legacy_recipient_uses_family_wallet_without_another_balance_decision(): void
    {
        $owner = $this->owner();
        $account = $this->account($owner, 'Linda Bennett');
        $recipient = $this->recipient($owner, 'Linda Bennett');
        $this->actingAs($owner)->post(route('family.care-hours.buy.start'), ['target' => 'recipient:'.$recipient->id])
            ->assertRedirect(route('family.care-hours.packages', $account));
        $this->assertDatabaseCount('care_hour_accounts', 1);
        $this->assertDatabaseCount('care_hour_recipient_links', 0);
        $this->post(route('family.care-hours.buy.start'), ['target' => 'recipient:'.$recipient->id, 'balance_target' => $account->id])
            ->assertRedirect(route('family.care-hours.packages', $account));
        $this->assertDatabaseCount('care_hour_recipient_links', 0);
        $this->assertDatabaseCount('care_hour_accounts', 1);
    }

    public function test_available_credit_uses_scoped_live_balances_without_merging_held_or_inactive_hours(): void
    {
        $owner = $this->owner();
        $first = $this->account($owner, 'Linda Bennett');
        $second = $this->account($owner, 'Robert Bennett');
        $this->buy($first, $owner, 5);
        $this->buy($second, $owner, 10);
        $this->actingAs($owner)->get(route('family.care-hours.index'))->assertOk()
            ->assertViewHas('availableMinutes', 960)->assertSee('Your family balance')->assertDontSee('Linda Bennett')->assertDontSee('Robert Bennett');
        $first->lots()->update(['status' => 'held']);
        $this->get(route('family.care-hours.index'))->assertOk()
            ->assertViewHas('availableMinutes', 660)->assertViewHas('heldMinutes', 300);
        $second->update(['active' => false]);
        $this->get(route('family.care-hours.index'))->assertOk()->assertViewHas('availableMinutes', 0);
        $this->assertDatabaseCount('care_hour_accounts', 2);
    }

    public function test_buy_without_a_recipient_creates_only_the_family_wallet(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post(route('family.care-hours.buy.start'))->assertRedirect();
        $account = CareHourAccount::sole();
        $this->get(route('family.care-hours.buy'))->assertRedirect(route('family.care-hours.packages', $account));
        $this->assertNull($account->care_recipient_profile_id);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->assertDatabaseCount('care_requests', 0);
        $this->assertDatabaseCount('care_hour_purchases', 0);
    }

    public function test_pilot_sales_and_enrollment_guards_remain_enforced(): void
    {
        $owner = $this->owner();
        $this->profile($owner, 'Linda Bennett');
        $other = User::factory()->create(['role' => 'family']);
        $this->actingAs($other)->get(route('family.care-hours.buy'))->assertForbidden();
        $this->post(route('family.care-hours.buy.start'))->assertForbidden();
        config(['care_hours.enrollment_enabled' => false]);
        $this->actingAs($owner)->post(route('family.care-hours.buy.start'))->assertRedirect(route('family.care-hours.buy'));
        $this->assertDatabaseCount('care_hour_accounts', 0);
        $account = $this->account($owner, 'Existing balance');
        $this->post(route('family.care-hours.buy.start'))->assertRedirect(route('family.care-hours.packages', $account));
        config(['care_hours.sales_enabled' => false]);
        $this->post(route('family.care-hours.buy.start'))->assertForbidden();
        $this->get(route('family.care-hours.buy'))->assertForbidden();
        $this->assertDatabaseCount('care_hour_purchases', 0);
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => 'family', 'email' => 'peverelli.t@gmail.com']);
    }

    private function profile(User $owner, string $name): \App\Models\CareRecipientProfile
    {
        return app(CareRecipientProfileService::class)->saveDraft($owner, null, ['full_name' => $name]);
    }

    private function account(User $owner, string $name): CareHourAccount
    {
        return CareHourAccount::create(['family_account_id' => app(FamilyAccountContext::class)->account($owner)->id, 'display_name' => $name]);
    }

    private function recipient(User $owner, string $name): CareRecipient
    {
        $request = CareRequest::create(['family_user_id' => $owner->id, 'family_account_id' => app(FamilyAccountContext::class)->account($owner)->id,
            'title' => 'Care for '.$name, 'status' => 'open', 'request_type' => 'one_time',
            'address_line1' => '123 Test St', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'requested_start_at' => now()->addDay(), 'requested_end_at' => now()->addDay()->addHours(2)]);

        return CareRecipient::create(['care_request_id' => $request->id, 'full_name' => $name, 'relationship_to_family' => 'Mother']);
    }

    private function buy(CareHourAccount $account, User $owner, int $hours): void
    {
        $service = app(CareHourPurchaseService::class);
        $quote = $service->quote($account, $hours);
        $service->refresh($service->start($account, $hours, $owner, $quote['quote_key'], (string) Str::uuid(), 'http://localhost/success/{PURCHASE_ID}', 'http://localhost/cancel/{PURCHASE_ID}'));
    }
}
