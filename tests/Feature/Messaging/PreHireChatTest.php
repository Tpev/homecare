<?php

namespace Tests\Feature\Messaging;

use App\Livewire\Caregiver\ApplyToCareRequest;
use App\Livewire\Family\ManageCareRequest;
use App\Livewire\Messaging\Inbox;
use App\Models\CaregiverProfile;
use App\Models\CareRequest;
use App\Models\CareRequestApplication;
use App\Models\CareRequestConversation;
use App\Models\CareRequestInvitation;
use App\Models\FamilyAccountMember;
use App\Models\Language;
use App\Models\MarketplaceNotificationDelivery;
use App\Models\Skill;
use App\Models\User;
use App\Services\Marketplace\CareRequestInvitationResponseService;
use App\Services\Messaging\CareRequestChatService;
use App\Services\Notifications\UnreadMessageEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PreHireChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['marketplace.prelaunch_enabled' => false, 'marketplace.ops_alert_recipients' => []]);
    }

    public static function activeStates(): array
    {
        return [['pending'], ['applied'], ['shortlisted'], ['hired']];
    }

    #[DataProvider('activeStates')]
    public function test_both_parties_can_open_and_message_without_changing_recruitment(string $state): void
    {
        [$family, $caregiver, $request] = $this->context();
        $record = $state === 'pending' ? $this->invite($request, $caregiver) : $this->apply($request, $caregiver, $state);
        if ($state === 'hired') {
            $request->update(['status' => 'filled']);
        }

        foreach ([$family, $caregiver] as $actor) {
            $this->actingAs($actor)->post($this->openUrl($request, $caregiver))->assertRedirect();
            $thread = CareRequestConversation::query()->firstOrFail();
            Livewire::actingAs($actor)->test(Inbox::class, ['conversation' => $thread->id])
                ->set('messageBody', 'Question from '.$actor->id)->call('sendMessage')->assertHasNoErrors();
        }

        $this->assertDatabaseCount('care_request_conversations', 1);
        $this->assertDatabaseCount('care_request_messages', 2);
        $this->assertSame($state, $record->fresh()->status);
        $this->assertNull($request->fresh()->first_shortlist_at);
        $this->assertNull($request->fresh()->first_hire_at);
        $this->assertDatabaseCount('care_bookings', 0);
        if ($state === 'pending') {
            $this->assertDatabaseCount('care_request_applications', 0);
            $this->assertNull($record->fresh()->responded_at);
        }
    }

    public function test_opening_existing_pending_invitation_chat_sends_no_notification(): void
    {
        [$family, $caregiver, $request] = $this->context();
        $this->invite($request, $caregiver);
        $before = MarketplaceNotificationDelivery::count();
        $this->actingAs($caregiver)->post($this->openUrl($request, $caregiver))->assertRedirect();
        $this->actingAs($family)->post($this->openUrl($request, $caregiver))->assertRedirect();
        $this->assertSame($before, MarketplaceNotificationDelivery::count());
        $this->assertDatabaseCount('care_request_messages', 0);
        $this->assertDatabaseCount('care_request_conversations', 1);
    }

    public function test_accepting_invitation_reuses_messages_and_attaches_application_without_marking_them_read(): void
    {
        [$family, $caregiver, $request] = $this->context();
        $invite = $this->invite($request, $caregiver);
        $thread = app(CareRequestChatService::class)->open($family, $request, $caregiver->id);
        Livewire::actingAs($family)->test(Inbox::class, ['conversation' => $thread->id])
            ->set('messageBody', 'Can you help with lunch?')->call('sendMessage');

        $result = app(CareRequestInvitationResponseService::class)->accept($invite, $caregiver);
        $this->assertTrue($result['ok']);
        $this->assertSame($thread->id, $result['conversation']->id);
        $this->assertNotNull($thread->fresh()->care_request_application_id);
        $this->assertNull($thread->fresh()->caregiver_last_read_at);
        $this->assertDatabaseCount('care_request_conversations', 1);
        $this->assertDatabaseCount('care_request_messages', 1);
        $this->assertTrue($thread->fresh()->canSendMessages($caregiver));
    }

    public function test_ordinary_application_and_later_hire_reuse_invitation_thread(): void
    {
        [$family, $caregiver, $request] = $this->context();
        $this->invite($request, $caregiver);
        $thread = app(CareRequestChatService::class)->open($family, $request, $caregiver->id);
        Livewire::actingAs($caregiver)->test(ApplyToCareRequest::class, ['careRequest' => $request->id])->call('submit')->assertHasNoErrors();
        $application = $request->applications()->firstOrFail();
        $this->assertSame($application->id, $thread->fresh()->care_request_application_id);
        $application->update(['status' => 'hired']);
        $request->update(['status' => 'filled']);
        $this->assertSame($thread->id, CareRequestConversation::findOrCreateForApplication($application, $family->id)->id);
        $this->assertTrue($thread->fresh()->canSendMessages($caregiver));
        $this->assertDatabaseCount('care_request_conversations', 1);
    }

    public static function endedStates(): array
    {
        return [
            ['invitation', 'declined'], ['invitation', 'expired'], ['invitation', 'cancelled'],
            ['invitation', 'elapsed'], ['application', 'rejected'], ['application', 'withdrawn'],
            ['application', 'not_selected'], ['request', 'cancelled'], ['request', 'filled'],
            ['request', 'expired'], ['request', 'elapsed'],
        ];
    }

    #[DataProvider('endedStates')]
    public function test_ended_recruitment_preserves_history_but_rejects_sending(string $kind, string $status): void
    {
        [$family, $caregiver, $request] = $this->context();
        $record = $kind === 'application' ? $this->apply($request, $caregiver) : $this->invite($request, $caregiver);
        $thread = app(CareRequestChatService::class)->open($family, $request, $caregiver->id);
        $thread->messages()->create(['sender_user_id' => $family->id, 'body' => 'Preserved history']);
        if ($kind === 'request') {
            $request->update($status === 'elapsed'
                ? ['requested_start_at' => now()->subHours(3), 'requested_end_at' => now()->subHour()]
                : ['status' => $status]);
        } else {
            $record->update($status === 'elapsed' ? ['expires_at' => now()->subMinute()] : ['status' => $status]);
        }

        foreach ([$family, $caregiver] as $actor) {
            $this->actingAs($actor)->post($this->openUrl($request, $caregiver))->assertRedirect(route('messages.show', $thread));
            Livewire::actingAs($actor)->test(Inbox::class, ['conversation' => $thread->id])
                ->assertSee('Preserved history')->assertDontSee('Chat is locked until')
                ->set('messageBody', 'Should not send')->call('sendMessage')->assertForbidden();
        }
        $this->assertDatabaseCount('care_request_messages', 1);
    }

    public function test_expired_invitation_does_not_override_an_active_application_and_reinvite_reuses_thread(): void
    {
        [$family, $caregiver, $request] = $this->context();
        $invitation = $this->invite($request, $caregiver);
        $thread = app(CareRequestChatService::class)->open($family, $request, $caregiver->id);
        $invitation->update(['status' => 'expired']);
        $this->assertFalse($thread->canSendMessages($family));
        $invitation->update(['status' => 'pending', 'expires_at' => now()->addDay()]);
        $this->assertSame($thread->id, app(CareRequestChatService::class)->open($caregiver, $request, $caregiver->id)->id);
        $invitation->update(['status' => 'expired']);
        $this->apply($request, $caregiver);
        $this->assertTrue($thread->canSendMessages($family));
    }

    public function test_unrelated_users_and_uninvited_caregivers_cannot_open_or_read_chat(): void
    {
        [$family, $caregiver, $request] = $this->context();
        $this->actingAs($caregiver)->post($this->openUrl($request, $caregiver))->assertForbidden();
        $this->actingAs($family)->post($this->openUrl($request, $caregiver))->assertForbidden();
        $this->invite($request, $caregiver);
        $thread = app(CareRequestChatService::class)->open($family, $request, $caregiver->id);
        $outsider = User::factory()->create(['role' => 'caregiver']);
        $this->actingAs($outsider)->post($this->openUrl($request, $caregiver))->assertForbidden();
        $this->actingAs($outsider)->get(route('messages.show', $thread))->assertNotFound();
        $otherFamily = User::factory()->create(['role' => 'family']);
        $this->actingAs($otherFamily)->post($this->openUrl($request, $caregiver))->assertNotFound();
        $this->actingAs($otherFamily)->get(route('messages.show', $thread))->assertNotFound();
        $this->assertDatabaseCount('care_request_conversations', 1);
    }

    public function test_family_members_share_chat_and_removed_members_lose_access(): void
    {
        [$family, $caregiver, $request] = $this->context();
        $this->invite($request, $caregiver);
        $member = User::factory()->create(['role' => 'family']);
        $membership = FamilyAccountMember::create([
            'family_account_id' => $request->family_account_id, 'user_id' => $member->id,
            'access_level' => 'member', 'status' => 'active', 'joined_at' => now(),
        ]);
        $this->actingAs($member)->post($this->openUrl($request, $caregiver))->assertRedirect();
        $thread = CareRequestConversation::firstOrFail();
        Livewire::actingAs($member)->test(Inbox::class, ['conversation' => $thread->id])
            ->set('messageBody', 'A question from our family')->call('sendMessage')->assertHasNoErrors();
        $this->assertTrue($thread->isParticipant($family));
        $membership->update(['status' => 'removed']);
        $this->actingAs($member->fresh())->get(route('messages.show', $thread))->assertNotFound();
    }

    public function test_message_buttons_exist_before_acceptance_and_shortlisting_copy_is_accurate(): void
    {
        [$family, $caregiver, $request] = $this->context();
        $this->invite($request, $caregiver);
        $this->actingAs($caregiver)->get(route('caregiver.invitations.index'))->assertOk()->assertSee('Message family');
        $this->actingAs($caregiver)->get(route('caregiver.work-inbox.index'))->assertOk()->assertSee($this->openUrl($request, $caregiver), false);
        Livewire::actingAs($family)->test(ManageCareRequest::class, ['careRequest' => $request->id])
            ->call('setCaregiverView', 'invited')->assertSee('Message caregiver');
        $this->apply($request, $caregiver, 'shortlisted');
        Livewire::actingAs($caregiver)->test(ApplyToCareRequest::class, ['careRequest' => $request->id])
            ->assertSee('The family shortlisted you.')->assertSee('Message family')
            ->assertDontSee('You accepted the invitation.')->assertDontSee('Answer only if they ask a question.');
    }

    public function test_pending_invitation_messages_keep_email_delay_and_read_suppression(): void
    {
        [$family, $caregiver, $request] = $this->context();
        $this->invite($request, $caregiver);
        $thread = app(CareRequestChatService::class)->open($family, $request, $caregiver->id);
        Livewire::actingAs($caregiver)->test(Inbox::class, ['conversation' => $thread->id])
            ->set('messageBody', 'Can you clarify the hours?')->call('sendMessage');
        $this->assertSame(0, app(UnreadMessageEmailService::class)->dispatchPending());
        $this->assertDatabaseHas('marketplace_notification_deliveries', ['user_id' => $family->id, 'channel' => 'email', 'status' => 'pending']);
        $this->assertSame(1, $family->unreadNotifications()->count());
        Livewire::actingAs($family)->test(Inbox::class, ['conversation' => $thread->id]);
        $this->travel(6)->minutes();
        $this->assertSame(0, app(UnreadMessageEmailService::class)->dispatchPending());
        $this->assertSame(0, $family->unreadNotifications()->count());
    }

    public function test_private_request_invitation_allows_chat_but_an_unrelated_request_does_not(): void
    {
        [$family, $caregiver, $request] = $this->context();
        $request->update(['is_private' => true]);
        $this->actingAs($caregiver)->post($this->openUrl($request, $caregiver))->assertForbidden();
        $this->invite($request, $caregiver);
        $this->actingAs($caregiver)->post($this->openUrl($request, $caregiver))->assertRedirect();
        $this->assertTrue(CareRequestConversation::firstOrFail()->canSendMessages($caregiver));
    }

    public function test_an_invitation_that_expires_while_chat_is_open_prevents_the_next_message(): void
    {
        [$family, $caregiver, $request] = $this->context();
        $invitation = $this->invite($request, $caregiver);
        $thread = app(CareRequestChatService::class)->open($family, $request, $caregiver->id);
        $component = Livewire::actingAs($caregiver)->test(Inbox::class, ['conversation' => $thread->id])
            ->set('messageBody', 'A stale open chat must not bypass expiry');
        $invitation->update(['expires_at' => now()->subMinute()]);
        $component->call('sendMessage')->assertForbidden();
        $this->assertDatabaseCount('care_request_messages', 0);
    }

    public function test_recurring_chat_remains_available_after_original_start_but_closes_at_end_date(): void
    {
        [$family, $caregiver, $request] = $this->context();
        $request->update(['request_type' => 'recurring', 'recurring_starts_on' => now()->subWeek(), 'recurring_ends_on' => null]);
        $this->invite($request, $caregiver);
        $thread = app(CareRequestChatService::class)->open($family, $request, $caregiver->id);
        $this->assertTrue($thread->canSendMessages($caregiver));
        $request->update(['recurring_ends_on' => now()->subDay()]);
        $this->assertFalse($thread->canSendMessages($caregiver));
        $this->apply($request, $caregiver, 'hired');
        $this->assertTrue($thread->canSendMessages($caregiver));
    }

    private function openUrl(CareRequest $request, User $caregiver): string
    {
        return route('messages.open', ['careRequest' => $request->id, 'caregiver' => $caregiver->id]);
    }

    private function invite(CareRequest $request, User $caregiver): CareRequestInvitation
    {
        return $request->invitations()->create([
            'family_user_id' => $request->family_user_id, 'caregiver_user_id' => $caregiver->id,
            'status' => 'pending', 'expires_at' => now()->addDays(2),
        ]);
    }

    private function apply(CareRequest $request, User $caregiver, string $status = 'applied'): CareRequestApplication
    {
        return $request->applications()->create(['caregiver_user_id' => $caregiver->id, 'status' => $status, 'proposed_rate' => 26]);
    }

    private function context(): array
    {
        $family = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $profile = CaregiverProfile::create([
            'user_id' => $caregiver->id, 'status' => 'active', 'slug' => 'chat-caregiver-'.$caregiver->id,
            'bio' => str_repeat('Experienced caregiver. ', 4), 'platform_hourly_rate' => 26,
            'years_experience' => 5, 'service_area_zip' => '27601', 'service_radius_miles' => 10,
            'insurance_status' => CaregiverProfile::INSURANCE_NO,
            'identity_verified_at' => now(), 'identity_verification_status' => 'approved',
        ]);
        $profile->availabilities()->create(['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '18:00']);
        $profile->skills()->attach(Skill::create(['name' => 'Companionship'])->id);
        $profile->languages()->attach(Language::create(['name' => 'English'])->id);
        $request = CareRequest::create([
            'family_user_id' => $family->id, 'title' => 'Pre-hire questions', 'status' => 'open', 'request_type' => 'one_time',
            'requested_start_at' => now()->addDays(3)->setTime(9, 0), 'requested_end_at' => now()->addDays(3)->setTime(18, 0),
            'address_line1' => '123 Test Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
        ]);

        return [$family, $caregiver, $request];
    }
}
