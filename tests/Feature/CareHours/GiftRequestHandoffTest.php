<?php

namespace Tests\Feature\CareHours;

use App\Livewire\Family\CreateCareRequestWizard;
use App\Livewire\Family\ManageCareRequest;
use App\Models\CareBooking;
use App\Models\CareHourAccount;
use App\Models\CareRecipientProfile;
use App\Models\CareRequest;
use App\Models\CareTask;
use App\Models\FamilyAccountMember;
use App\Models\FamilyOnboarding;
use App\Models\User;
use App\Notifications\CareHourVerificationNotification;
use App\Services\CareHours\CareHourGiftService;
use App\Services\CareHours\CareHourIdentityService;
use App\Services\CareHours\CareHourPurchaseService;
use App\Services\CareRecipientProfiles\CareRecipientProfilePresenter;
use App\Services\CareRecipientProfiles\CareRecipientProfileService;
use App\Support\CareHourRequestHandoff;
use App\Support\FamilyQuickRequestDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class GiftRequestHandoffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['care_hours.enabled' => true, 'care_hours.sales_enabled' => true, 'care_hours.enrollment_enabled' => true,
            'care_hours.gifts_enabled' => true, 'care_hours.welcome_enabled' => true, 'services.stripe.bypass' => true]);
        Notification::fake();
        Mail::fake();
        Queue::fake();
    }

    public function test_claim_find_care_and_publish_without_optional_profile_keeps_gift_identity_private_and_available(): void
    {
        [$actor, $account, $purchase] = $this->claimedGift();
        $url = route('family.requests.create');
        $this->actingAs($actor)->get(route('family.care-hours.claim.ready', $purchase->public_id))->assertOk()->assertSee($url, false);
        $this->get(route('family.care-hours.show', $account))->assertOk()->assertSee($url, false);
        $this->get($url)->assertOk();
        $this->composer($actor)->set('care_for', 'self')
            ->assertSet('createQuickCareProfile', false)->assertSet('selected_care_recipient_profile_id', null)
            ->call('publish')->assertHasNoErrors();

        $request = CareRequest::sole();
        $this->assertNull($request->recipient->care_recipient_profile_id);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->assertDatabaseCount('care_recipient_profile_versions', 0);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $this->assertNull(app(CareRecipientProfilePresenter::class)->forCareRequest($caregiver, $request));
        $this->assertSame($account->id, $this->bookingAccount($request)?->id);
        Livewire::actingAs($actor)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->assertViewHas('careHourAccounts', fn ($accounts) => count($accounts) === 1 && $accounts[0]['id'] === $account->id);
        $this->assertSame(660, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_hour_accounts', 1);
        $this->assertDatabaseCount('care_booking_payments', 0);
        $this->assertNull($actor->fresh()->stripe_customer_id);
    }

    public function test_optional_profile_requires_existing_sharing_consent_and_does_not_own_the_gift_balance(): void
    {
        [$actor, $account] = $this->claimedGift();
        $composer = $this->composer($actor)->set('care_for', 'self')->set('createQuickCareProfile', true)
            ->set('quick_profile_about', 'I enjoy a walk and a friendly conversation.')
            ->set('quick_profile_good_visit', 'Please explain the plan when you arrive.')
            ->call('publish')->assertHasErrors('quick_profile_sharing_acknowledged');
        $this->assertDatabaseCount('care_requests', 0);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
        $this->assertDatabaseCount('care_recipient_profile_versions', 0);
        $composer->set('quick_profile_sharing_acknowledged', true)->call('publish')->assertHasNoErrors();

        $profile = CareRecipientProfile::sole();
        $request = CareRequest::sole();
        $this->assertNull($account->care_recipient_profile_id);
        $this->assertTrue($profile->isReady());
        $this->assertNotNull($profile->sharing_acknowledged_at);
        $this->assertSame($profile->latest_ready_version_id, $request->recipient->care_recipient_profile_version_id);
        $this->assertSame($account->id, $this->bookingAccount($request)?->id);
        $this->assertDatabaseCount('care_recipient_profile_versions', 1);
        $this->assertSame(660, $account->balance()['available_minutes']);
    }

    public function test_gift_hours_are_available_for_another_person_in_the_family(): void
    {
        [$actor, $account] = $this->claimedGift();
        $this->composer($actor)->set('care_for', 'other')
            ->assertSet('careHoursHandoffAccountId', null)->assertSet('careHoursHandoffProfileId', null)
            ->set('recipient_full_name', 'Another person')->set('recipient_relationship_to_family', 'Friend')
            ->call('publish')->assertHasNoErrors();
        $request = CareRequest::sole();
        $this->assertNull($request->recipient->care_recipient_profile_id);
        $this->assertSame($account->id, $this->bookingAccount($request)?->id);
        $this->assertSame(660, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_recipient_profiles', 0);
    }

    public function test_selecting_an_existing_profile_uses_the_shared_family_gift_balance(): void
    {
        [$actor, $account] = $this->claimedGift();
        $profiles = app(CareRecipientProfileService::class);
        $data = ['full_name' => 'Mum', 'preferred_name' => 'Mum', 'recipient_is_requester' => false,
            'relationship_to_family' => 'Mother', 'about_them' => 'She enjoys gardening.'];
        $other = $profiles->saveDraft($actor, null, $data);
        $other = $profiles->makeReady($actor, $other, $data, $other->revision, true);
        $this->composer($actor)->call('selectCareRecipientProfile', $other->id)
            ->assertSet('careHoursHandoffAccountId', null)->call('publish')->assertHasNoErrors();
        $request = CareRequest::sole();
        $this->assertSame($other->id, $request->recipient->care_recipient_profile_id);
        $this->assertSame($account->id, $this->bookingAccount($request)?->id);
        $this->assertSame(660, $account->balance()['available_minutes']);
    }

    public function test_another_family_and_same_family_member_cannot_use_someone_elses_self_handoff(): void
    {
        [$actor, $account] = $this->legacyClaimedGift();
        $outsider = User::factory()->create(['role' => 'family', 'name' => $actor->name]);
        $member = User::factory()->create(['role' => 'family', 'name' => $actor->name]);
        FamilyAccountMember::create(['family_account_id' => $account->family_account_id, 'user_id' => $member->id,
            'status' => 'active', 'access_level' => 'member', 'joined_at' => now()]);
        foreach ([$outsider, $member] as $other) {
            $this->actingAs($other)->get(route('family.requests.create', ['care_hours_account' => $account->id]))->assertNotFound();
            if ($other->is($member)) {
                $this->assertSame(route('family.requests.create'), CareHourRequestHandoff::url($account, $other));
            } else {
                try {
                    CareHourRequestHandoff::url($account, $other);
                    $this->fail('Foreign family wallet access must be rejected.');
                } catch (\Illuminate\Auth\Access\AuthorizationException) {
                    $this->assertTrue(true);
                }
            }
        }
        $this->assertDatabaseCount('care_requests', 0);
        $this->assertDatabaseCount('care_recipient_profiles', 1);
    }

    public function test_publish_revalidates_changed_profile_and_inactive_account_without_creating_requests(): void
    {
        [$actor, $account] = $this->legacyClaimedGift();
        $composer = $this->composer($actor, $account);
        $account->profile->increment('revision');
        $composer->call('publish')->assertHasErrors('profile');
        $this->assertDatabaseCount('care_requests', 0);
        $composer = $this->composer($actor, $account);
        $account->update(['active' => false]);
        $composer->call('publish')->assertHasErrors('profile');
        $this->actingAs($actor)->get(route('family.requests.create', ['care_hours_account' => $account->id]))->assertNotFound();
        $this->assertDatabaseCount('care_requests', 0);
        $this->assertDatabaseCount('care_recipient_profiles', 1);
    }

    public function test_choosing_myself_defaults_to_my_gift_hours_without_publishing_my_private_profile(): void
    {
        [$actor, $account] = $this->claimedGift();
        $this->composer($actor)->assertSet('careHoursHandoffAccountId', null)->assertSet('care_for', 'other')
            ->set('care_for', 'self')->assertSet('careHourAccountId', $account->id)->call('publish')->assertHasNoErrors();
        $request = CareRequest::sole();
        $this->assertNull($request->recipient->care_recipient_profile_id);
        $this->assertSame($account->id, $this->bookingAccount($request)?->id);
        $this->assertSame(660, $account->balance()['available_minutes']);
        $this->assertDatabaseCount('care_hour_booking_fundings', 0);
        $this->assertDatabaseCount('care_booking_payments', 0);
    }

    public function test_archived_or_changed_self_identity_is_rejected_at_publication_and_handoff_cannot_be_tampered_with(): void
    {
        [$actor, $account] = $this->legacyClaimedGift();
        $composer = $this->composer($actor, $account);
        $account->profile->update(['recipient_is_requester' => false]);
        $composer->call('publish')->assertHasErrors('profile');
        $account->profile->update(['recipient_is_requester' => true, 'status' => CareRecipientProfile::STATUS_ARCHIVED]);
        $composer->call('publish')->assertHasErrors('profile');
        $this->assertDatabaseCount('care_requests', 0);
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        $composer->set('careHoursHandoffAccountId', $account->id + 1);
    }

    public function test_explicit_self_handoff_does_not_load_or_consume_another_persons_saved_request(): void
    {
        [$actor, $account] = $this->legacyClaimedGift();
        $context = ['wizard_state' => ['recipient_date_of_birth' => '1940-01-01', 'recipient_gender' => 'female',
            'recipient_mobility_level' => 'wheelchair_user', 'recipient_care_notes' => 'Another person private notes',
            'includeThirdPartyContact' => true, 'third_party_full_name' => 'Another contact']];
        $record = FamilyOnboarding::create(['family_account_id' => $account->family_account_id,
            'initiated_by_user_id' => $actor->id, 'status' => 'completed', 'draft' => [],
            'request_context' => $context, 'submitted_snapshot' => ['care_for' => 'family', 'recipient_name' => 'Another person',
                'relationship' => 'Mother', 'care_notes' => 'Private notes', 'care_preferences' => 'Private preferences']]);
        $quickDraft = ['care_for' => 'other', 'recipient_full_name' => 'Another person', 'additional_info' => 'Another draft'];
        FamilyQuickRequestDraft::put($quickDraft);

        $composer = $this->composer($actor, $account)->assertSet('care_for', 'self')
            ->assertSet('recipient_full_name', $actor->name)->assertSet('recipient_date_of_birth', '')
            ->assertSet('recipient_gender', '')->assertSet('recipient_mobility_level', '')
            ->assertSet('recipient_care_notes', '')->assertSet('includeThirdPartyContact', false)
            ->assertSet('third_party_full_name', '')->assertSet('additional_info', '')
            ->assertSet('onboardingHandoffRevision', null)->assertSet('startedFromCareHours', true);
        $composer->set('care_for', 'other')->set('recipient_full_name', 'Chosen person')
            ->set('recipient_relationship_to_family', 'Friend')->call('publish')->assertHasNoErrors();
        $this->assertNull($record->fresh()->request_handoff_completed_at);
        $this->assertSame($context, $record->fresh()->request_context);
        $this->assertSame($quickDraft, FamilyQuickRequestDraft::get());
        $this->assertNull(CareRequest::sole()->recipient->care_recipient_profile_id);
    }

    private function composer(User $actor, ?CareHourAccount $account = null)
    {
        $task = CareTask::firstOrCreate(['name' => 'Handoff companionship']);

        return Livewire::actingAs($actor)->withQueryParams($account ? ['care_hours_account' => $account->id] : [])
            ->test(CreateCareRequestWizard::class)
            ->set('selectedTasks', [$task->id])->set('requested_start_date', now()->addDay()->toDateString())
            ->set('requested_start_time', '09:00')->set('requested_duration_minutes', '120')
            ->set('address_line1', '100 Fictional St')->set('city', 'Raleigh')->set('state', 'NC')->set('zip', '27601');
    }

    private function bookingAccount(CareRequest $request): ?CareHourAccount
    {
        $booking = new CareBooking(['family_account_id' => $request->family_account_id]);
        $booking->setRelation('careRequest', $request->fresh('recipient'));

        return app(CareHourIdentityService::class)->forBooking($booking);
    }

    private function legacyClaimedGift(): array
    {
        [$actor, $account, $purchase] = $this->claimedGift();
        $profile = app(CareRecipientProfileService::class)->saveDraft($actor, null, [
            'full_name' => $actor->name, 'recipient_is_requester' => true,
        ]);
        // Historical self-claim URLs must retain their original privacy guards.
        $account->update(['care_recipient_profile_id' => $profile->id]);

        return [$actor, $account->fresh(), $purchase];
    }

    private function claimedGift(): array
    {
        $gifts = app(CareHourGiftService::class);
        $verification = $gifts->requestVerification(Str::uuid().'@example.test', 'Fictional Buyer');
        $otp = '';
        Notification::assertSentOnDemand(CareHourVerificationNotification::class, function ($notice) use (&$otp): bool {
            $otp = $notice->code;

            return true;
        });
        $token = $gifts->verify($verification->id, $otp);
        $purchase = $gifts->start($token, 10, $gifts->quote(10)['quote_key'], (string) Str::uuid(), url('/ok'), url('/cancel'));
        $purchase = app(CareHourPurchaseService::class)->refresh($purchase);
        $code = $gifts->codeForBuyer($purchase, null, $token);
        $actor = User::factory()->create(['role' => 'family', 'name' => 'Gift Recipient']);
        $account = $gifts->resolveSelfClaimAccount($code, $actor);
        $quote = $gifts->previewForClaim($code, $account, $actor);
        $purchase = $gifts->claim($code, $account, $actor, $quote['quote_key']);
        // Request entry must work without a saved card and without the provider bypass masking gates.
        config(['services.stripe.bypass' => false]);

        return [$actor, $account, $purchase];
    }
}
