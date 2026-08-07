<?php

namespace Tests\Feature;

use App\Livewire\Auth\FamilyInvitationRegister;
use App\Livewire\Family\FamilyAccess;
use App\Mail\FamilyAccountInvitationMail;
use App\Models\CareRequest;
use App\Models\CareRequestConversation;
use App\Models\FamilyAccountInvitation;
use App\Models\FamilyAccountMember;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\FamilyAccounts\FamilyAccountAccessService;
use App\Services\FamilyAccounts\FamilyAccountBackfill;
use App\Services\FamilyAccounts\FamilyAccountContext;
use App\Services\FamilyAccounts\FamilyAccountInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FamilyAccountAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        RateLimiter::clear('family-invite:ip:'.hash('sha256', '127.0.0.1'));
    }

    public function test_backfill_is_idempotent_and_preserves_legacy_owner(): void
    {
        $owner = User::factory()->create(['role' => 'family', 'stripe_customer_id' => 'cus_family_test']);
        $request = CareRequest::withoutEvents(fn () => CareRequest::query()->create([
            'family_user_id' => $owner->id,
            'family_account_id' => null,
            'title' => 'Morning care',
            'status' => CareRequest::STATUS_OPEN,
            'address_line1' => '100 Fiesta Drive',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27603',
        ]));
        $caregiver = User::factory()->create(['role' => 'caregiver']);
        $legacyReadAt = now()->subHour()->startOfSecond();
        $conversation = CareRequestConversation::withoutEvents(fn () => CareRequestConversation::query()->create([
            'care_request_id' => $request->id,
            'family_account_id' => null,
            'family_user_id' => $owner->id,
            'caregiver_user_id' => $caregiver->id,
            'started_by_user_id' => $owner->id,
            'family_last_read_at' => $legacyReadAt,
        ]));
        $ticket = SupportTicket::withoutEvents(fn () => SupportTicket::query()->create([
            'family_account_id' => null,
            'family_visibility' => null,
            'opener_user_id' => $owner->id,
            'category' => 'general',
            'subject' => 'Existing support conversation',
            'description' => 'Keep its prior read state during migration.',
            'opener_last_read_at' => $legacyReadAt,
        ]));

        $backfill = app(FamilyAccountBackfill::class);
        $first = $backfill->run();
        $newerReadAt = now()->startOfSecond();
        \DB::table('family_conversation_reads')
            ->where('care_request_conversation_id', $conversation->id)
            ->where('user_id', $owner->id)
            ->update(['last_read_at' => $newerReadAt]);
        \DB::table('family_support_ticket_reads')
            ->where('support_ticket_id', $ticket->id)
            ->where('user_id', $owner->id)
            ->update(['last_read_at' => $newerReadAt]);
        $second = $backfill->run();

        $account = $owner->fresh()->ownedFamilyAccount;
        $this->assertNotNull($account);
        $this->assertSame('cus_family_test', $account->stripe_customer_id);
        $this->assertSame($account->id, $request->fresh()->family_account_id);
        $this->assertSame($owner->id, $request->fresh()->family_user_id);
        $this->assertSame(1, $account->memberships()->count());
        $this->assertSame(1, $first['users']);
        $this->assertSame(1, $second['users']);
        $this->assertSame(0, $second['records']);
        $this->assertDatabaseHas('family_conversation_reads', [
            'care_request_conversation_id' => $conversation->id,
            'user_id' => $owner->id,
            'last_read_at' => $newerReadAt,
        ]);
        $this->assertDatabaseHas('family_support_ticket_reads', [
            'support_ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'last_read_at' => $newerReadAt,
        ]);
        $this->assertSame(0, Artisan::call('homecare:verify-family-accounts'));
    }

    public function test_owner_can_send_a_hashed_private_invitation(): void
    {
        $owner = User::factory()->create(['role' => 'family', 'name' => 'Charles Martin']);
        $issued = app(FamilyAccountInvitationService::class)->send($owner, ' Sarah@Example.com ', '127.0.0.1');

        $invitation = $issued['invitation'];
        $this->assertSame('sarah@example.com', $invitation->email_normalized);
        $this->assertSame(hash('sha256', $issued['token']), $invitation->token_hash);
        $this->assertNotSame($issued['token'], $invitation->token_hash);
        $this->assertSame(64, strlen($issued['token']));
        $this->assertTrue($invitation->expires_at->between(now()->addDays(6), now()->addDays(8)));

        Mail::assertSent(FamilyAccountInvitationMail::class, function (FamilyAccountInvitationMail $mail): bool {
            $rendered = $mail->render();

            return str_contains($rendered, 'Charles')
                && ! str_contains($rendered, 'diagnosis')
                && ! str_contains($rendered, 'care address');
        });
    }

    public function test_verifier_rejects_unmapped_family_support_records(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        app(FamilyAccountContext::class)->account($owner);
        SupportTicket::withoutEvents(fn () => SupportTicket::query()->create([
            'family_account_id' => null,
            'family_visibility' => null,
            'opener_user_id' => $owner->id,
            'category' => 'general',
            'subject' => 'Unmapped family support record',
            'description' => 'This fixture must be rejected by deployment verification.',
        ]));

        $this->assertSame(1, Artisan::call('homecare:verify-family-accounts'));
        $this->assertStringContainsString('family-care records without a Family Account', Artisan::output());
    }

    public function test_resend_invalidates_the_old_token_and_cancel_invalidates_the_new_token(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $service = app(FamilyAccountInvitationService::class);
        $first = $service->send($owner, 'member@example.com', '127.0.0.1');
        $second = $service->resend($owner, $first['invitation'], '127.0.0.1');

        $this->assertNull($service->findByToken($first['token']));
        $this->assertTrue($service->findByToken($second['token'])->isUsable());

        $service->cancel($owner, $second['invitation']);
        $service->cancel($owner, $second['invitation']);
        $this->assertNull($service->findByToken($second['token']));
        $this->assertDatabaseCount('family_account_activity_logs', 4);
    }

    public function test_eligible_existing_family_user_can_join_and_actions_are_audited(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $member = User::factory()->create(['role' => 'family', 'email' => 'member@example.com']);
        $originalAccount = app(\App\Services\FamilyAccounts\FamilyAccountProvisioner::class)
            ->provisionOwner($member, 'test_existing_account');
        $service = app(FamilyAccountInvitationService::class);
        $issued = $service->send($owner, $member->email, '127.0.0.1');

        $membership = $service->accept($member, $issued['token']);

        $this->assertSame(FamilyAccountMember::ACCESS_MEMBER, $membership->access_level);
        $this->assertSame($owner->ownedFamilyAccount->id, $membership->family_account_id);
        $this->assertSame(\App\Models\FamilyAccount::STATUS_CLOSED, $originalAccount->fresh()->status);
        $this->assertSame(FamilyAccountMember::STATUS_LEFT, $originalAccount->memberships()->firstOrFail()->status);
        $this->assertTrue(app(FamilyAccountContext::class)->canAccessAccount($member, $owner->ownedFamilyAccount));
        $this->assertDatabaseHas('family_account_activity_logs', [
            'family_account_id' => $membership->family_account_id,
            'actor_user_id' => $member->id,
            'action' => 'invitation_accepted',
            'subject_user_id' => $member->id,
        ]);
    }

    public function test_caregiver_cannot_accept_a_family_invitation(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $caregiver = User::factory()->create(['role' => 'caregiver', 'email' => 'caregiver@example.com']);
        $issued = app(FamilyAccountInvitationService::class)->send($owner, $caregiver->email, '127.0.0.1');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(FamilyAccountInvitationService::class)->accept($caregiver, $issued['token']);
    }

    public function test_new_user_can_create_a_verified_login_from_the_invitation(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $issued = app(FamilyAccountInvitationService::class)->send($owner, 'new.member@example.com', '127.0.0.1');

        Livewire::test(FamilyInvitationRegister::class, ['token' => $issued['token']])
            ->set('name', 'Sarah Martin')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->set('accept_terms', true)
            ->call('register')
            ->assertRedirect(route('family.invitations.review', ['token' => $issued['token']], absolute: false));

        $user = User::query()->where('email', 'new.member@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_removed_member_is_denied_on_the_next_request_and_history_remains(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $member = User::factory()->create(['role' => 'family', 'email' => 'member@example.com']);
        $issued = app(FamilyAccountInvitationService::class)->send($owner, $member->email, '127.0.0.1');
        $membership = app(FamilyAccountInvitationService::class)->accept($member, $issued['token']);

        $this->actingAs($member)->get(route('family.access'))->assertOk();
        $openFamilyAccessPage = Livewire::actingAs($member)->test(FamilyAccess::class);
        app(FamilyAccountAccessService::class)->remove($owner, $membership);

        $this->actingAs($member)->get(route('family.access'))->assertForbidden();
        $openFamilyAccessPage->call('leaveAccount')->assertForbidden();
        $this->assertDatabaseHas('family_account_members', [
            'id' => $membership->id,
            'status' => FamilyAccountMember::STATUS_REMOVED,
            'ended_by_user_id' => $owner->id,
        ]);
        $this->assertDatabaseHas('family_account_activity_logs', [
            'family_account_id' => $membership->family_account_id,
            'action' => 'member_removed',
            'subject_user_id' => $member->id,
        ]);
    }

    public function test_member_cannot_use_owner_invitation_actions(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $member = User::factory()->create(['role' => 'family', 'email' => 'member@example.com']);
        $issued = app(FamilyAccountInvitationService::class)->send($owner, $member->email, '127.0.0.1');
        app(FamilyAccountInvitationService::class)->accept($member, $issued['token']);

        Livewire::actingAs($member)
            ->test(FamilyAccess::class)
            ->set('inviteEmail', 'another@example.com')
            ->call('sendInvitation')
            ->assertHasErrors('email');
    }

    public function test_livewire_ids_cannot_manage_another_family_account(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        app(FamilyAccountContext::class)->account($owner);
        $otherOwner = User::factory()->create(['role' => 'family', 'email' => 'other.owner@example.com']);
        $otherMember = User::factory()->create(['role' => 'family', 'email' => 'other.member@example.com']);
        $service = app(FamilyAccountInvitationService::class);
        $membership = $service->accept(
            $otherMember,
            $service->send($otherOwner, $otherMember->email, '127.0.0.2')['token'],
        );
        $pending = $service->send($otherOwner, 'other.pending@example.com', '127.0.0.2')['invitation'];

        try {
            Livewire::actingAs($owner)
                ->test(FamilyAccess::class)
                ->call('cancelInvitation', $pending->id);
            $this->fail('A cross-account invitation ID was accepted.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            $this->assertSame(FamilyAccountInvitation::class, $exception->getModel());
        }

        try {
            Livewire::actingAs($owner)
                ->test(FamilyAccess::class)
                ->set('removingMemberId', $membership->id)
                ->call('removeAccess');
            $this->fail('A cross-account membership ID was accepted.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            $this->assertSame(FamilyAccountMember::class, $exception->getModel());
        }
    }

    public function test_expired_and_used_invitation_tokens_cannot_be_accepted_again(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $service = app(FamilyAccountInvitationService::class);
        $expired = $service->send($owner, 'expired@example.com', '127.0.0.1');
        $expired['invitation']->forceFill(['expires_at' => now()->subMinute()])->save();

        try {
            $service->accept(User::factory()->create(['role' => 'family', 'email' => 'expired@example.com']), $expired['token']);
            $this->fail('An expired invitation was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('expired', $exception->errors()['invitation'][0]);
        }

        $issued = $service->send($owner, 'single.use@example.com', '127.0.0.1');
        $member = User::factory()->create(['role' => 'family', 'email' => 'single.use@example.com']);
        $service->accept($member, $issued['token']);

        $this->expectException(ValidationException::class);
        $service->accept($member, $issued['token']);
    }

    public function test_mismatched_email_and_nonempty_existing_family_account_are_ineligible(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $service = app(FamilyAccountInvitationService::class);
        $mismatch = $service->send($owner, 'right@example.com', '127.0.0.1');

        try {
            $service->accept(User::factory()->create(['role' => 'family', 'email' => 'wrong@example.com']), $mismatch['token']);
            $this->fail('A mismatched email accepted an invitation.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('email address that received', $exception->errors()['invitation'][0]);
        }

        $existingOwner = User::factory()->create(['role' => 'family', 'email' => 'established@example.com']);
        $existingAccount = app(\App\Services\FamilyAccounts\FamilyAccountProvisioner::class)
            ->provisionOwner($existingOwner, 'test_established_account');
        CareRequest::query()->create([
            'family_account_id' => $existingAccount->id,
            'family_user_id' => $existingOwner->id,
            'title' => 'Existing care history',
            'status' => CareRequest::STATUS_OPEN,
            'address_line1' => '100 Existing Lane',
            'city' => 'Raleigh',
            'state' => 'NC',
            'zip' => '27601',
        ]);
        $establishedInvite = $service->send($owner, $existingOwner->email, '127.0.0.1');

        $this->expectException(ValidationException::class);
        $service->accept($existingOwner, $establishedInvite['token']);
    }

    public function test_member_can_leave_immediately_but_owner_cannot_leave(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $member = User::factory()->create(['role' => 'family', 'email' => 'leaving@example.com']);
        $issued = app(FamilyAccountInvitationService::class)->send($owner, $member->email, '127.0.0.1');
        $membership = app(FamilyAccountInvitationService::class)->accept($member, $issued['token']);
        $access = app(FamilyAccountAccessService::class);

        $access->leave($member);
        $this->assertSame(FamilyAccountMember::STATUS_LEFT, $membership->fresh()->status);
        $this->actingAs($member)->get(route('family.access'))->assertForbidden();

        $this->expectException(ValidationException::class);
        $access->leave($owner);
    }

    public function test_backfill_rerun_does_not_restore_removed_users_or_promote_members(): void
    {
        $owner = User::factory()->create(['role' => 'family']);
        $member = User::factory()->create(['role' => 'family', 'email' => 'active.member@example.com']);
        $removed = User::factory()->create(['role' => 'family', 'email' => 'removed.member@example.com']);
        $service = app(FamilyAccountInvitationService::class);
        $activeMembership = $service->accept($member, $service->send($owner, $member->email, '127.0.0.1')['token']);
        $removedMembership = $service->accept($removed, $service->send($owner, $removed->email, '127.0.0.1')['token']);
        app(FamilyAccountAccessService::class)->remove($owner, $removedMembership);

        app(FamilyAccountBackfill::class)->run();

        $this->assertSame(FamilyAccountMember::ACCESS_MEMBER, $activeMembership->fresh()->access_level);
        $this->assertSame(FamilyAccountMember::STATUS_REMOVED, $removedMembership->fresh()->status);
        $this->assertSame(1, FamilyAccountMember::query()->where('user_id', $member->id)->count());
        $this->assertSame(1, FamilyAccountMember::query()->where('user_id', $removed->id)->count());
        $this->assertSame(0, Artisan::call('homecare:verify-family-accounts'));
    }
}
