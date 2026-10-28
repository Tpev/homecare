<?php

namespace Tests\Feature\CareHours;

use App\Livewire\Family\CreateCareRequestWizard;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CareHourAccount;
use App\Models\CareHourPurchase;
use App\Models\CareHourRequestPreference;
use App\Models\CareRequest;
use App\Models\CareTask;
use App\Models\User;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourLedgerService;
use App\Services\CareRecipientProfiles\CareRecipientProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PrepaidRequestDefaultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['care_hours.enabled' => true, 'care_hours.enrollment_enabled' => true, 'services.stripe.bypass' => true]);
        Notification::fake();
        Mail::fake();
        Queue::fake();
    }

    public function test_posting_selects_recipient_hours_and_carries_choice_to_booking_without_spending(): void
    {
        [$owner, $profile, $account] = $this->recipient();
        $this->composer($owner)->call('selectCareRecipientProfile', $profile->id)
            ->assertSet('careHourAccountId', $account->id)->assertSee('Use care hours first')
            ->assertSee('Covered by your current care hours.')->call('publish')->assertHasNoErrors();
        $request = CareRequest::sole();
        $this->assertSame($account->id, CareHourRequestPreference::sole()->care_hour_account_id);
        Livewire::actingAs($owner)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertSet('careHourAccountId', $account->id);
        $this->assertSame(300, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_hour_allocations', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_explicit_card_choice_survives_rerender_publication_and_reload(): void
    {
        [$owner, $profile, $account] = $this->recipient();
        $this->composer($owner)->call('selectCareRecipientProfile', $profile->id)
            ->set('careHourAccountId', null)->set('is_private', true)->assertSet('careHourAccountId', null)
            ->call('publish')->assertHasNoErrors();
        $this->assertFalse(CareHourRequestPreference::sole()->enabled);
        $this->assertNull(CareHourRequestPreference::sole()->care_hour_account_id);
        foreach ([1, 2] as $reload) {
            Livewire::actingAs($owner)->test(ManageCareRequest::class, ['careRequest' => CareRequest::sole()->id])
                ->assertSet('careHourAccountId', null);
        }
        $this->assertSame(300, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_switching_recipients_recomputes_default_without_using_someone_elses_hours(): void
    {
        [$owner, $mum, $mumAccount] = $this->recipient();
        [, $dad, $dadAccount] = $this->recipient($owner, 'Robert');
        $this->composer($owner)->call('selectCareRecipientProfile', $mum->id)
            ->set('careHourAccountId', null)->call('selectCareRecipientProfile', $dad->id)
            ->assertSet('careHourAccountId', $dadAccount->id)->call('publish')->assertHasNoErrors();
        $this->assertSame($dadAccount->id, CareHourRequestPreference::sole()->care_hour_account_id);
        $this->assertSame($dad->id, CareRequest::sole()->recipient->care_recipient_profile_id);
        $this->assertSame(300, $mumAccount->balance()['available_minutes']);
    }

    public function test_partial_balance_is_default_and_estimate_shows_uncovered_amount(): void
    {
        [$owner, $profile, $account] = $this->recipient(minutes: 60);
        $this->composer($owner)->call('selectCareRecipientProfile', $profile->id)
            ->assertSet('careHourAccountId', $account->id)->assertSee('care hours + $31.00')
            ->assertSee('Posting is free.')->call('publish')->assertHasNoErrors();
        $this->assertSame(60, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_hour_booking_fundings', 0);
    }

    public static function unavailableBalances(): array
    {
        return ['empty' => ['empty'], 'held' => ['held'], 'inactive' => ['inactive']];
    }

    #[DataProvider('unavailableBalances')]
    public function test_unavailable_hours_keep_the_existing_request_flow(string $state): void
    {
        [$owner, $profile, $account] = $this->recipient(minutes: $state === 'empty' ? 0 : 300);
        if ($state === 'held') {
            $account->lots()->update(['status' => 'held']);
        } elseif ($state === 'inactive') {
            $account->update(['active' => false]);
        }
        $this->composer($owner)->call('selectCareRecipientProfile', $profile->id)
            ->assertSet('careHourAccountId', null)->assertDontSee('Use care hours first')
            ->call('publish')->assertHasNoErrors();
        $this->assertDatabaseCount('care_hour_request_preferences', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_foreign_account_cannot_be_injected_into_publication(): void
    {
        [$owner, $profile] = $this->recipient();
        [, , $foreign] = $this->recipient();
        $this->composer($owner)->call('selectCareRecipientProfile', $profile->id)
            ->set('careHourAccountId', $foreign->id)->call('publish')->assertHasErrors('careHourAccountId');
        $this->assertDatabaseCount('care_requests', 0);
        $this->assertDatabaseCount('care_hour_request_preferences', 0);
        $this->assertSame(300, $foreign->balance()['available_minutes']);
    }

    public function test_new_person_with_the_same_name_does_not_inherit_another_recipient_balance(): void
    {
        [$owner, , $account] = $this->recipient();
        $this->composer($owner)->set('recipient_full_name', 'Margaret')->set('recipient_relationship_to_family', 'Mother')
            ->assertSet('careHourAccountId', null)->assertDontSee('Use care hours first')->call('publish')->assertHasNoErrors();
        $this->assertDatabaseCount('care_hour_request_preferences', 0);
        $this->assertDatabaseCount('care_hour_recipient_links', 0);
        $this->assertSame(300, $account->balance()['available_minutes']);
    }

    public function test_absent_preference_migration_does_not_break_ordinary_card_requests(): void
    {
        Schema::drop('care_hour_request_preferences');
        $owner = User::factory()->create(['role' => 'family']);
        $this->composer($owner)->set('care_for', 'self')->assertSet('careHourAccountId', null)
            ->call('publish')->assertHasNoErrors();
        $this->assertDatabaseCount('care_requests', 1);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_publication_rechecks_self_identity_before_linking_the_balance(): void
    {
        [$owner, $profile, $account] = $this->recipient();
        $profile->update(['recipient_is_requester' => true]);
        $composer = $this->composer($owner)->set('care_for', 'self')->assertSet('careHourAccountId', $account->id);
        CareRequest::created(function () use ($profile): void {
            $profile->update(['recipient_is_requester' => false]);
        });
        $composer->call('publish')->assertHasErrors('careHourAccountId');
        $this->assertDatabaseCount('care_requests', 0);
        $this->assertDatabaseCount('care_hour_recipient_links', 0);
        $this->assertDatabaseCount('care_hour_request_preferences', 0);
        $this->assertSame(300, $account->balance()['available_minutes']);
    }

    public function test_card_opt_out_is_saved_even_if_the_balance_is_held_before_posting(): void
    {
        [$owner, $profile, $account] = $this->recipient();
        $composer = $this->composer($owner)->call('selectCareRecipientProfile', $profile->id)->set('careHourAccountId', null);
        $account->lots()->update(['status' => 'held']);
        $composer->call('publish')->assertHasNoErrors();
        $this->assertFalse(CareHourRequestPreference::sole()->enabled);
        $account->lots()->update(['status' => 'available']);
        Livewire::actingAs($owner)->test(ManageCareRequest::class, ['careRequest' => CareRequest::sole()->id])
            ->assertSet('careHourAccountId', null);
    }

    public function test_copying_a_request_reuses_its_explicit_recipient_identity_without_a_saved_profile(): void
    {
        [$owner, $source, $account] = $this->legacyRecipient();
        [, $differentProfile] = $this->recipient($owner, 'Robert');
        $this->composer($owner)->call('selectCareRecipientProfile', $differentProfile->id)
            ->call('prefillFromLastRequest')->assertSet('selected_care_recipient_profile_id', null)
            ->assertSet('careHourAccountId', $account->id)->assertSee('Use care hours first')
            ->set('requested_start_date', now()->addDays(2)->toDateString())->set('requested_start_time', '10:00')
            ->call('publish')->assertHasNoErrors();
        $request = CareRequest::where('id', '!=', $source->id)->sole();
        $this->assertSame($account->id, CareHourRequestPreference::sole()->care_hour_account_id);
        $this->assertNull($request->recipient->care_recipient_profile_id);
        $this->assertDatabaseHas('care_hour_recipient_links', ['care_hour_account_id' => $account->id,
            'care_recipient_id' => $request->recipient->id]);
        $this->assertSame(300, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_bookings', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_editing_a_copied_recipient_drops_the_copied_balance(): void
    {
        [$owner, $source, $account] = $this->legacyRecipient();
        $this->composer($owner)->call('prefillFromLastRequest')->assertSet('careHourAccountId', $account->id)
            ->set('recipient_full_name', 'Robert')->assertSet('careHourAccountId', null)
            ->assertSet('careHoursCopiedRequestId', null)->assertDontSee('Use care hours first')
            ->set('requested_start_date', now()->addDays(2)->toDateString())->set('requested_start_time', '10:00')
            ->call('publish')->assertHasNoErrors();
        $request = CareRequest::where('id', '!=', $source->id)->sole();
        $this->assertDatabaseMissing('care_hour_recipient_links', ['care_recipient_id' => $request->recipient->id]);
        $this->assertDatabaseCount('care_hour_request_preferences', 0);
        $this->assertSame(300, $account->balance()['available_minutes']);
    }

    public function test_copy_action_cannot_load_another_familys_request_or_hours(): void
    {
        [, $source] = $this->legacyRecipient();
        $owner = User::factory()->create(['role' => 'family']);
        $this->composer($owner)->set('lastRequestId', $source->id)->call('prefillFromLastRequest')
            ->assertSet('prefillApplied', false)->assertSet('careHoursCopiedRequestId', null)
            ->assertSet('careHourAccountId', null)->assertDontSee('Use care hours first');
    }

    private function legacyRecipient(): array
    {
        $owner = User::factory()->create(['role' => 'family']);
        $this->composer($owner)->set('recipient_full_name', 'Margaret')->set('recipient_relationship_to_family', 'Mother')
            ->call('publish')->assertHasNoErrors();
        $request = CareRequest::sole();
        $account = app(CareHourIdentityService::class)->enroll($request->recipient, $owner);
        $this->credit($account, $owner, 300);

        return [$owner, $request, $account];
    }

    private function composer(User $owner)
    {
        $task = CareTask::firstOrCreate(['name' => 'Companionship']);

        return Livewire::actingAs($owner)->test(CreateCareRequestWizard::class)
            ->set('selectedTasks', [$task->id])->set('requested_start_date', now()->addDay()->toDateString())
            ->set('requested_start_time', '09:00')->set('requested_duration_minutes', '120')
            ->set('address_line1', '100 Fictional St')->set('city', 'Raleigh')->set('state', 'NC')->set('zip', '27601');
    }

    private function recipient(?User $owner = null, string $name = 'Margaret', int $minutes = 300): array
    {
        $owner ??= User::factory()->create(['role' => 'family']);
        $profiles = app(CareRecipientProfileService::class);
        $data = ['full_name' => $name, 'preferred_name' => $name, 'recipient_is_requester' => false,
            'relationship_to_family' => 'Parent', 'about_them' => 'Enjoys gardening and quiet conversation.'];
        $profile = $profiles->saveDraft($owner, null, $data);
        $profile = $profiles->makeReady($owner, $profile, $data, $profile->revision, true);
        $account = app(CareHourIdentityService::class)->enrollProfile($profile, $owner);
        $this->credit($account, $owner, $minutes);

        return [$owner, $profile, $account];
    }

    private function credit(CareHourAccount $account, User $owner, int $minutes): void
    {
        if ($minutes > 0) {
            $purchase = CareHourPurchase::create(['public_id' => (string) Str::uuid(), 'request_key' => (string) Str::uuid(),
                'care_hour_account_id' => $account->id, 'family_account_id' => $account->family_account_id,
                'buyer_user_id' => $owner->id, 'buyer_email' => $owner->email, 'buyer_name' => $owner->name,
                'paid_minutes' => $minutes, 'bonus_minutes' => 0, 'total_minutes' => $minutes, 'amount_cents' => $minutes * 3100 / 60,
                'currency' => 'usd', 'status' => 'paid', 'processing_fee_cents' => 0, 'terms_version' => 'test', 'quote' => [],
                'terms_accepted_at' => now(), 'success_url' => '/care-hours', 'cancel_url' => '/care-hours']);
            app(CareHourLedgerService::class)->issuePurchase($purchase);
        }

    }
}
