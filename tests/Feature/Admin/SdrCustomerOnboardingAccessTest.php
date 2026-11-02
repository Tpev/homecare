<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CustomerOnboarding;
use App\Livewire\Admin\FamilyOnboardingIndex;
use App\Livewire\Admin\FamilyOnboardingShow;
use App\Models\CareRequest;
use App\Models\FamilyAccount;
use App\Models\FamilyAccountActivityLog;
use App\Models\FamilyOnboarding;
use App\Models\FamilyOnboardingDelivery;
use App\Models\FamilyPaymentSetupLink;
use App\Models\FamilyWelcomeVisit;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\Auth\LoLoCareResetPasswordNotification;
use App\Services\CustomerOnboarding\CustomerOnboardingAccountService;
use App\Services\CustomerOnboarding\FamilyPaymentSetupService;
use App\Services\FamilyAccounts\FamilyAccountProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SdrCustomerOnboardingAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $sdr;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.bypass' => true]);
        Mail::fake();
        Notification::fake();
        Queue::fake();
        $this->sdr = User::factory()->create(['role' => 'sdr']);
    }

    public function test_sdr_can_complete_the_account_payment_and_notes_workflow(): void
    {
        $lead = Lead::withoutEvents(fn () => Lead::create([
            'lead_type' => Lead::TYPE_FAMILY, 'name' => 'SDR prospect', 'email' => 'prospect@example.test',
            'phone' => '+19195550123', 'status' => 'new', 'source' => 'manual',
        ]));
        $this->actingAs($this->sdr)->get(route('admin.customer-onboarding.index'))->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $page = Livewire::actingAs($this->sdr)->test(CustomerOnboarding::class)
            ->call('openFamily', 'lead:'.$lead->id)
            ->set('note', 'Discussed weekly care.')->call('addNote')->assertHasNoErrors()
            ->call('startCreate')->assertSet('email', $lead->email)
            ->call('createFamily')->assertHasNoErrors();
        $account = FamilyAccount::findOrFail($lead->fresh()->family_account_id);
        $page->assertSet('selectedKey', 'account:'.$account->id)
            ->assertSeeHtml(route('admin.crm.index', ['lead' => $lead->id]))
            ->assertDontSee('View account');
        Notification::assertNothingSent();
        $this->assertSame('family', $account->owner->role);
        $this->assertNull($account->owner->email_verified_at);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id, 'actor_user_id' => $this->sdr->id, 'body' => 'Discussed weekly care.',
        ]);

        $page->set('note', 'Payment setup explained.')->call('addNote')->assertHasNoErrors()
            ->call('generatePaymentLink')->assertHasNoErrors()->assertSee('Show QR code')
            ->assertViewHas('paymentLinkUrl', fn ($url) => str_contains($url, '/payment-setup/'));
        $firstLink = FamilyPaymentSetupLink::sole();
        $this->assertSame($this->sdr->id, $firstLink->created_by_user_id);
        $page->call('generatePaymentLink')->assertHasNoErrors();
        $this->assertNotNull($firstLink->fresh()->revoked_at);
        $replacement = FamilyPaymentSetupLink::latest('id')->firstOrFail();
        $page->call('refreshPaymentStatus')->assertHasNoErrors()
            ->call('revokePaymentLink')->assertHasNoErrors()->assertViewHas('paymentLinkUrl', null)
            ->call('sendAccessEmail')->assertHasNoErrors();
        $this->assertNotNull($replacement->fresh()->revoked_at);
        Notification::assertSentToTimes($account->owner, LoLoCareResetPasswordNotification::class, 1);
        foreach (['customer_onboarding_account_created', 'customer_onboarding_note', 'payment_setup_link_generated',
            'payment_setup_link_revoked', 'customer_onboarding_access_email_sent'] as $action) {
            $this->assertDatabaseHas('family_account_activity_logs', [
                'family_account_id' => $account->id, 'actor_user_id' => $this->sdr->id, 'action' => $action,
            ]);
        }
        Mail::assertNothingSent();
    }

    public function test_sdr_can_open_intake_update_a_visit_and_reconcile_its_delivery(): void
    {
        $account = $this->account();
        $onboarding = $account->onboarding;
        $visit = $this->visit($onboarding);
        $delivery = FamilyOnboardingDelivery::create([
            'family_onboarding_id' => $onboarding->id, 'recipient' => 'ops@example.test', 'status' => 'unconfirmed',
        ]);
        $this->actingAs($this->sdr)->get(route('admin.family-onboarding.index'))->assertOk();
        $this->get(route('admin.family-onboarding.show', $onboarding))->assertOk()->assertSee('Welcome visit follow-up');
        Livewire::actingAs($this->sdr)->test(FamilyOnboardingIndex::class)
            ->set('filter', 'visits')->assertViewHas('families', fn ($rows) => $rows->sole()->id === $account->id);
        $page = Livewire::actingAs($this->sdr)->test(FamilyOnboardingShow::class, ['onboarding' => $onboarding])
            ->assertSee('SMS in the family CRM')->assertDontSeeHtml(route('admin.sms.index'))
            ->set('visitStatus', 'confirmed')->set('confirmedStart', now()->addDays(3)->format('Y-m-d').'T10:00')
            ->call('saveVisit')->assertHasErrors('textConfirmed')
            ->set('textConfirmed', true)->set('staffNote', 'Confirmed with the family.')
            ->call('saveVisit')->assertHasNoErrors();
        $this->assertSame('confirmed', $visit->fresh()->status);
        $this->assertSame($this->sdr->id, $visit->fresh()->handled_by_user_id);
        $page->set('deliveryReason', 'Verified acceptance with the mail provider.')
            ->call('resolveDelivery', $delivery->id, 'accepted')->assertHasNoErrors();
        $this->assertSame('accepted', $delivery->fresh()->status);
        foreach (['welcome_visit_updated', 'onboarding_email_reconciled'] as $action) {
            $this->assertDatabaseHas('family_account_activity_logs', ['actor_user_id' => $this->sdr->id, 'action' => $action]);
        }
    }

    public function test_onboarding_does_not_expose_global_admin_tools_to_sdr(): void
    {
        $account = app(FamilyAccountProvisioner::class)->provisionOwner(User::factory()->create(['role' => 'family']));
        $request = CareRequest::withoutEvents(fn () => CareRequest::create([
            'family_user_id' => $account->owner_user_id, 'family_account_id' => $account->id,
            'title' => 'Family request', 'status' => CareRequest::STATUS_OPEN, 'request_type' => 'one_time',
            'address_line1' => '123 Test Street', 'city' => 'Raleigh', 'state' => 'NC', 'zip' => '27601',
            'is_system_generated' => false,
        ]));
        Livewire::actingAs($this->sdr)->test(CustomerOnboarding::class)
            ->call('openFamily', 'account:'.$account->id)
            ->assertDontSeeHtml(route('admin.users.show', $account->owner_user_id))
            ->assertDontSeeHtml(route('admin.requests.show', $request));
        Livewire::actingAs($this->sdr)->test(FamilyOnboardingIndex::class)
            ->assertSeeHtml(route('admin.customer-onboarding.index', ['family' => 'account:'.$account->id]))
            ->assertSee('Request #'.$request->id)->assertDontSeeHtml(route('admin.requests.show', $request));
        foreach (['admin.users.index', 'admin.requests.index', 'admin.payments.ops', 'admin.sms.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->assertFalse($this->sdr->isAdministrator());
    }

    #[DataProvider('excludedRoles')]
    public function test_other_roles_cannot_access_any_onboarding_workspace(string $role): void
    {
        $account = $this->account();
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user);
        foreach (['admin.customer-onboarding.index', 'admin.family-onboarding.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->get(route('admin.family-onboarding.show', $account->onboarding))->assertForbidden();
        Livewire::test(CustomerOnboarding::class)->assertForbidden();
        Livewire::test(FamilyOnboardingIndex::class)->assertForbidden();
        Livewire::test(FamilyOnboardingShow::class, ['onboarding' => $account->onboarding])->assertForbidden();
    }

    public static function excludedRoles(): array
    {
        return [['family'], ['caregiver'], ['sales']];
    }

    #[DataProvider('customerActions')]
    public function test_open_workspace_rechecks_persisted_permissions_before_every_action(string $action, bool $revoked): void
    {
        $account = $this->account();
        $link = app(FamilyPaymentSetupService::class)->generate($this->sdr, $account);
        $page = Livewire::actingAs($this->sdr)->test(CustomerOnboarding::class)
            ->call('openFamily', 'account:'.$account->id)->set('name', 'Unauthorized')
            ->set('email', 'unauthorized@example.test')->set('note', 'Must not be saved.');
        $logCount = FamilyAccountActivityLog::count();
        User::whereKey($this->sdr->id)->update($revoked ? ['access_revoked_at' => now()] : ['role' => 'sales']);
        $page->call($action)->assertForbidden();
        $this->assertDatabaseCount('family_account_activity_logs', $logCount);
        $this->assertDatabaseCount('family_payment_setup_links', 1);
        $this->assertNull($link->fresh()->revoked_at);
        $this->assertDatabaseMissing('users', ['email' => 'unauthorized@example.test']);
        Notification::assertNothingSent();
    }

    public static function customerActions(): array
    {
        $cases = [];
        foreach (['createFamily', 'generatePaymentLink', 'revokePaymentLink', 'refreshPaymentStatus', 'sendAccessEmail', 'addNote'] as $action) {
            foreach ([false, true] as $revoked) {
                $cases[$action.($revoked ? ' revoked' : ' downgraded')] = [$action, $revoked];
            }
        }

        return $cases;
    }

    #[DataProvider('intakeActions')]
    public function test_open_intake_rechecks_permissions_before_mutation(string $action): void
    {
        $onboarding = $this->account()->onboarding;
        $visit = $this->visit($onboarding);
        $delivery = FamilyOnboardingDelivery::create([
            'family_onboarding_id' => $onboarding->id, 'recipient' => 'ops@example.test', 'status' => 'unconfirmed',
        ]);
        $page = Livewire::actingAs($this->sdr)->test(FamilyOnboardingShow::class, ['onboarding' => $onboarding])
            ->set('visitStatus', 'contacted')->set('deliveryReason', 'A sufficient explanation.');
        User::whereKey($this->sdr->id)->update(['role' => 'family']);
        $page->call($action, ...($action === 'resolveDelivery' ? [$delivery->id, 'accepted'] : []))->assertForbidden();
        $this->assertSame('requested', $visit->fresh()->status);
        $this->assertSame('unconfirmed', $delivery->fresh()->status);
    }

    public static function intakeActions(): array
    {
        return [['saveVisit'], ['resolveDelivery']];
    }

    #[DataProvider('serviceActions')]
    public function test_services_reject_stale_or_revoked_staff_models(string $action, string $role, bool $revoked): void
    {
        $account = $this->account();
        $actor = User::factory()->create(['role' => $role]);
        $payments = app(FamilyPaymentSetupService::class);
        $accounts = app(CustomerOnboardingAccountService::class);
        $link = $payments->generate($actor, $account);
        User::whereKey($actor->id)->update($revoked ? ['access_revoked_at' => now()] : ['role' => 'sales']);
        try {
            match ($action) {
                'create' => $accounts->create($actor, ['name' => 'Unauthorized', 'email' => 'unauthorized@example.test']),
                'sendAccessEmail' => $accounts->sendAccessEmail($actor, $account),
                'generate' => $payments->generate($actor, $account),
                'revoke' => $payments->revoke($actor, $link),
            };
            $this->fail('An old staff model must not retain permission.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertNull($link->fresh()->revoked_at);
        $this->assertDatabaseCount('family_payment_setup_links', 1);
        $this->assertDatabaseMissing('users', ['email' => 'unauthorized@example.test']);
        Notification::assertNothingSent();
    }

    public static function serviceActions(): array
    {
        $cases = [];
        foreach (['create', 'sendAccessEmail', 'generate', 'revoke'] as $action) {
            foreach (['admin', 'sdr'] as $role) {
                foreach ([false, true] as $revoked) {
                    $cases[$action.' '.$role.($revoked ? ' revoked' : ' downgraded')] = [$action, $role, $revoked];
                }
            }
        }

        return $cases;
    }

    private function account(): FamilyAccount
    {
        return app(CustomerOnboardingAccountService::class)->create($this->sdr, [
            'name' => 'Prepared family', 'email' => fake()->unique()->safeEmail(),
        ]);
    }

    private function visit(FamilyOnboarding $onboarding): FamilyWelcomeVisit
    {
        return FamilyWelcomeVisit::create([
            'family_onboarding_id' => $onboarding->id, 'preferred_date' => now()->addDays(3)->toDateString(),
            'preferred_range' => 'morning', 'timezone' => 'America/New_York', 'phone' => '+19195550123',
            'window_start_at' => now()->addDays(3), 'window_end_at' => now()->addDays(3)->addHours(3),
        ]);
    }
}
